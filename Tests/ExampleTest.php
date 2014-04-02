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
use Bcn\Component\Json\Writer;
use Bcn\Component\StreamWrapper\Stream;

class ExampleTest extends \PHPUnit_Framework_TestCase
{

    public function testWriting()
    {
        $catalog = $this->getData();
        $filename = new Stream();

        $fh = fopen($filename, "w");
        $writer = new Writer($fh);

        $writer->enter(Writer::TYPE_OBJECT);                // enter root object
            $writer->write("catalog", $catalog['id']);      // write key-value entry
            $writer->enter("items", Writer::TYPE_ARRAY);    // enter items array
                foreach ($catalog['products'] as $product) {
                    $writer->write(null, array(             // write an array item
                        'sku'  => $product['sku'],
                        'name' => $product['name']
                    ));
                }
            $writer->leave();                               // leave items array
        $writer->leave();                                   // leave root object

        fclose($fh);

        $this->assertEquals($this->getJSON(), $filename->getContent());
    }

    /**
     *
     */
    public function testReading()
    {
        $filename = new Stream($this->getJSON());
        $catalog = array();

        $fh = fopen($filename, "r");

        $reader = new Reader($fh);
        $reader->enter(Reader::TYPE_OBJECT);                // enter root object
            $catalog['id'] = $reader->read("catalog");      // read catalog node
            $reader->enter("items", Reader::TYPE_ARRAY);    // enter item array
                while ($product = $reader->read()) {         // read product structure
                    $catalog['products'][] = $product;
                }
            $reader->leave();                               // leave item node
        $reader->leave();                                   // leave root object

        fclose($fh);

        $this->assertEquals($this->getData(), $catalog);
    }

    /**
     * @return array
     */
    protected function getData()
    {
        return array(
            'id' => 19,
            'products' => array(
                array("sku" => "0001", "name" => "Product #1"),
                array("sku" => "0002", "name" => "Product #2")
            )
        );
    }

    /**
     * @return string
     */
    protected function getJSON()
    {
        return '{"catalog":19,"items":[{"sku":"0001","name":"Product #1"},{"sku":"0002","name":"Product #2"}]}';
    }

}
