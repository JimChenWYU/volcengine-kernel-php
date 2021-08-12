<?php

namespace Volcengine\Tests\Kernel;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use Monolog\Logger;
use Volcengine\Kernel\BaseClient;
use Volcengine\Kernel\Http\Response;
use Volcengine\Kernel\ServiceContainer;
use Volcengine\Kernel\Support\Collection;
use Volcengine\Tests\ServiceContainerImpl;
use Volcengine\Tests\TestCase;

class BaseClientTest extends TestCase
{
    public function makeClient($methods = [], ServiceContainer $app = null)
    {
        $methods = !empty($methods) ? \sprintf('[%s]', implode(',', (array) $methods)) : '';
        $app = $app ?? \Mockery::mock(ServiceContainer::class);

        return \Mockery::mock(BaseClient::class."{$methods}", [$app])->makePartial();
    }

    public function testHttpGet()
    {
        $client = $this->makeClient('request');
        $url = 'https://open.volcengineapi.com';
        $query = ['foo' => 'bar'];
        $client->expects()->request($url, 'GET', ['query' => $query])->andReturn('mock-result');
        $this->assertSame('mock-result', $client->httpGet($url, $query));
    }

    public function testHttpPost()
    {
        $client = $this->makeClient('request');
        $url = 'https://open.volcengineapi.com';

        $data = ['foo' => 'bar'];
        $client->expects()->request($url, 'POST', ['form_params' => $data])->andReturn('mock-result');
        $this->assertSame('mock-result', $client->httpPost($url, $data));
    }

    public function testHttpPostJson()
    {
        $client = $this->makeClient('request');
        $url = 'https://open.volcengineapi.com';

        $data = ['foo' => 'bar'];
        $query = ['appid' => 1234];
        $client->expects()->request($url, 'POST', ['query' => $query, 'json' => $data])->andReturn('mock-result');
        $this->assertSame('mock-result', $client->httpPostJson($url, $data, $query));
    }

    public function testRequest()
    {
        $url = 'https://open.volcengineapi.com';
        $app = new ServiceContainerImpl([
            'response_type' => 'array',
        ]);
        $client = $this->makeClient(['registerHttpMiddlewares', 'performRequest'], $app)
            ->shouldAllowMockingProtectedMethods();

        // default value
        $client->expects()->registerHttpMiddlewares();
        $client->expects()->performRequest($url, 'GET', [])->andReturn(new Response(200, [], '{"mock":"result"}'));
        $this->assertEquals(new Collection(['mock' => 'result']), $client->request($url));

        // return raw with custom arguments
        $options = ['foo' => 'bar'];
        $response = new Response(200, [], '{"mock":"result"}');
        $client->expects()->registerHttpMiddlewares();
        $client->expects()->performRequest($url, 'POST', $options)->andReturn($response);
        $this->assertSame($response, $client->request($url, 'POST', $options, true));
    }

    public function testHttpClient()
    {
        // default
        $app = new ServiceContainerImpl();
        $client = $this->makeClient('request', $app);
        $this->assertInstanceOf(Client::class, $client->getHttpClient());

        // custom client
        $http = new Client(['base_uri' => 'https://open.volcengineapi.com']);
        $app = new ServiceContainerImpl([], [
            'http_client' => $http,
        ]);

        $client = $this->makeClient('request', $app);
        $this->assertSame($http, $client->getHttpClient());
    }

    public function testRegisterMiddlewares()
    {
        $client = $this->makeClient(['retryMiddleware', 'logMiddleware', 'pushMiddleware'])
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();
        $retryMiddleware = function () {
            return 'retry';
        };
        $logMiddleware = function () {
            return 'log';
        };
        $client->expects()->retryMiddleware()->andReturn($retryMiddleware);
        $client->expects()->logMiddleware()->andReturn($logMiddleware);
        $client->expects()->pushMiddleware($retryMiddleware, 'retry');
        $client->expects()->pushMiddleware($logMiddleware, 'log');

        $client->registerHttpMiddlewares();

        $this->expectNotToPerformAssertions();
    }

    public function testLogMiddleware()
    {
        $app = new ServiceContainerImpl([
            'http' => [
                'log_template',
            ],
        ]);
        $app['logger'] = new Logger('logger');
        $client = $this->makeClient([], $app)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $this->assertInstanceOf('Closure', $client->logMiddleware());
    }

    public function testRetryMiddleware()
    {
        // no retries configured
        $app = new ServiceContainerImpl([]);
        $app['logger'] = $logger = \Mockery::mock('stdClass');
        $client = $this->makeClient(['retryMiddleware'], $app)
            ->shouldAllowMockingProtectedMethods()
            ->makePartial();

        $func = $client->retryMiddleware();

        // default once with right response
        $logger->expects()->debug('Retrying with `SystemError`.');
        $handler = new MockHandler([
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"SystemError"}}}'),
            new Response(200, [], '{"success": true}'),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push($func, 'retry');
        $c = new Client(['handler' => $stack]);
        $p = $c->requestAsync('GET', 'https://open.volcengineapi.com', []);
        $response = $p->wait();
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"success": true}', $response->getBody()->getContents());

        // default once with error response
        $logger->expects()->debug('Retrying with `SystemError`.');
        $handler = new MockHandler([
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"SystemError"}}}'),
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"InternalError.UnknownInternalError"}}}'),
            new Response(200, [], '{"success": true}'),
        ]);
        $handler = $func($handler);
        $c = new Client(['handler' => $handler]);
        $p = $c->sendAsync(new Request('GET', 'https://open.volcengineapi.com'), []);
        $response = $p->wait();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"ResponseMetadata":{"Error":{"Code":"InternalError.UnknownInternalError"}}}', $response->getBody()->getContents());

        // default once with configured retries
        $app['config']['http'] = ['max_retries' => 0];
        $logger->expects()->debug('Retrying with `SystemError`.')->never();
        $handler = new MockHandler([
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"SystemError"}}}'),
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"InternalError.UnknownInternalError"}}}'),
            new Response(200, [], '{"success": true}'),
        ]);
        $handler = $func($handler);
        $c = new Client(['handler' => $handler]);
        $p = $c->sendAsync(new Request('GET', 'https://open.volcengineapi.com'), []);
        $response = $p->wait();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"ResponseMetadata":{"Error":{"Code":"SystemError"}}}', $response->getBody()->getContents());

        // 3 times
        $app['config']['http'] = [
            'max_retries' => 3,
            'retry_delay' => 1,
        ];
        $logger->expects()->debug('Retrying with `InternalError.UnknownInternalError`.');
        $logger->expects()->debug('Retrying with `SystemError`.')->times(2);
        $handler = new MockHandler([
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"SystemError"}}}'),
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"InternalError.UnknownInternalError"}}}'),
            new Response(200, [], '{"ResponseMetadata":{"Error":{"Code":"SystemError"}}}'),
            new Response(200, [], '{"success":true}'),
        ]);
        $handler = $func($handler);
        $c = new Client(['handler' => $handler]);
        $s = microtime(true);
        $p = $c->sendAsync(new Request('GET', 'https://open.volcengineapi.com'), []);
        $response = $p->wait();

        $this->assertTrue(microtime(true) - $s >= 3 * ($app['config']['http']['retry_delay'] / 1000), 'delay time'); // times * delay
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"success":true}', $response->getBody()->getContents());
    }
}
