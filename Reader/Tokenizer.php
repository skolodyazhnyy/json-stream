<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <skolodyazhnyy@ebay.com>
 *
 */

namespace Bcn\Component\Json\Reader;

use Bcn\Component\Json\Exception\ReadingError;

class Tokenizer
{

    const CONTEXT_OBJECT = 1;
    const CONTEXT_ARRAY  = 2;

    const TOKEN_OBJECT_START   = 1;
    const TOKEN_OBJECT_END     = 2;
    const TOKEN_ARRAY_START    = 4;
    const TOKEN_ARRAY_END      = 8;
    const TOKEN_SCALAR         = 16;
    const TOKEN_KEY            = 32;
    const TOKEN_ITEM_SEPARATOR = 64;

    const EXPECTED_ANY         = 127;
    const EXPECTED_ARRAY_ITEM  = 29;  // Object Start, Array Start, Scalar, Array End
    const EXPECTED_OBJECT_ITEM = 23;  // Object Start, Array Start, Scalar, Object End
    const EXPECTED_SEPARATOR   = 64;
    const EXPECTED_KEY         = 32;
    const EXPECTED_ARRAY_END   = 8;
    const EXPECTED_OBJECT_END  = 2;

    /** @var resource */
    protected $stream;

    /** @var array */
    protected $context  = array();
    protected $expected;

    /** @var array */
    protected $token = array();

    /** @var array */
    protected $buffered = array();

    /** @var array */
    protected $first = array();

    /**
     * @param  resource                  $stream
     * @throws \InvalidArgumentException
     */
    public function __construct($stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
            throw new \InvalidArgumentException("Argument is not a stream");
        }

        $this->stream = $stream;
        $this->expected = self::EXPECTED_ANY;
    }

    /**
     * @return array
     * @throws ReadingError
     */
    public function next()
    {
        $this->token = $this->fetch();

        if (!$this->token['token']) {
            return false;
        }

        if (!($this->token['token'] & $this->expected)) {
            throw new ReadingError(sprintf("Read unexpected token %s/%s", $this->token['token'], $this->expected));
        }

        switch ($this->token['token']) {
            case self::TOKEN_ARRAY_START:
                $this->context[] = self::CONTEXT_ARRAY;
                $this->expected  = self::EXPECTED_ARRAY_ITEM;
                break;
            case self::TOKEN_OBJECT_START:
                $this->context[] = self::CONTEXT_OBJECT;
                $this->expected  = self::EXPECTED_OBJECT_ITEM;
                break;
            case self::TOKEN_OBJECT_END:
            case self::TOKEN_ARRAY_END:
                array_pop($this->context);
            // no break;
            case self::TOKEN_SCALAR:
                if ($this->context()) {
                    $this->expected  = self::EXPECTED_SEPARATOR;
                    $this->expected |= $this->context() == self::CONTEXT_ARRAY ?
                        self::EXPECTED_ARRAY_END :self::EXPECTED_OBJECT_END;
                } else {
                    $this->expected = 0;
                }
                break;
            case self::TOKEN_ITEM_SEPARATOR:
                $this->expected = $this->context() == self::CONTEXT_ARRAY ?
                    self::EXPECTED_ARRAY_ITEM : self::EXPECTED_OBJECT_ITEM;

                return $this->next();

        }

        return $this->token;
    }

    /**
     * @return array
     * @throws ReadingError
     */
    protected function fetch()
    {
        if ($this->context() == self::CONTEXT_OBJECT) {
            list($token, $key) = $this->readKey();
            if ($token != self::TOKEN_KEY) {
                return array(
                    'key'     => null,
                    'token'   => $token,
                    'content' => null
                );
            }
        } else {
            $key = null;
        }

        list($token, $content) = $this->readValue();

        return array(
            'key'     => $key,
            'token'   => $token,
            'content' => $content
        );
    }

    /**
     * @throws ReadingError
     */
    protected function readKey()
    {
        list($token, $key) = $this->readKeyToken();

        if ($token == self::TOKEN_KEY) {
            $char = $this->findSymbol();
            if ($char != ":") {
                throw new ReadingError(sprintf("Expecting key-value separator, got \"%s\"", $char));
            }
        }

        return array($token, $key);
    }

    /**
     * @return array
     */
    protected function readKeyToken()
    {
        $char = $this->findSymbol();

        switch ($char) {
            case "}":
                return array(self::TOKEN_OBJECT_END,     null);
            case "]":
                return array(self::TOKEN_ARRAY_END,      null);
            case ",":
                return array(self::TOKEN_ITEM_SEPARATOR, null);
            case "\"":
                return array(self::TOKEN_KEY, $this->completeStringReading($char));
        }

        return array(null, null);
    }

    /**
     * @return array
     * @throws ReadingError
     */
    protected function readValue()
    {
        $char = $this->findSymbol();

        if ($char === "" || $char === false) {
            return array(null, null);
        }

        switch ($char) {
            case "{":
                return array(self::TOKEN_OBJECT_START,   null);
            case "}":
                return array(self::TOKEN_OBJECT_END,     null);
            case "[":
                return array(self::TOKEN_ARRAY_START,    null);
            case "]":
                return array(self::TOKEN_ARRAY_END,      null);
            case ",":
                return array(self::TOKEN_ITEM_SEPARATOR, null);
            case "\"":
                return array(self::TOKEN_SCALAR, $this->completeStringReading($char));
            default:
                return array(self::TOKEN_SCALAR, $this->completeScalarReading($char));
        }

    }

    /**
     * @param $char
     * @throws ReadingError
     * @return string
     */
    protected function completeStringReading($char)
    {
        $quotes  = $char;
        $buffer  = "";
        $escaped = false;

        while (true) {
            $char = $this->readSymbol();
            if ($char === false || $char === "") {
                break;
            }
            if ($quotes == $char && !$escaped) {
                return json_decode($quotes . $buffer . $quotes);
            }
            $buffer .= $char;
            $escaped = $quotes === "\"" && $char == "\\";
        }

        throw new ReadingError("String not terminated correctly " . ftell($this->stream));
    }

    /**
     * @param $char
     * @return string
     * @throws ReadingError
     */
    protected function completeScalarReading($char)
    {
        $buffer = $char;

        while (true) {
            $char = $this->readSymbol();
            if ($char === "" || $char === false || strpos(",}] \t\n\r", $char) !== false) {
                if ($char && strpos(",}]", $char) !== false) {
                    $this->buffered[] = $char;
                }
                break;
            }
            $buffer .= $char;
        }

        switch ($buffer) {
            case "true":
                return true;
            case "false":
                return false;
            case "null":
                return null;
        }

        if (!preg_match("/^-?[0-9]*(\.[0-9]+)?(e[0-9]+)?$/", $buffer)) {
            throw new ReadingError(sprintf("Scalar value \"%s\" is invalid", $buffer));
        }

        return floatval($buffer);
    }

    /**
     * @return string
     */
    protected function findSymbol()
    {
        while (($char = $this->readSymbol()) && strpos(" \n\r\t", $char) !== false);

        return $char;
    }

    /**
     * @return string
     */
    protected function readSymbol()
    {
        if ($this->buffered) {
            return array_pop($this->buffered);
        }

        return fread($this->stream, 1);
    }

    /**
     * @return mixed
     */
    public function context()
    {
        return end($this->context);
    }

}
