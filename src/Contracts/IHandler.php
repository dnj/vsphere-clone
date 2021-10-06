<?php

namespace dnj\VsphereClone\Contracts;

interface IHandler
{
    public function setLocation(ILocation $location): self;

    public function getLocation(): ?ILocation;

    public function makePowerOn(bool $value = true): self;

    public function getPowerOn(): bool;

    public function makeTemplate(bool $value = true): self;

    public function getTemplate(): bool;

    /**
     * @return string New VM's ID
     */
    public function cloneTo(string $name): string;
}
