<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json;

use Bcn\Component\Json\Parser\ListenerInterface;
use Bcn\Component\Json\Exception\ParsingError;

/**
 * Class Parser
 * @package Bcn\Component\Json
 */
class Parser
{
    const STATE_START_DOCUMENT     = 0;
    const STATE_DONE               = -1;
    const STATE_IN_ARRAY           = 1;
    const STATE_IN_OBJECT          = 2;
    const STATE_END_KEY            = 3;
    const STATE_AFTER_KEY          = 4;
    const STATE_IN_STRING          = 5;
    const STATE_START_ESCAPE       = 6;
    const STATE_UNICODE            = 7;
    const STATE_IN_NUMBER          = 8;
    const STATE_IN_TRUE            = 9;
    const STATE_IN_FALSE           = 10;
    const STATE_IN_NULL            = 11;
    const STATE_AFTER_VALUE        = 12;

    const STACK_OBJECT             = 0;
    const STACK_ARRAY              = 1;
    const STACK_KEY                = 2;
    const STACK_STRING             = 3;

    /** @var array */
    private $stack;
    /** @var int */
    private $state;
    /** @var resource */
    private $stream;
    /** @var Parser/Listener */
    private $listener;
    /** @var string */
    private $buffer;
    /** @var int */
    private $bufferSize;
    /** @var array */
    private $unicodeBuffer;
    /** @var int */
    private $unicodeHighCodePoint;
    /** @var string */
    private $lineEnding;
    /** @var integer */
    private $lineNumber;
    /** @var integer */
    private $charNumber;

    /**
     * @param  resource                  $stream
     * @param  ListenerInterface         $listener
     * @param  string                    $lineEnding
     * @throws \InvalidArgumentException
     */
    public function __construct($stream, ListenerInterface $listener, $lineEnding = null)
    {
        if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
            throw new \InvalidArgumentException("Argument is not a stream");
        }

        $this->stream = $stream;
        $this->listener = $listener;

        $this->state = self::STATE_START_DOCUMENT;
        $this->stack = array();

        $this->buffer = '';
        $this->bufferSize = 8192;
        $this->unicodeBuffer = array();
        $this->unicodeHighCodePoint = -1;
        $this->lineEnding = $lineEnding ?: "\n";;
    }

    /**
     * Parse stream content
     */
    public function parse()
    {
        $this->lineNumber = 1;
        $this->charNumber = 1;

        while (!feof($this->stream)) {
            $pos = ftell($this->stream);
            $line = stream_get_line($this->stream, $this->bufferSize, $this->lineEnding);
            $ended = (bool) (ftell($this->stream) - strlen($line) - $pos);

            $byteLen = strlen($line);
            for ($i = 0; $i < $byteLen; $i++) {
                $this->listener->file_position($this->lineNumber, $this->charNumber);
                $this->consumeChar($line[$i]);
                $this->charNumber++;
            }

            if ($ended) {
                $this->lineNumber++;
                $this->charNumber = 1;
            }

        }
    }

    /**
     * @param  string       $c
     * @throws ParsingError
     */
    private function consumeChar($c)
    {
        // valid whitespace characters in JSON (from RFC4627 for JSON) include:
        // space, horizontal tab, line feed or new line, and carriage return.
        // thanks: http://stackoverflow.com/questions/16042274/definition-of-whitespace-in-json
        if (($c === " " || $c === "\t" || $c === "\n" || $c === "\r") &&
            !($this->state === self::STATE_IN_STRING ||
                $this->state === self::STATE_UNICODE ||
                $this->state === self::STATE_START_ESCAPE ||
                $this->state === self::STATE_IN_NUMBER ||
                $this->state === self::STATE_START_DOCUMENT)) {
            return;
        }

        switch ($this->state) {

            case self::STATE_START_DOCUMENT:
                $this->listener->start_document();
                if ($c === '[') {
                    $this->startArray();
                } elseif ($c === '{') {
                    $this->startObject();
                } else {
                    throw new ParsingError($this->lineNumber, $this->charNumber,
                        "Document must start with object or array.");
                }
                break;

            case self::STATE_IN_ARRAY:
                if ($c === ']') {
                    $this->endArray();
                } else {
                    $this->startValue($c);
                }
                break;

            case self::STATE_IN_OBJECT:
                if ($c === '}') {
                    $this->endObject();
                } elseif ($c === '"') {
                    $this->startKey();
                } else {
                    throw new ParsingError($this->lineNumber, $this->charNumber,
                        "Start of string expected for object key. Instead got: ".$c);
                }
                break;

            case self::STATE_END_KEY:
                if ($c !== ':') {
                    throw new ParsingError($this->lineNumber, $this->charNumber,
                        "Expected ':' after key.");
                }
                $this->state = self::STATE_AFTER_KEY;
                break;

            case self::STATE_AFTER_KEY:
                $this->startValue($c);
                break;

            case self::STATE_IN_STRING:
                if ($c === '"') {
                    $this->endString();
                } elseif ($c === '\\') {
                    $this->state = self::STATE_START_ESCAPE;
                } elseif (($c < "\x1f") || ($c === "\x7f")) {
                    throw new ParsingError($this->lineNumber, $this->charNumber,
                        "Unescaped control character encountered: " . $c);
                } else {
                    $this->buffer .= $c;
                }
                break;

            case self::STATE_START_ESCAPE:
                $this->processEscapeCharacter($c);
                break;

            case self::STATE_UNICODE:
                $this->processUnicodeCharacter($c);
                break;

            case self::STATE_AFTER_VALUE:
                $within = end($this->stack);
                if ($within === self::STACK_OBJECT) {
                    if ($c === '}') {
                        $this->endObject();
                    } elseif ($c === ',') {
                        $this->state = self::STATE_IN_OBJECT;
                    } else {
                        throw new ParsingError($this->lineNumber, $this->charNumber,
                            "Expected ',' or '}' while parsing object. Got: ".$c);
                    }
                } elseif ($within === self::STACK_ARRAY) {
                    if ($c === ']') {
                        $this->endArray();
                    } elseif ($c === ',') {
                        $this->state = self::STATE_IN_ARRAY;
                    } else {
                        throw new ParsingError($this->lineNumber, $this->charNumber,
                            "Expected ',' or ']' while parsing array. Got: ".$c);
                    }
                } else {
                    throw new ParsingError($this->lineNumber, $this->charNumber,
                        "Finished a literal, but unclear what state to move to. Last state: ".$within);
                }
                break;

            case self::STATE_IN_NUMBER:
                if (preg_match('/\d/', $c)) {
                    $this->buffer .= $c;
                } elseif ($c === '.') {
                    if (strpos($this->buffer, '.') !== false) {
                        throw new ParsingError($this->lineNumber, $this->charNumber,
                            "Cannot have multiple decimal points in a number.");
                    } elseif (stripos($this->buffer, 'e') !== false) {
                        throw new ParsingError($this->lineNumber, $this->charNumber,
                            "Cannot have a decimal point in an exponent.");
                    }
                    $this->buffer .= $c;
                } elseif ($c === 'e' || $c === 'E') {
                    if (stripos($this->buffer, 'e') !== false) {
                        throw new ParsingError($this->lineNumber, $this->charNumber,
                            "Cannot have multiple exponents in a number.");
                    }
                    $this->buffer .= $c;
                } elseif ($c === '+' || $c === '-') {
                    $last = mb_substr($this->buffer, -1);
                    if (!($last === 'e' || $last === 'E')) {
                        throw new ParsingError($this->lineNumber, $this->charNumber,
                            "Can only have '+' or '-' after the 'e' or 'E' in a number.");
                    }
                    $this->buffer .= $c;
                } else {
                    $this->endNumber();
                    // we have consumed one beyond the end of the number
                    $this->consumeChar($c);
                }
                break;

            case self::STATE_IN_TRUE:
                $this->buffer .= $c;
                if (mb_strlen($this->buffer) === 4) {
                    $this->endTrue();
                }
                break;

            case self::STATE_IN_FALSE:
                $this->buffer .= $c;
                if (mb_strlen($this->buffer) === 5) {
                    $this->endFalse();
                }
                break;

            case self::STATE_IN_NULL:
                $this->buffer .= $c;
                if (mb_strlen($this->buffer) === 4) {
                    $this->endNull();
                }
                break;

            case self::STATE_DONE:
                throw new ParsingError($this->lineNumber, $this->charNumber,
                    "Expected end of document.");

            default:
                throw new ParsingError($this->lineNumber, $this->charNumber,
                    "Internal error. Reached an unknown state: ".$this->state);
        }
    }

    /**
     * @param $c
     * @return int
     */
    private function isHexCharacter($c)
    {
        return preg_match('/[0-9a-fA-F]/u', $c);
    }

    /**
     * @param $num
     * @return string
     *
     * Thanks: http://stackoverflow.com/questions/1805802/php-convert-unicode-codepoint-to-utf-8
     */
    private function convertCodepointToCharacter($num)
    {
        if($num<=0x7F)       return chr($num);
        if($num<=0x7FF)      return chr(($num>>6)+192)  . chr(($num&63)+128);
        if($num<=0xFFFF)     return chr(($num>>12)+224) . chr((($num>>6)&63)+128)  . chr(($num&63)+128);
        if($num<=0x1FFFFF)   return chr(($num>>18)+240) . chr((($num>>12)&63)+128) .
                                    chr((($num>>6)&63)+128) . chr(($num&63)+128);

        return '';
    }

    /**
     * @param $c
     * @return int
     */
    private function isDigit($c)
    {
        // Only concerned with the first character in a number.
        return preg_match('/[0-9]|-/u', $c);
    }

    /**
     * @param $c
     * @throws JsonParser\ParsingError
     */
    private function startValue($c)
    {
        if ($c === '[') {
            $this->startArray();
        } elseif ($c === '{') {
            $this->startObject();
        } elseif ($c === '"') {
            $this->startString();
        } elseif ($this->isDigit($c)) {
            $this->startNumber($c);
        } elseif ($c === 't') {
            $this->state = self::STATE_IN_TRUE;
            $this->buffer .= $c;
        } elseif ($c === 'f') {
            $this->state = self::STATE_IN_FALSE;
            $this->buffer .= $c;
        } elseif ($c === 'n') {
            $this->state = self::STATE_IN_NULL;
            $this->buffer .= $c;
        } else {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Unexpected character for value: " . $c);
        }
    }

    /**
     *
     */
    private function startArray()
    {
        $this->listener->start_array();
        $this->state = self::STATE_IN_ARRAY;
        array_push($this->stack, self::STACK_ARRAY);
    }

    /**
     * @throws JsonParser\ParsingError
     */
    private function endArray()
    {
        $popped = array_pop($this->stack);
        if ($popped !== self::STACK_ARRAY) {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Unexpected end of array encountered.");
        }
        $this->listener->end_array();
        $this->state = self::STATE_AFTER_VALUE;

        if (empty($this->stack)) {
            $this->endDocument();
        }
    }

    /**
     *
     */
    private function startObject()
    {
        $this->listener->start_object();
        $this->state = self::STATE_IN_OBJECT;
        array_push($this->stack, self::STACK_OBJECT);
    }

    /**
     * @throws JsonParser\ParsingError
     */
    private function endObject()
    {
        $popped = array_pop($this->stack);
        if ($popped !== self::STACK_OBJECT) {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Unexpected end of object encountered.");
        }
        $this->listener->end_object();
        $this->state = self::STATE_AFTER_VALUE;

        if (empty($this->stack)) {
            $this->endDocument();
        }
    }

    /**
     *
     */
    private function startString()
    {
        array_push($this->stack, self::STACK_STRING);
        $this->state = self::STATE_IN_STRING;
    }

    /**
     *
     */
    private function startKey()
    {
        array_push($this->stack, self::STACK_KEY);
        $this->state = self::STATE_IN_STRING;
    }

    /**
     * @throws JsonParser\ParsingError
     */
    private function endString()
    {
        $popped = array_pop($this->stack);
        if ($popped === self::STACK_KEY) {
            $this->listener->key($this->buffer);
            $this->state = self::STATE_END_KEY;
        } elseif ($popped === self::STACK_STRING) {
            $this->listener->value($this->buffer);
            $this->state = self::STATE_AFTER_VALUE;
        } else {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Unexpected end of string.");
        }
        $this->buffer = '';
    }

    /**
     * @param $c
     * @throws JsonParser\ParsingError
     */
    private function processEscapeCharacter($c)
    {
        if ($c === '"') {
            $this->buffer .= '"';
        } elseif ($c === '\\') {
            $this->buffer .= '\\';
        } elseif ($c === '/') {
            $this->buffer .= '/';
        } elseif ($c === 'b') {
            $this->buffer .= "\x08";
        } elseif ($c === 'f') {
            $this->buffer .= "\f";
        } elseif ($c === 'n') {
            $this->buffer .= "\n";
        } elseif ($c === 'r') {
            $this->buffer .= "\r";
        } elseif ($c === 't') {
            $this->buffer .= "\t";
        } elseif ($c === 'u') {
            $this->state = self::STATE_UNICODE;
        } else {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Expected escaped character after backslash. Got: ".$c);
        }

        if ($this->state !== self::STATE_UNICODE) {
            $this->state = self::STATE_IN_STRING;
        }
    }

    /**
     * @param $c
     * @throws JsonParser\ParsingError
     */
    private function processUnicodeCharacter($c)
    {
        if (!$this->isHexCharacter($c)) {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Expected hex character for escaped unicode character. Unicode parsed: " . implode($this->unicodeBuffer) . " and got: ".$c);
        }
        array_push($this->unicodeBuffer, $c);
        if (count($this->unicodeBuffer) === 4) {
            $codepoint = hexdec(implode($this->unicodeBuffer));

            if ($codepoint >= 0xD800 && $codepoint < 0xDC00) {
                $this->unicodeHighCodePoint = $codepoint;
                $this->unicodeBuffer = array();
            } elseif ($codepoint >= 0xDC00 && $codepoint <= 0xDFFF) {
                if ($this->unicodeHighCodePoint === -1) {
                    throw new ParsingError($this->lineNumber, $this->charNumber,
                        "Missing high codepoint for unicode low codepoint.");
                }
                $combined_codepoint = (($this->unicodeHighCodePoint - 0xD800) * 0x400) + ($codepoint - 0xDC00) + 0x10000;

                $this->endUnicodeCharacter($combined_codepoint);
            } else {
                $this->endUnicodeCharacter($codepoint);
            }
        }
    }

    /**
     * @param $codepoint
     */
    private function endUnicodeCharacter($codepoint)
    {
        $this->buffer .= $this->convertCodepointToCharacter($codepoint);
        $this->unicodeBuffer = array();
        $this->unicodeHighCodePoint = -1;
        $this->state = self::STATE_IN_STRING;
    }

    /**
     * @param $c
     */
    private function startNumber($c)
    {
        $this->state = self::STATE_IN_NUMBER;
        $this->buffer .= $c;
    }

    /**
     *
     */
    private function endNumber()
    {
        $num = $this->buffer;
        if (preg_match('/\./', $num)) {
            $num = (float) ($num);
        } else {
            $num = (int) ($num);
        }
        $this->listener->value($num);

        $this->buffer = '';
        $this->state = self::STATE_AFTER_VALUE;
    }

    /**
     * @throws JsonParser\ParsingError
     */
    private function endTrue()
    {
        $true = $this->buffer;
        if ($true === 'true') {
            $this->listener->value(true);
        } else {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Expected 'true'. Got: ".$true);
        }
        $this->buffer = '';
        $this->state = self::STATE_AFTER_VALUE;
    }

    /**
     * @throws JsonParser\ParsingError
     */
    private function endFalse()
    {
        $false = $this->buffer;
        if ($false === 'false') {
            $this->listener->value(false);
        } else {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Expected 'false'. Got: ".$false);
        }
        $this->buffer = '';
        $this->state = self::STATE_AFTER_VALUE;
    }

    /**
     * @throws JsonParser\ParsingError
     */
    private function endNull()
    {
        $null = $this->buffer;
        if ($null === 'null') {
            $this->listener->value(null);
        } else {
            throw new ParsingError($this->lineNumber, $this->charNumber,
                "Expected 'null'. Got: ".$null);
        }
        $this->buffer = '';
        $this->state = self::STATE_AFTER_VALUE;
    }

    /**
     *
     */
    private function endDocument()
    {
        $this->listener->end_document();
        $this->state = self::STATE_DONE;
    }

}
