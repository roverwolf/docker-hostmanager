<?php

namespace Test\DockerHostManager;

use DockerHostManager\Docker\Docker;
use DockerHostManager\Synchronizer;
use PHPUnit\Framework\TestCase;
use Test\Utils\PropertyAccessor;

class SynchronizerTest extends TestCase
{
    public function testThatAppCanBeConstructed()
    {
        $docker = $this->createMock(Docker::class);

        $application = new Synchronizer($docker, '/etc/hosts', 'docker');

        $this->assertSame($docker, PropertyAccessor::getProperty($application, 'docker'));
        $this->assertSame('/etc/hosts', PropertyAccessor::getProperty($application, 'hostsFile'));
        $this->assertSame('docker', PropertyAccessor::getProperty($application, 'tld'));
        $this->assertIsArray(PropertyAccessor::getProperty($application, 'activeContainers'));
    }
}
