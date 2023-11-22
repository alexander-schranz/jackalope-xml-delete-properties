<?php

namespace App;

class XmlPropsRemover
{
    /**
     * @var string
     */
    private $xml;

    /**
     * @var string[]
     */
    private $propertyNames;

    /**
     * @var bool
     */
    private $skipCurrentTag = false;

    /**
     * @var string
     */
    private $newXml = '';


    public function __construct(string $xml, array $propertyNames)
    {
        $this->xml = $xml;
        $this->propertyNames = $propertyNames;
    }

    /**
     * @return \stdClass
     */
    public function removeProps(): string
    {
        $this->newXml = '<?xml version="1.0" encoding="UTF-8"?>';
        $this->skipCurrentTag = false;

        $parser = xml_parser_create();

        xml_set_element_handler(
            $parser,
            [$this, 'startHandler'],
            [$this, 'endHandler']
        );

        xml_set_character_data_handler($parser, [$this, 'dataHandler']);

        xml_parse($parser, $this->xml, true);
        xml_parser_free($parser);
        // avoid memory leaks and unset the parser see: https://www.php.net/manual/de/function.xml-parser-free.php
        unset($parser);

        return $this->newXml;
    }

    /**
     * @param \XmlParser $parser
     * @param string $name
     * @param mixed[] $attrs
     */
    private function startHandler($parser, $name, $attrs): void
    {
        if ($this->skipCurrentTag) {
            return;
        }

        if ($name === 'SV:PROPERTY') {
            $svName = $attrs['SV:NAME'];

            if (\in_array($svName, $this->propertyNames)) {
                $this->skipCurrentTag = true;

                echo $svName . ' ' . \count([] /* TODO queries */) . PHP_EOL;

                return;
            }
        }

        $tag = '<' . \strtolower($name);
        foreach ($attrs as $key => $value) {
            $tag .= ' ' . \strtolower($key) . '="' . $value . '"'; // TODO escaping
        }
        $tag .= '>';

        $this->newXml .= $tag;

        // TODO removed weakreferences and references need to be returned
    }


    private function endHandler($parser, $name): void
    {
        if ($name === 'SV:PROPERTY' && $this->skipCurrentTag) {
            $this->skipCurrentTag = false;

            return;
        }

        $this->newXml .= '</' . \strtolower($name) . '>';
    }

    private function dataHandler($parser, $data): void
    {
        if ($this->skipCurrentTag) {
            $this->skipCurrentTag = false;
        }

        $this->newXml .= $data;  // TODO escaping
    }
}
