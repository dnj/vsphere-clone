<?php

namespace dnj\VsphereClone;

use dnj\phpvmomi\API;
use dnj\phpvmomi\DataObjects\ManagedObjectReference;
use dnj\phpvmomi\DataObjects\VirtualMachineCloneSpec;
use dnj\phpvmomi\DataObjects\VirtualMachineRelocateSpec;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;

class VCenterHandler extends HandlerAbstract
{
    private API $api;
    private string $sourceID;

    public function __construct(API $api, string $sourceID)
    {
        $this->api = $api;
        $this->sourceID = $sourceID;
    }

    /**
     * @return static
     */
    public function setSourceID(string $sourceID): self
    {
        $this->sourceID = $sourceID;

        return $this;
    }

    public function getSourceID(): string
    {
        return $this->sourceID;
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

    public function cloneTo(string $name): string
    {
        $source = $this->getSource();
        $location = $this->location;
        if (null === $location) {
            $location = new Location();
        }

        $host = $location->getHost() ?? Location::getVMHost($source);
        $resourcePool = $location->getResourcePool() ?? Location::getVMResourcePool($source);
        $datastore = $location->getDatastore() ?? Location::getVMDatastore($source);

        $spec = new VirtualMachineCloneSpec();
        $spec->location = new VirtualMachineRelocateSpec();
        $spec->location->datastore = $datastore ? new ManagedObjectReference('Datastore', $datastore) : null;
        $spec->location->host = $host ? new ManagedObjectReference('HostSystem', $host) : null;
        $spec->location->pool = $resourcePool ? new ManagedObjectReference('ResourcePool', $resourcePool) : null;
        $spec->memory = false;
        $spec->powerOn = $this->powerOn;
        $spec->template = $this->template;
        $task = $source->_CloneVM_Task($name, $source->parent, $spec);
        $task->waitFor(0);

        return $task->info->result;
    }

    public function getSource(): VirtualMachine
    {
        return (new VirtualMachine($this->api))->byID($this->sourceID);
    }
}
