<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Tests\Parser;

use Bcn\Component\Json\Parser;
use Bcn\Component\Json\Tests\Parser\FunctionalTest\TestListener;

/**
 * Class FunctionalTest
 * @package Bcn\Component\Json\Tests\Parser
 */
class FunctionalTest extends \PHPUnit_Framework_TestCase
{

    /**
     *
     */
    public function testTraverseOrder()
    {
        $listener = new TestListener();
        $parser = new Parser(fopen($this->getFixtureName('example.json'), 'r'), $listener);
        $parser->parse();

        $this->assertEquals(
            array(
                'start_document',
                'start_array',
                'start_object',
                'key = name',
                'value = example document for wicked fast parsing of huge json docs',
                'key = integer',
                'value = 123',
                'key = totally sweet scientific notation',
                'value = -1.23123',
                'key = unicode? you betcha!',
                'value = ú™£¢∞§♥',
                'key = zero character',
                'value = 0',
                'key = null is boring',
                'value = NULL',
                'end_object',
                'start_object',
                'key = name',
                'value = another object',
                'key = cooler than first object?',
                'value = 1',
                'key = nested object',
                'start_object',
                'key = nested object?',
                'value = 1',
                'key = is nested array the same combination i have on my luggage?',
                'value = 1',
                'key = nested array',
                'start_array',
                'value = 1',
                'value = 2',
                'value = 3',
                'value = 4',
                'value = 5',
                'end_array',
                'end_object',
                'key = false',
                'value = false',
                'end_object',
                'end_array',
                'end_document',
            ),
            $listener->order
        );
    }

    /**
     *
     */
    public function testListenerGetsNotifiedAboutPositionInFileOfDataRead()
    {
        $listener = new TestListener();
        $parser = new Parser(fopen($this->getFixtureName('data-ranges.json'), 'r'), $listener);
        $parser->parse();

        $this->assertEquals(
            array(
                array('value' => '2013-10-24', 'line' => 5,  'char' => 42),
                array('value' => '2013-10-25', 'line' => 5,  'char' => 67),
                array('value' => '2013-10-26', 'line' => 6,  'char' => 42),
                array('value' => '2013-10-27', 'line' => 6,  'char' => 67),
                array('value' => '2013-11-01', 'line' => 10, 'char' => 46),
                array('value' => '2013-11-10', 'line' => 10, 'char' => 71),
            ),
            $listener->positions
        );
    }

    public function testCountsLongLinesCorrectly()
    {
        $value = str_repeat('!', 10000);
        $longStream = self::inMemoryStream(<<<JSON
[
  "$value",
  "$value"
]
JSON
        );

        $listener = new TestListener();
        $parser = new Parser($longStream, $listener);
        $parser->parse();

        unset($listener->positions[0]['value']);
        unset($listener->positions[1]['value']);

        $this->assertSame(array(
                array('line' => 2, 'char' => 10004,),
                array('line' => 3, 'char' => 10004,),
            ),
            $listener->positions
        );
    }

    /**
     *
     */
    public function testThrowsParingError()
    {
        $listener = new TestListener();
        $parser = new Parser(self::inMemoryStream('{ invalid json }'), $listener);

        $this->setExpectedException('Bcn\Component\Json\Exception\ParsingError', 'Parsing error in [1:3]');
        $parser->parse();
    }

    /**
     * @param $content
     * @return resource
     */
    private static function inMemoryStream($content)
    {
        $stream = fopen('php://memory', 'rw');
        fwrite($stream, $content);
        fseek($stream, 0);

        return $stream;
    }

    /**
     * @param  string $name
     * @return string
     */
    protected function getFixtureName($name)
    {
        return __DIR__ . '/FunctionalTest/fixtures/' . $name;
    }
}
