<?php

namespace Volcengine\Tests;

use Volcengine\Kernel\ServiceContainer;

class ServiceContainerImpl extends ServiceContainer
{
    protected function getBaseUri(): string
    {
        return '';
    }
}
