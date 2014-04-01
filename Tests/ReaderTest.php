<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Tests;

use Bcn\Component\Json\Reader;
use Bcn\Component\StreamWrapper\Stream;
use Symfony\Component\Yaml\Yaml;

class ReaderTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @param string $content
     * @param array  $tokens
     *
     * @dataProvider provideTokens
     */
    public function testTokenizer($content, array $tokens)
    {
        $resource = fopen(new Stream($content), "r");

        $reader = new Reader($resource);
        foreach ($tokens as $token) {
            $token['token'] = $this->toTokenCode($token['token']);
            $this->assertEquals($token, $reader->next());
        }

        fclose($resource);
    }

    protected function toTokenCode($code)
    {
        switch ($code) {
            case 'scalar':       return Reader::TOKEN_SCALAR;
            case 'array_start':  return Reader::TOKEN_ARRAY_START;
            case 'object_start': return Reader::TOKEN_OBJECT_START;
            case 'array_end':    return Reader::TOKEN_ARRAY_END;
            case 'object_end':   return Reader::TOKEN_OBJECT_END;
        }

        return $code;
    }

    /**
     * @return array
     */
    public function provideTokens()
    {
        return Yaml::parse(__DIR__ . DIRECTORY_SEPARATOR . "fixtures" . DIRECTORY_SEPARATOR . "tokenizer.yml");
    }

    /**
     *
     */
    public function testObjectReading()
    {
        $this->markTestIncomplete("Waiting for complete implementation of Reader");

        $reader = new Reader($stream = fopen("", "r"));

        $reader->enter(null, Reader::TYPE_OBJECT); // enter root object
            $catalog = $reader->read("catalog");   // read property catalog
            $stock   = $reader->read("stock");     // read property stock
            $items   = array();
            $reader->enter("items");               // enter property items
                while ($reader->enter()) {          // enter each item
                    $sku = $reader->read("sku");   // read property sku
                    $qty = $reader->read("qty");   // read property qty
                    $reader->leave();              // leave item node

                    $items[] = array("sku" => $sku, "qty" => $qty);
                }
            $reader->leave();                      // leave items node
        $reader->leave();                          // leave root node

        fclose($stream);

        $this->assertEquals("catalog_code", $catalog);
        $this->assertEquals("stock_code",   $stock);

    }

}
