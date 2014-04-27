<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json;

use Bcn\Component\Json\Exception\ReadingError;
use Bcn\Component\Json\Reader\Tokenizer;

class Reader
{

    const TYPE_OBJECT = 1;
    const TYPE_ARRAY  = 2;

    /** @var \Bcn\Component\Json\Reader\Tokenizer */
    protected $tokenizer;

    /** @var array */
    protected $token;

    /**
     * @param resource $resource
     */
    public function __construct($resource)
    {
        $this->tokenizer = new Tokenizer($resource);
        $this->token = $this->tokenizer->next();
    }

    /**
     * @param null|string $key
     * @param null|string $type
     * @return bool
     */
    public function enter($key = null, $type = null)
    {
        if ($type === null && in_array($key, array(self::TYPE_OBJECT, self::TYPE_ARRAY))) {
            $type = $key;
            $key  = null;
        }

        switch ($type) {
            case self::TYPE_ARRAY:
                $tokens = array(Tokenizer::TOKEN_ARRAY_START);
                break;
            case self::TYPE_OBJECT:
                $tokens = array(Tokenizer::TOKEN_OBJECT_START);
                break;
            default:
                $tokens = array(Tokenizer::TOKEN_ARRAY_START, Tokenizer::TOKEN_OBJECT_START);
        }

        if ($this->token['key'] != $key || !in_array($this->token['token'], $tokens)) {
            return false;
        }

        $this->next();

        return true;
    }

    /**
     * @return bool
     */
    public function leave()
    {
        if (!($context = $this->tokenizer->context())) {
            return false;
        }

        $level = 0;
        do {
            switch ($this->token['token']) {
                case Tokenizer::TOKEN_ARRAY_START:
                case Tokenizer::TOKEN_OBJECT_START:
                    $level++;
                    break;
                case Tokenizer::TOKEN_ARRAY_END:
                case Tokenizer::TOKEN_OBJECT_END:
                    $level--;
                    break;
            }

        } while ($this->next() && $level >= 0 && $this->tokenizer->context());

        return true;
    }

    /**
     * @param string|null $key
     * @return mixed
     */
    public function read($key = null)
    {
        if ($this->token['key'] != $key) {
            return false;
        }

        switch ($this->token['token']) {
            case Tokenizer::TOKEN_SCALAR:
                $value = $this->token['content'];
                $this->next();

                return $value;
            case Tokenizer::TOKEN_ARRAY_START:
                $items = array();
                $this->enter($this->token['key']);
                while ($this->token['token'] != Tokenizer::TOKEN_ARRAY_END) {
                    $items[] = $this->read();

                    if (!$this->token) {
                        throw new ReadingError("Unexpected object ending");
                    }
                }
                $this->leave();

                return $items;
            case Tokenizer::TOKEN_OBJECT_START:
                $items = array();
                $this->enter($this->token['key']);
                while ($this->token['token'] != Tokenizer::TOKEN_OBJECT_END) {
                    $items[$this->token['key']] = $this->read($this->token['key']);

                    if (!$this->token) {
                        throw new ReadingError("Unexpected object ending");
                    }
                }
                $this->leave();

                return $items;
        }

        return null;
    }

    /**
     *
     */
    protected function next()
    {
        return $this->token = $this->tokenizer->next();
    }

}
