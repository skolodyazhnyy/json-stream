<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Tests;

use Bcn\Component\Json\Writer;

/**
 * Class WriterTest
 * @package Bcn\Component\Json\Tests
 */
class WriterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @param $data
     *
     * @dataProvider provideEncodeData
     */
    public function testInsert($data)
    {
        $stream = fopen("php://memory", "r+");
        $writer = new Writer($stream);
        $writer->insert($data);

        rewind($stream);
        $encoded = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals($data, json_decode($encoded, true));
    }

    /**
     * @return array
     */
    public function provideEncodeData()
    {
        return array(
            'String'                    => array("String"),
            'String with special chars' => array("String\"\'\\\/!@#$%^&*()!@¡™£¢"),
            'Integer'                   => array(1),
            'Decimal'                   => array(9.9991119),
            'Array'                     => array(1, 2, 3),
            'Object'                    => array('a' => 1, 'b' => 2),
            'Array of objects'          => array(array('a' => 1, 'b' => 2), array('a' => 1, 'b' => 2)),
            'Multilevel object'         => array('a' => array('a' => 1, 'b' => 2), 'b' => array('a' => 1, 'b' => 2)),
            'Object with special keys'  => array('¡Hola!' => 'Hello!')
        );
    }

    /**
     * @param $items
     *
     * @dataProvider provideArrayData
     */
    public function testArrayWriting($items)
    {
        $stream = fopen("php://memory", "r+");
        $writer = new Writer($stream);
        $writer->start();
        foreach ($items as $item) {
           $writer->insert($item);
        }
        $writer->end();

        rewind($stream);
        $encoded = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals($items, json_decode($encoded, true), $encoded);
    }

    /**
     * @return array
     */
    public function provideArrayData()
    {
        return array(
            'Array without items'       => array(array()),
            'Array with one item'       => array(array(1)),
            'Array with multiple items' => array(array(1, 2, 3)),
            'Nested array'              => array(array(array(1, 2), array(2, 3), 4))
        );
    }

    /**
     * @param $items
     *
     * @dataProvider provideObjectData
     */
    public function testObjectWriting($items)
    {
        $stream = fopen("php://memory", "r+");
        $writer = new Writer($stream);
        $writer->start(true);
        foreach ($items as $key => $item) {
           $writer->key($key);
           $writer->insert($item);
        }
        $writer->end();

        rewind($stream);
        $encoded = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals($items, json_decode($encoded, true), $encoded);
    }

    /**
     * @return array
     */
    public function provideObjectData()
    {
        return array(
            'Empty object'               => array(array()),
            'Object with one item'       => array(array('a' => 1)),
            'Object with numeric keys'   => array(array('2' => 1, '11' => 3)),
            'Object with multiple items' => array(array('a' => 1, 'b' => 2, 'c' => 3)),
            'Nested object'              => array(array(
                'a' => array('aa' => 1, 'ab' => 2),
                'b' => array('ba' => 2, 'bb' => 3),
                'c' => 4)
            )
        );
    }

    /**
     *
     */
    public function testBrackets()
    {
        $stream = fopen("php://memory", "r+");
        $writer = new Writer($stream);
        $writer
            ->start(true)
                ->key("key")
                ->start()
                    ->start(true)
                        ->key('inner')
                        ->start()
                            ->start()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        rewind($stream);
        $encoded = stream_get_contents($stream);
        fclose($stream);

        $this->assertEquals(
            array("key" => array(array('inner' => array(array())))),
            json_decode($encoded, true),
            $encoded
        );
    }

}
