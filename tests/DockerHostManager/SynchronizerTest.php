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

    public function testUnderscoreHostnamesAlsoGetDashVariants(): void
    {
        $method = new \ReflectionMethod(Synchronizer::class, 'uniqueHostnames');
        $method->setAccessible(true);

        $synchronizer = new Synchronizer($this->createMock(Docker::class), '/etc/hosts', '.docker');

        $hostnames = $method->invoke($synchronizer, ['web.my_project_default', 'api.my_project_default']);

        $this->assertSame(
            ['web.my_project_default', 'web.my-project-default', 'api.my_project_default', 'api.my-project-default'],
            $hostnames
        );
    }
}
