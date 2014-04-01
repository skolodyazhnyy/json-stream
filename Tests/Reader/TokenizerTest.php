<?php
/**
 *
 * This file is part of the JSON Stream Project.
 *
 * @author Sergey Kolodyazhnyy <sergey.kolodyazhnyy@gmail.com>
 *
 */

namespace Bcn\Component\Json\Tests\Reader;

use Bcn\Component\Json\Reader\Tokenizer;
use Bcn\Component\StreamWrapper\Stream;
use Symfony\Component\Yaml\Yaml;

class TokenizerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @param string $content
     * @param array  $tokens
     *
     * @dataProvider provideTokens
     */
    public function testTokenizer($content, array $tokens)
    {
        $resource = fopen(new Stream($content), "r");

        $reader = new Tokenizer($resource);
        foreach ($tokens as $token) {
            $token['token'] = $this->toTokenCode($token['token']);
            $this->assertEquals($token, $reader->next());
        }

        fclose($resource);
    }

    protected function toTokenCode($code)
    {
        switch ($code) {
            case 'scalar':       return Tokenizer::TOKEN_SCALAR;
            case 'array_start':  return Tokenizer::TOKEN_ARRAY_START;
            case 'object_start': return Tokenizer::TOKEN_OBJECT_START;
            case 'array_end':    return Tokenizer::TOKEN_ARRAY_END;
            case 'object_end':   return Tokenizer::TOKEN_OBJECT_END;
        }

        return $code;
    }

    /**
     * @return array
     */
    public function provideTokens()
    {
        return Yaml::parse(__DIR__ . DIRECTORY_SEPARATOR . "fixtures" . DIRECTORY_SEPARATOR . "tokenizer.yml");
    }

}
