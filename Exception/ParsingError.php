<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Exception;

/**
 * Class ParsingError
 * @package Bcn\Component\Json\Exception
 */
class ParsingError extends \Exception
{
    /**
     * @param int    $line
     * @param int    $char
     * @param string $message
     */
    public function __construct($line, $char, $message)
    {
        parent::__construct("Parsing error in [$line:$char]: " . $message);
    }
}
