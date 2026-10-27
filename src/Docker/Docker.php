<?php

namespace DockerHostManager\Docker;

use Docker\API\Model\EventMessage;
use Docker\Docker as DockerBase;

class Docker extends DockerBase
{
    public static function create(
        $httpClient = null,
        array $additionalPlugins = [],
        array $additionalNormalizers = [],
        bool $applyServerPlugins = true
    ): self {
        return parent::create($httpClient, $additionalPlugins, $additionalNormalizers, $applyServerPlugins);
    }

    public function listenEvents(callable $callback): void
    {
        $events = $this->systemEvents([
            'filters' => json_encode(['type' => ['container']], JSON_THROW_ON_ERROR),
        ]);

        if (null === $events) {
            throw new \RuntimeException('Failed to open Docker events stream.');
        }

        $events->onFrame(function (EventMessage $message) use ($callback): void {
            call_user_func($callback, new Event($message));
        });
        $events->wait();
    }
}
