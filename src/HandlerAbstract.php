<?php

namespace dnj\VsphereClone;

use dnj\phpvmomi\API;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use dnj\VsphereClone\Contracts\IHandler;
use dnj\VsphereClone\Contracts\ILocation;

abstract class HandlerAbstract implements IHandler
{
    protected ?ILocation $location = null;
    protected bool $powerOn = false;
    protected bool $template = false;

    protected API $api;

    /**
     * @var VirtualMachine|string
     */
    protected $source;

    /**
     * @param VirtualMachine|string $source
     */
    public function __construct(API $api, $source)
    {
        $this->api = $api;
        $this->source = $source;
    }

    /**
     * @param VirtualMachine|string $source
     *
     * @return static
     */
    public function setSource($source): self
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return VirtualMachine|string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @return static
     */
    public function setAPI(API $api): self
    {
        $this->api = $api;

        return $this;
    }

    public function getAPI(): API
    {
        return $this->api;
    }

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
