<?php

namespace dnj\VsphereClone\Contracts;

use dnj\phpvmomi\DataObjects\VirtualMachineConfigSpec;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;

interface IHandler
{
    public function setLocation(?ILocation $location): self;

    public function getLocation(): ?ILocation;

    public function makePowerOn(bool $value = true): self;

    public function getPowerOn(): bool;

    public function makeTemplate(bool $value = true): self;

    public function getTemplate(): bool;

    public function cloneTo(string $name): VirtualMachine;

    /**
     * @param VirtualMachineConfigSpec|(callable(VirtualMachine):(VirtualMachineConfigSpec|null))|null $config
     */
    public function setVmConfig($config): self;

    /**
     * @return VirtualMachineConfigSpec|(callable(VirtualMachine):(VirtualMachineConfigSpec|null))|null
     */
    public function getVmConfig();
}
