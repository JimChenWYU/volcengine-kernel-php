<?php

namespace Volcengine\Tests\Kernel\Traits;

use Volcengine\Kernel\Contracts\Arrayable;
use Volcengine\Kernel\Exceptions\InvalidArgumentException;
use Volcengine\Kernel\Exceptions\InvalidConfigException;
use Volcengine\Kernel\Http\Response;
use Volcengine\Kernel\Support\Collection;
use Volcengine\Kernel\Traits\ResponseCastable;

class ResponseCastableTest extends \Volcengine\Tests\TestCase
{
    public function testCastResponseToType()
    {
        $cls = \Mockery::mock(DummyClassForResponseCastable::class);

        $response = new Response(200, [], '{"foo": "bar"}');

        // collection
        $collection = $cls->castResponseToType($response, 'collection');
        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame(['foo' => 'bar'], $collection->all());

        // array
        $this->assertSame(['foo' => 'bar'], $cls->castResponseToType($response, 'array'));

        // object
        $this->assertSame('bar', $cls->castResponseToType($response, 'object')->foo);

        // raw
        $raw = $cls->castResponseToType($response, 'raw');
        $this->assertInstanceOf(Response::class, $raw);

        // custom class
        // 1. exists
        $dummyResponse = $cls->castResponseToType($response, DummyResponseClassForResponseCastableTest::class);
        $this->assertInstanceOf(DummyResponseClassForResponseCastableTest::class, $dummyResponse);
        $this->assertInstanceOf(Response::class, $dummyResponse->response);

        // 2. not exists
        $this->expectException(InvalidConfigException::class);
        $cls->castResponseToType($response, 'Not\Exists\ClassName');
        $this->fail('failed to assert castResponseToType should throw an exception.');
    }

    public function testDetectAndCastResponseToType()
    {
        $cls = \Mockery::mock(DummyClassForResponseCastable::class);

        // response
        $response = new Response(200, [], '{"foo": "bar"}');
        $this->assertInstanceOf(Collection::class, $cls->detectAndCastResponseToType($response, 'collection'));

        // array
        $response = ['foo' => 'bar'];
        $this->assertInstanceOf(Collection::class, $cls->detectAndCastResponseToType($response, 'collection'));
        $this->assertSame(['foo' => 'bar'], $cls->detectAndCastResponseToType($response, 'collection')->all());

        // object
        $response = json_decode(json_encode(['foo' => 'bar']));
        $this->assertSame(['foo' => 'bar'], $cls->detectAndCastResponseToType($response, 'array'));

        // string
        $this->assertSame([], $cls->detectAndCastResponseToType('foobar', 'array'));
        $this->assertSame('foobar', $cls->detectAndCastResponseToType('foobar', 'raw')->getBody()->getContents());

        // int
        $this->assertSame([123], $cls->detectAndCastResponseToType(123, 'array'));
        $this->assertSame('123', $cls->detectAndCastResponseToType(123, 'raw')->getBody()->getContents());

        // float
        $this->assertSame([123.01], $cls->detectAndCastResponseToType(123.01, 'array'));
        $this->assertSame('123.01', $cls->detectAndCastResponseToType(123.01, 'raw')->getBody()->getContents());

        // bool
        $this->assertSame([], $cls->detectAndCastResponseToType(false, 'array'));
        $this->assertSame('', $cls->detectAndCastResponseToType(false, 'raw')->getBody()->getContents());

        // custom response
        $response = new DummyClassForArrayableCast();
        $this->assertSame(['hello' => 'world!'], $cls->detectAndCastResponseToType($response, 'array'));

        // exception
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported response type "NULL"');
        $cls->detectAndCastResponseToType(null, 'array');
    }
}

class DummyClassForResponseCastable
{
    use ResponseCastable;
}

class DummyClassForArrayableCast implements Arrayable
{
    public function toArray()
    {
        return [
            'hello' => 'world!',
        ];
    }

    public function offsetExists($offset)
    {
        // TODO: Implement offsetExists() method.
    }

    public function offsetGet($offset)
    {
        // TODO: Implement offsetGet() method.
    }

    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }
}

class DummyResponseClassForResponseCastableTest implements Arrayable
{
    public $response;

    public function __construct($response)
    {
        $this->response = $response;
    }

    public function toArray()
    {
        // TODO: Implement toArray() method.
    }

    public function offsetExists($offset)
    {
        // TODO: Implement offsetExists() method.
    }

    public function offsetGet($offset)
    {
        // TODO: Implement offsetGet() method.
    }

    public function offsetSet($offset, $value)
    {
        // TODO: Implement offsetSet() method.
    }

    public function offsetUnset($offset)
    {
        // TODO: Implement offsetUnset() method.
    }
}
