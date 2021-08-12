<?php

namespace Volcengine\Tests\Kernel\Http;

use Volcengine\Kernel\Http\Response;
use Volcengine\Kernel\Support\Collection;
use Volcengine\Tests\TestCase;

class ResponseTest extends TestCase
{
    public function testBasicFeatures()
    {
        $response = new Response(200, ['content-type:application/json'], '{"name": "volcengine"}');

        $this->assertInstanceOf(\GuzzleHttp\Psr7\Response::class, $response);

        $this->assertSame('{"name": "volcengine"}', (string) $response);
        $this->assertSame('{"name": "volcengine"}', $response->getBodyContents());
        $this->assertSame('{"name":"volcengine"}', $response->toJson());
        $this->assertSame(['name' => 'volcengine'], $response->toArray());
        $this->assertSame('volcengine', $response->toObject()->name);
        $this->assertInstanceOf(Collection::class, $response->toCollection());
        $this->assertSame(['name' => 'volcengine'], $response->toCollection()->all());
    }

    public function testInvalidArrayableContents()
    {
        $response = new Response(200, [], 'not json string');

        $this->assertInstanceOf(\GuzzleHttp\Psr7\Response::class, $response);

        $this->assertSame([], $response->toArray());

        // #1291
        $json = "{\"name\":\"小明\x09了死烧部全们你把并\"}";
        \json_decode($json, true);
        $this->assertSame(\JSON_ERROR_CTRL_CHAR, \json_last_error());

        $response = new Response(200, ['Content-Type' => ['application/json']], $json);
        $this->assertInstanceOf(\GuzzleHttp\Psr7\Response::class, $response);
        $this->assertSame(['name' => '小明了死烧部全们你把并'], $response->toArray());
    }
}
