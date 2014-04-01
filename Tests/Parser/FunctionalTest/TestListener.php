<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Tests\Parser\FunctionalTest;
use Bcn\Component\Json\Parser\ListenerInterface;

/**
 * Class TestListener
 * @package Bcn\Component\Json\Tests\Parser\FunctionalTest
 */
class TestListener implements ListenerInterface
{

    /** @var array */
    public $order = array();
    /** @var array */
    public $positions = array();
    /** @var integer */
    private $currentLine;
    /** @var integer */
    private $currentChar;

    public function file_position($line, $char)
    {
        $this->currentLine = $line;
        $this->currentChar = $char;
    }

    public function start_document()
    {
        $this->order[] = __FUNCTION__;
    }

    public function end_document()
    {
        $this->order[] = __FUNCTION__;
    }

    public function start_object()
    {
        $this->order[] = __FUNCTION__;
    }

    public function end_object()
    {
        $this->order[] = __FUNCTION__;
    }

    public function start_array()
    {
        $this->order[] = __FUNCTION__;
    }

    public function end_array()
    {
        $this->order[] = __FUNCTION__;
    }

    public function key($key)
    {
        $this->order[] = __FUNCTION__ . ' = ' . self::stringify($key);
    }

    public function value($value)
    {
        $this->order[] = __FUNCTION__ . ' = ' . self::stringify($value);
        $this->positions[] = array('value' => $value, 'line' => $this->currentLine, 'char' => $this->currentChar);
    }

    private static function stringify($value)
    {
        return strlen($value) ? $value : var_export($value, true);
    }
}
