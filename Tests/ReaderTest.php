<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Tests;

use Bcn\Component\Json\Exception\ReadingError;
use Bcn\Component\Json\Reader;
use Bcn\Component\StreamWrapper\Stream;
use PHPUnit\Framework\TestCase;

class ReaderTest extends TestCase
{

    /**
     *
     */
    public function testObjectReading()
    {
        $stream = new Stream(<<<JSON
        {
            "catalog": "catalog_code",
            "stock": "stock_code",
            "items": [
                {"sku":"ABC","qty":1},
                {"sku":"A\"BC","qty":.095},
                {"sku":"CDE","qty":0}
            ]
        }
JSON
        );

        $reader = new Reader(fopen($stream, "r"));

        static::assertTrue($reader->enter(null, Reader::TYPE_OBJECT)); // enter root object
            $catalog = $reader->read("catalog");                       // read property catalog
            $stock   = $reader->read("stock");                         // read property stock
            $items   = array();
            static::assertTrue($reader->enter("items"));               // enter property items
                while ($reader->enter()) {                             // enter each item
                    $sku = $reader->read("sku");                       // read property sku
                    $qty = $reader->read("qty");                       // read property qty
                    $reader->leave();                                  // leave item node

                    $items[] = array("sku" => $sku, "qty" => $qty);
                }
            $reader->leave();                                          // leave items node
        $reader->leave();                                              // leave root node

        static::assertEquals("catalog_code", $catalog);
        static::assertEquals("stock_code",   $stock);
        static::assertEquals(array(
            array('sku' => 'ABC',  'qty' => 1),
            array('sku' => 'A"BC', 'qty' => .095),
            array('sku' => 'CDE',  'qty' => 0)
        ),   $items);

    }
    /**
     *
     */
    public function testExtractReading()
    {
        $stream = new Stream(<<<JSON
        {
            "catalog": "catalog_code",
            "stock": "stock_code",
            "items": [
                {"sku":"ABC","qty":1},
                {"sku":"A\"BC","qty":.095},
                {"sku":"CDE","qty":0}
            ]
        }
JSON
        );

        $reader = new Reader(fopen($stream, "r"));
        static::assertEquals(array(
            'catalog' => 'catalog_code',
            'stock' => 'stock_code',
            'items' => array(
                array('sku' => 'ABC',  'qty' => 1),
                array('sku' => 'A"BC', 'qty' => 0.095),
                array('sku' => 'CDE',  'qty' => 0),
            )
        ), $reader->read());
    }

    /**
     *
     */
    public function testReaderLeaving()
    {
        $stream = new Stream(<<<JSON
        {
            "first": "1",
            "items": [
                "String",
                {"sku":"ABC","qty":1},
                {"sku":"A\"BC","qty":.095},
                999,
                {"sku":"CDE","qty":[{  }]}
            ],
            "last": "2"
        }
JSON
        );

        $reader = new Reader(fopen($stream, "r"));

        static::assertTrue($reader->enter(null, Reader::TYPE_OBJECT)); // enter root object
        $first = $reader->read("first");                               // read property first
        static::assertTrue($reader->enter("items"));                   // enter property items
        static::assertTrue($reader->leave());                         // leave items node
        $last = $reader->read("last");                                // leave root node

        static::assertEquals("1", $first);
        static::assertEquals("2", $last);

    }

    /**
     * @param $string
     * @param $data
     *
     * @dataProvider provideReadingData
     */
    public function testReading($string, $data)
    {
        $stream = new Stream($string);
        $reader = new Reader(fopen($stream, "r"));
        static::assertEquals($data, $reader->read());
    }

    /**
     * @return array
     */
    public function provideReadingData()
    {
        return array(
            "Double quoted string" => array('"test"', "test"),
            "Escaped string"       => array(json_encode("\"!@\n\t#$%^&*()_+/\\\"\\'"), "\"!@\n\t#$%^&*()_+/\\\"\\'"),
            "Integer"              => array("12345", 12345),
            "Float"                => array("123.45", 123.45),
        );
    }

    /**
     * @param $content
     * @dataProvider provideMalformedFiles
     */
    public function testMalformedFileReading($content)
    {
        $this->expectException(ReadingError::class);

        $reader = new Reader(fopen(new Stream($content), "r"));
        $reader->read();
    }

    /**
     * @return array
     */
    public function provideMalformedFiles()
    {
        return array(
            'Unquoted string'                       => array('string'),
            'Unwrapped array items'                 => array('"Array", "Array"'),
            'Property name in non-object context'   => array('"key": "Value"'),
            'Property name in array'                => array('["key": "Value"]'),
            'Malformed object'                     => array('{"key": "Value": "Test"}')
        );
    }

}
