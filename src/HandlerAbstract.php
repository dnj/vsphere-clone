<?php

namespace dnj\VsphereClone;

use dnj\VsphereClone\Contracts\IHandler;
use dnj\VsphereClone\Contracts\ILocation;

abstract class HandlerAbstract implements IHandler
{
    protected ?ILocation $location = null;
    protected bool $powerOn = false;
    protected bool $template = false;

    /**
     * @return static
     */
    public function setLocation(?ILocation $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getLocation(): ?ILocation
    {
        return $this->location;
    }

    /**
     * @return static
     */
    public function makePowerOn(bool $value = true): self
    {
        $this->powerOn = $value;

        return $this;
    }

    public function getPowerOn(): bool
    {
        return $this->powerOn;
    }

    /**
     * @return static
     */
    public function makeTemplate(bool $value = true): self
    {
        $this->template = $value;

        return $this;
    }

    public function getTemplate(): bool
    {
        return $this->template;
    }
}
