<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Parser;

/**
 * Interface ListenerInterface
 * @package Bcn\Component\Json\Parser
 */
interface ListenerInterface
{
    /**
     * @param  integer $line
     * @param  integer $char
     * @return void
     */
    public function file_position($line, $char);

    /**
     * @return void
     */
    public function start_document();

    /**
     * @return void
     */
    public function end_document();

    /**
     * @return void
     */
    public function start_object();

    /**
     * @return void
     */
    public function end_object();

    /**
     * @return void
     */
    public function start_array();

    /**
     * @return void
     */
    public function end_array();

    /**
     * @param  string $key
     * @return void
     */
    public function key($key);

    /**
     * @param  string $value
     * @return void
     */
    public function value($value);

}
