<?php

namespace Bcn\Component\Json;

/**
 * Class Writer
 * @package Bcn\Component\Json
 */
class Writer
{

    const CONTEXT_NONE         = 0;
    const CONTEXT_ARRAY        = 1;
    const CONTEXT_ARRAY_START  = 2;
    const CONTEXT_OBJECT       = 3;
    const CONTEXT_OBJECT_START = 4;

    protected $stream;
    protected $context;

    protected $parents = array();

    /**
     * @param resource $stream A stream resource.
     * @throws \InvalidArgumentException If $stream is not a stream resource.
     */
    public function __construct($stream)
    {
        if (!is_resource($stream) || get_resource_type($stream) != 'stream') {
            throw new \InvalidArgumentException("Resource is not a stream");
        }

        $this->stream  = $stream;
        $this->context = self::CONTEXT_NONE;
    }

    /**
     * @param $value
     * @param null $type
     * @return $this
     */
    public function insert($value, $type = null)
    {
        if ($this->context == self::CONTEXT_ARRAY) {
            $this->write(',');
        } elseif ($this->context == self::CONTEXT_ARRAY_START) {
            $this->write("[");
            $this->context = self::CONTEXT_ARRAY;
        }

        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        switch ($type ? : $this->getType($value)) {
            case "null":
                $this->write("null");
                break;
            case "bool":
                $this->write($value ? 'true' : 'false');
                break;
            case "scalar":
                $this->scalar($value);
                break;
            case "array":
                $this->encodeArray($value);
                break;
            case "object":
                $this->encodeObject($value);
                break;
        }

        $this->flush();

        return $this;
    }

    /**
     * @param $key
     * @return $this
     */
    public function key($key)
    {
        if ($this->context == self::CONTEXT_OBJECT) {
            $this->write(',');
        } elseif ($this->context == self::CONTEXT_OBJECT_START) {
            $this->write("{");
            $this->context = self::CONTEXT_OBJECT;
        }

        $this->scalar((string) $key);
        $this->write(":");

        return $this;
    }

    /**
     * @param bool $isObject
     * @return $this
     */
    public function start($isObject = false)
    {
        if ($this->context == self::CONTEXT_ARRAY_START) {
            $this->write("[");
            $this->context = self::CONTEXT_ARRAY;
        } elseif ($this->context == self::CONTEXT_OBJECT_START) {
            $this->write("{");
            $this->context = self::CONTEXT_OBJECT;
        }

        array_push($this->parents, $this->context);
        $this->context = $isObject ? self::CONTEXT_OBJECT_START : self::CONTEXT_ARRAY_START;

        return $this;
    }

    /**
     * @return $this
     */
    public function object()
    {
        return $this->start(true);
    }

    /**
     * @return $this
     */
    public function end()
    {
        switch ($this->context) {
            case self::CONTEXT_OBJECT:
                $this->write("}");
                break;
            case self::CONTEXT_OBJECT_START:
                $this->write("{}");
                break;
            case self::CONTEXT_ARRAY:
                $this->write("]");
                break;
            case self::CONTEXT_ARRAY_START:
                $this->write("[]");
                break;
        }

        $this->context = array_pop($this->parents);

        return $this;
    }

    /**
     * @param $value
     * @return string
     */
    protected function getType($value)
    {
        if (is_bool($value)) {
            return "bool";
        } elseif (is_scalar($value)) {
            return "scalar";
        } elseif ((is_array($value) && !$this->isAssociative($value)) || (is_object($value) && $value instanceof \Traversable)) {
            return "array";
        } elseif (is_object($value) || is_array($value)) {
            return "object";
        }

        return "null";
    }

    /**
     * @param $value
     * @return $this
     */
    public function scalar($value)
    {
        $this->write(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));

        return $this;
    }

    /**
     * @param $value
     * @return bool
     */
    protected function isAssociative($value)
    {
        if (is_object($value) && $value instanceof \Traversable && !($value instanceof \ArrayAccess)) {
            return false;
        }

        if (!is_array($value)) {
            return true;
        }

        $keys = array_keys($value);
        sort($keys);

        return end($keys) !== count($keys) - 1;
    }

    /**
     * @param $array
     */
    protected function encodeArray($array)
    {
        $this->start(false);
        foreach ($array as $value) {
            $this->insert($value);
        }
        $this->end();
    }

    /**
     * @param $object
     */
    protected function encodeObject($object)
    {
        $this->start(true);
        foreach ($object as $key => $value) {
            $this->key((string) $key);
            $this->insert($value);
        }
        $this->end();
    }

    /**
     * @param $value
     */
    protected function write($value)
    {
        fwrite($this->stream, $value);
    }

    /**
     *
     */
    protected function flush()
    {
        fflush($this->stream);
    }

}
