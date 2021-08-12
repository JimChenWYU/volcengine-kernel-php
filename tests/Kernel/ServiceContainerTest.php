<?php

namespace Volcengine\Tests\Kernel;

use GuzzleHttp\Client;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Volcengine\Kernel\BaseClient;
use Volcengine\Kernel\Config;
use Volcengine\Kernel\Log\LogManager;
use Volcengine\Kernel\ServiceContainer;
use Volcengine\Tests\ServiceContainerImpl;
use Volcengine\Tests\TestCase;

class ServiceContainerTest extends TestCase
{
    public function testBasicFeatures()
    {
        $container = new ServiceContainerImpl();

        $this->assertNotEmpty($container->getProviders());

        // __set, __get, offsetGet
        $this->assertInstanceOf(Config::class, $container['config']);
        $this->assertInstanceOf(Config::class, $container->config);

        $this->assertInstanceOf(Client::class, $container['http_client']);
        $this->assertInstanceOf(Request::class, $container['request']);
        $this->assertInstanceOf(LogManager::class, $container['log']);
        $this->assertInstanceOf(LogManager::class, $container['logger']);

        $container['foo'] = 'foo';
        $container->bar = 'bar';

        $this->assertSame('foo', $container['foo']);
        $this->assertSame('bar', $container['bar']);
    }

    public function testGetId()
    {
        $this->assertSame((new ServiceContainerImpl(['app_id' => 'app-id1']))->getId(), (new ServiceContainerImpl(['app_id' => 'app-id1']))->getId());
        $this->assertNotSame((new ServiceContainerImpl(['app_id' => 'app-id1']))->getId(), (new ServiceContainerImpl(['app_id' => 'app-id2']))->getId());
    }

    public function testRegisterProviders()
    {
        $container = new DummyContainerForProviderTest();

        $this->assertSame('foo', $container['foo']);
    }

    public function testMagicGetDelegation()
    {
        $container = \Mockery::mock(ServiceContainer::class);

        $container->shouldReceive('offsetGet')->andReturn(BaseClient::class);
        $container->shouldReceive('shouldDelegate')->andReturn(true, false);

        $this->assertSame(BaseClient::class, $container->config);
    }
}

class DummyContainerForProviderTest extends ServiceContainerImpl
{
    protected $providers = [
        FooServiceProvider::class,
    ];
}

class FooServiceProvider implements ServiceProviderInterface
{
    public function register(Container $pimple)
    {
        $pimple['foo'] = function () {
            return 'foo';
        };
    }
}
