<?php

namespace Makotokw\TwientBot\Storage;

use DomDocument;
use Exception;
use SimpleXMLElement;

class XmlFileStorage extends TextFileStorage
{
    /**
     * @param $path
     * @return array
     * @throws Exception
     */
    protected function readFormFile($path)
    {
        $messages = [];
        $xml = new SimpleXMLElement(file_get_contents($path));
        $result = $xml->xpath('/list/item');
        if (!$result) {
            throw new Exception('invalid xml format: ' . $path);
        }
        foreach ($result as $node) {
            $text = trim((string)$node);
            if (!empty($text)) {
                $messages[] = $text;
            }
        }
        return $messages;
    }

    protected function writeToFile($path, $messages)
    {
        $dom = new DomDocument('1.0', 'UTF-8');
        $list = $dom->appendChild($dom->createElement('list'));
        foreach ($messages as $message) {
            $item = $list->appendChild($dom->createElement('item'));
            $item->appendChild($dom->createTextNode($message));
        }
        $dom->formatOutput = true;
        $dom->save($path);
    }
}
