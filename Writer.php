<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json;
use Bcn\Component\Json\Exception\WritingError;

/**
 * Class Writer
 * @package Bcn\Component\Json
 */
class Writer
{

    const TYPE_OBJECT = 1;
    const TYPE_ARRAY  = 2;
    const TYPE_NULL   = 3;
    const TYPE_BOOL   = 4;
    const TYPE_SCALAR = 5;

    const CONTEXT_NONE         = 0;
    const CONTEXT_ARRAY        = 1;
    const CONTEXT_ARRAY_START  = 2;
    const CONTEXT_OBJECT       = 3;
    const CONTEXT_OBJECT_START = 4;

    protected $stream;
    protected $context;

    protected $parents = array();

    /**
     * @param  resource                  $stream A stream resource.
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
     * @param $key
     * @param $value
     * @param null $type
     * @return $this
     * @throws Exception\WritingError
     */
    public function write($key, $value, $type = null)
    {
        if ($value instanceof \JsonSerializable) {
            $value = $value->jsonSerialize();
        }

        switch ($type ? : $this->getType($value)) {
            case self::TYPE_NULL:
                $this->prefix($key);
                $this->streamWrite('null');
                break;
            case self::TYPE_BOOL:
                $this->prefix($key);
                $this->streamWrite($value ? 'true' : 'false');
                break;
            case self::TYPE_SCALAR:
                $this->prefix($key);
                $this->scalar($value);
                break;
            case self::TYPE_ARRAY:
                $this->encodeArray($key, $value);
                break;
            case self::TYPE_OBJECT:
                $this->encodeObject($key, $value);
                break;
            default:
                throw new WritingError("Unrecognized type");
        }

        $this->streamFlush();

        return $this;
    }

    /**
     * @param string $key
     * @param int $type
     * @return $this
     */
    public function enter($key = null, $type = null)
    {
        if ($type === null) {
            if (in_array($key, array(self::TYPE_OBJECT, self::TYPE_ARRAY))) {
                $type = $key;
                $key  = null;
            } else {
                $type = self::TYPE_ARRAY;
            }
        }
        $this->prefix($key);

        array_push($this->parents, $this->context);
        $this->context = $type == self::TYPE_OBJECT ? self::CONTEXT_OBJECT_START : self::CONTEXT_ARRAY_START;

        return $this;
    }

    /**
     * @return $this
     */
    public function leave()
    {
        switch ($this->context) {
            case self::CONTEXT_OBJECT:
                $this->streamWrite("}");
                break;
            case self::CONTEXT_OBJECT_START:
                $this->streamWrite("{}");
                break;
            case self::CONTEXT_ARRAY:
                $this->streamWrite("]");
                break;
            case self::CONTEXT_ARRAY_START:
                $this->streamWrite("[]");
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
            return self::TYPE_BOOL;
        }
        if (is_scalar($value)) {
            return self::TYPE_SCALAR;
        }
        if (is_array($value)  && !$this->isAssociative($value)) {
            return self::TYPE_ARRAY;
        }
        if (is_object($value) && $value instanceof \Traversable) {
            return self::TYPE_ARRAY;
        }
        if (is_object($value) || is_array($value)) {
            return self::TYPE_OBJECT;
        }

        return self::TYPE_NULL;
    }

    /**
     * @param $value
     * @return $this
     */
    public function scalar($value)
    {
        $this->streamWrite(json_encode($value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT));

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
     * @param $key
     * @param $array
     */
    protected function encodeArray($key, $array)
    {
        $this->enter($key, self::TYPE_ARRAY);
        foreach ($array as $value) {
            $this->write(null, $value);
        }
        $this->leave();
    }

    /**
     * @param $key
     * @param $object
     */
    protected function encodeObject($key, $object)
    {
        $this->enter($key, self::TYPE_OBJECT);
        foreach ($object as $key => $value) {
            $this->write((string) $key, $value);
        }
        $this->leave();
    }

    /**
     * @param $value
     */
    protected function streamWrite($value)
    {
        fwrite($this->stream, $value);
    }

    /**
     *
     */
    protected function streamFlush()
    {
        fflush($this->stream);
    }

    /**
     * @param $key
     */
    protected function key($key)
    {
        $this->scalar((string) $key);
        $this->streamWrite(":");
    }

    /**
     * @param $key
     */
    protected function prefix($key)
    {
        switch ($this->context) {
            case self::CONTEXT_OBJECT_START:
                $this->streamWrite("{");
                $this->key($key);
                $this->context = self::CONTEXT_OBJECT;
                break;
            case self::CONTEXT_ARRAY_START:
                $this->streamWrite("[");
                $this->context = self::CONTEXT_ARRAY;
                break;
            case self::CONTEXT_OBJECT:
                $this->streamWrite(',');
                $this->key($key);
                break;
            case self::CONTEXT_ARRAY:
                $this->streamWrite(',');
                break;
        }
    }

}
