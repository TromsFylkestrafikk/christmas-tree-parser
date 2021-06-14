# Christmas Tree Parser

An memory-efficient and asynchronous XML parser based on XMLReader and
SimpleXMLElement.

## Install

Add to composer.json:
```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/TromsFylkestrafikk/christmas-tree-parser"
        }
    ]
}
```
then run
```shell
composer require tromsfylkestrafikk/christmas-tree-parser
```

## Usage
Add callbacks for your desired path/branch/twig elements and fetch the
actual node as a SimpleXMLElement object. Unfortunately this tool
doesn't support XPath queries, only element paths as arrays. So in an
exmple XML file containing
```xml
<?xml version="1.0"?>
<Library>
  <Book>
    <Title>Practical XML parsingin PHP</Title>
    <Author>Seasoned Php Developer</Author>
  </Book>
</Library>
```
you can add a callback as an XML path to e.g. `Title` as any of these:
- `'Title'`
- `['Title']`
- `['Library', 'Title']`
- `['Book', 'Title']`
- `['Library', 'Book', 'Title']`
depending on need for granularity.

Full-scale example:

```php
use TromsFylkestrafikk\Xml\ChristmasTreeParser;

class LibraryReader
{
    public function readLibrary($library)
    {
        $reader = new ChristmasTreeParser();
        $reader->open($library);
        $reader->addCallback('Library', function() { echo "Parsing library"; })
            ->addCallback(['Library', 'Book'], [$this, 'readBook'])
            ->parse()
            ->close();
    }

    /**
     * Callback handler for //Library//Book XML elements.
     *
     * Due to the nature of this being called as an instance of \LibraryReader,
     * the callback handler has to be declared public.
     *
     * @param \TromsFylkestrafikk\Xml\ChristmasTreeParser $reader
     */
    public function readBook($reader)
    {
        $xml = $reader->expandSimpleXml();
        $author = $xml->Author;
        $isbn = $Xml->Isbn;
        // ...
    }
}

```
