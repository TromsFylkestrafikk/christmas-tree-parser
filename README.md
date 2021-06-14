# Christmas Tree Parser

A memory-efficient and asynchronous XML parser based on XMLReader and
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
matched node as a SimpleXMLElement object. Unfortunately this tool
doesn't support XPath queries, only element paths as arrays. So in an
exmple XML file containing
```xml
<?xml version="1.0"?>
<Library>
  <Book>
    <Title>Practical XML parsing in PHP</Title>
    <Author>Seasoned Php Developer</Author>
  </Book>
</Library>
```

you can add a callback for `Title` nodes using as any of these as path:
- `'Title'`
- `['Title']`
- `['Library', 'Title']`
- `['Book', 'Title']`
- `['Library', 'Book', 'Title']`

depending on your need for granularity.

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
        $title = $xml->Title;
        $author = $xml->Author;
    }
}

```

# Copying

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or (at
your option) any later version.

This program is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <https://www.gnu.org/licenses/>.
