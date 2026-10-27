<?php

namespace DockerHostManager\Docker;

use Docker\API\Model\EventMessage;

class Event
{
    /** @var string|null */
    protected $status;

    /** @var string|null */
    protected $id;

    /** @var string|null */
    protected $from;

    /** @var int|string|null */
    protected $time;

    public function __construct(EventMessage|array $raw)
    {
        if ($raw instanceof EventMessage) {
            $this->status = $raw->getAction();
            $this->id = $raw->getActor()?->getID();
            $attributes = $raw->getActor()?->getAttributes();
            $this->from = is_iterable($attributes) ? ($attributes['image'] ?? null) : null;
            $this->time = $raw->getTime();

            return;
        }

        $this->status = $raw['status'] ?? $raw['action'] ?? null;
        $this->id = $raw['id'] ?? $raw['Actor']['ID'] ?? null;
        $this->from = $raw['from'] ?? null;
        $this->time = $raw['time'] ?? null;
    }

    public function getStatus()
    {
        return $this->status;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function getTime()
    {
        return $this->time;
    }
}
