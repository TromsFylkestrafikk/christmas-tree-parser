<?php

/**
 * @file
 * Contains ChristmasTreeParser
 *
 * @author Kåre Slettnes <kaare.slettnes@tffk.no>
 */

namespace TromsFylkestrafikk\Xml;

use DomDocument;
use Exception;
use SimpleXMLElement;
use XMLReader;

class ChristmasTreeParser extends XMLReader
{
    /**
     * Current element being parsed
     *
     * @var string
     */
    public $elementName = '';

    /**
     * Attributes associated with currently parsed element.
     *
     * @var string[]
     */
    public $attributes = [];

    /**
     * Name space for current element.
     *
     * @var string
     */
    public $elementNsUri = '';

    /**
     * @var int
     */
    protected $elementLimit = 0;

    /**
     * @var int
     */
    protected $elementCount = 0;

    /**
     * @var bool
     */
    protected $continueParsing = true;

    /**
     * Injected callback handlers.
     *
     * @var array
     */
    protected $callTree = [];

    /**
     * List of parent elements of currently parsed element.
     *
     * @var string[]
     */
    protected $parents = [];

    /**
     * Add callback handler for parsing an element
     *
     * @param string|array $pattern
     *   Element name or list of names to add callback for. If using array, it
     *   can contain any of the elements leading up to the matching element.
     * @param callable $callback
     *   The callback handler
     *
     * @return \App\Xml\ChristmasTreeParser
     */
    public function addCallback($pattern, $callback): ChristmasTreeParser
    {
        if (!is_array($pattern)) {
            $pattern = [$pattern];
        }
        $name = end($pattern);
        if (!isset($this->callTree[$name])) {
            $this->callTree[$name] = [];
        } else {
            $this->removeCallback($pattern);
        }
        $this->callTree[$name][] = [
            'callback' => $callback,
            'pattern' => $pattern,
        ];
        usort($this->callTree[$name], function ($cb1, $cb2) {
            return count($cb2['pattern']) - count($cb1['pattern']);
        });
        return $this;
    }

    /**
     * Add callback handler for $pattern as children of $this->parents
     *
     * @param array|string $pattern Element(s) that are children of current
     *   position in XML tree.
     * @param callable $callback Handler to invoke for given match.
     *
     * @return ChristmasTreeParser
     */
    public function addNestedCallback($pattern, $callback): ChristmasTreeParser
    {
        if (!is_array($pattern)) {
            $pattern = [ $pattern ];
        }
        $this->addCallback(array_merge($this->parents, $pattern), $callback);
        return $this;
    }

    /**
     * Remove callback handler for given pattern
     *
     * @param string|array $pattern The pattern to remove handler for
     *
     * @return \App\Xml\ChristmasTreeParser
     */
    public function removeCallback($pattern): ChristmasTreeParser
    {
        if (! is_array($pattern)) {
            $pattern = [ $pattern ];
        }
        $name = end($pattern);
        if (empty($this->callTree[$name])) {
            return $this;
        }
        foreach ($this->callTree[$name] as $index => $candidate) {
            if (empty(array_diff($pattern, $candidate['pattern']))) {
                array_splice($this->callTree[$name], $index, 1);
                return $this;
            }
        }
        return $this;
    }

    /**
     * Set max number of elements to read
     *
     * @param int $limit
     *
     * @return \App\Xml\ChristmasTreeParser
     */
    public function setElementLimit(int $limit = 0): ChristmasTreeParser
    {
        $this->elementLimit = $limit;
        return $this;
    }

    /**
     * Stop all further parsing
     */
    public function halt()
    {
        $this->continueParsing = false;
    }

    /**
     * Start parsing the XML Document.
     *
     * @return \App\Xml\ChristmasTreeParser
     */
    public function parse(): ChristmasTreeParser
    {
        $this->continueParsing = true;
        while ($this->continueParsing && $this->read()) {
            switch ($this->nodeType) {
                case self::ELEMENT:
                    $isEmpty = $this->isEmptyElement;
                    $this->elementCount++;
                    array_push($this->parents, $this->localName);
                    $this->elementName = $this->localName;
                    $this->elementPrefix = $this->prefix;
                    $this->elementNsUri = $this->namespaceURI;
                    $this->parseAttributes();
                    $this->invokeIfExists();
                    if ($isEmpty) {
                        array_pop($this->parents);
                    }
                    break;

                case self::END_ELEMENT:
                    $name = array_pop($this->parents);
                    if ($name !== $this->localName) {
                        throw new Exception(sprintf(
                            "Invalid XML end tag. Expected '%s', got '%s'",
                            $name,
                            $this->localname
                        ));
                    }
                    break;
            }
            if ($this->elementLimit && $this->elementCount > $this->elementLimit) {
                return $this;
            }
        }
        return $this;
    }

    /**
     * Close XML descriptor
     */
    public function close()
    {
        $this->elementCount = 0;
        $this->elementName = '';
        $this->attributes = [];
        $this->parents = [];
        return parent::close();
    }

    /**
     * Expand current element as a SimpleXMLElement object.
     *
     * Useful in callback handlers when it is assumed the current element isn’t
     * of enormous proportions and you want simple parsing of it.
     *
     * @return \SimpleXMLElement
     */
    public function expandSimpleXml(): SimpleXMLElement
    {
        $document = new DomDocument('1.0', 'UTF-8');
        $document->preserveWhiteSpace = false;
        $element = $document->importNode($this->expand(), true);
        $document->appendChild($element);
        return simplexml_import_dom($element);
    }

    protected function indent(): string
    {
        return str_repeat('  ', count($this->parents));
    }

    /**
     * Build an array of all attributes and their values of current element.
     */
    protected function parseAttributes()
    {
        $this->attributes = [];
        while ($this->moveToNextAttribute()) {
            $this->attributes[$this->name] = $this->value;
        }
        $this->moveToFirstAttribute();
    }

    /**
     * Call handler associated with current element
     */
    protected function invokeIfExists()
    {
        if (empty($this->callTree[$this->elementName])) {
            return;
        }
        // Candidates are ordered by number of items in pattern up to current
        // element, with the most specific (most items) first.
        $parent_count = count($this->parents);
        foreach ($this->callTree[$this->elementName] as $candidate) {
            $parent_start = 0;
            $pattern_idx = 0;
            $pattern_count = count($candidate['pattern']);
            foreach ($candidate['pattern'] as $pattern_step) {
                $pattern_idx++;
                for ($parent_idx = $parent_start; $parent_idx < $parent_count; $parent_idx++) {
                    if ($this->parents[$parent_idx] == $pattern_step) {
                        // It’s a match between candidate in parents and
                        // candidate in pattern. If it is the last and final
                        // step in pattern, we’re ready to invoke our
                        // callback. If not, progress to next items in both
                        // lists.
                        if ($pattern_idx == $pattern_count) {
                            call_user_func($candidate['callback'], $this);
                            return;
                        }
                        $parent_start = $parent_idx + 1;
                        break;
                    }
                }
                if ($parent_idx >= $parent_count) {
                    break;
                }
            }
        }
    }

    /**
     * Create a callback handler that feeds given destination with content.
     */
    public static function feederFactory(&$destination)
    {
        return function (ChristmasTreeParser $reader) use (&$destination) {
            if (is_array($destination)) {
                $destination[$reader->elementName] = trim($reader->readString());
            } else {
                $destination->{$reader->elementName} = trim($reader->readString());
            }
        };
    }

    /**
     * Map (nested) element patterns to destination during parsing.
     *
     * NOTE! The patterns are added as children of current path/parents during
     * parsing.
     *
     * @param array $patterns List of patterns as string or array to add mapping for
     * @param array|object $destination Where text-values of given mapping is to
     *   be stored.
     */
    public function mapElementsToObject(array $patterns, &$destination): ChristmasTreeParser
    {
        $handler = self::feederFactory($destination);
        foreach ($patterns as $pattern) {
            $this->addNestedCallback($pattern, $handler);
        }
        return $this;
    }

    public function debug(): string
    {
        return print_r(array_map(function ($item) {
            return $item[0]['pattern'];
        }, $this->callTree), true);
    }
}
