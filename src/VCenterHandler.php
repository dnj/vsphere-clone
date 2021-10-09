<?php

namespace dnj\VsphereClone;

use dnj\phpvmomi\API;
use dnj\phpvmomi\DataObjects\ManagedObjectReference;
use dnj\phpvmomi\DataObjects\VirtualMachineCloneSpec;
use dnj\phpvmomi\DataObjects\VirtualMachineRelocateSpec;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use Exception;

class VCenterHandler extends HandlerAbstract
{
    /**
     * @param VirtualMachine|string $source
     */
    public function __construct(API $api, $source)
    {
        if (!$source instanceof VirtualMachine) {
            throw new Exception('Currently only VirtualMachine objects are accepted as source');
        }
        parent::__construct($api, $source);
    }

    public function cloneTo(string $name): VirtualMachine
    {
        $source = $this->getSource();
        if (!$source instanceof VirtualMachine) {
            throw new Exception('Currently only VirtualMachine objects are accepted as source');
        }
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
        $task->waitFor(3600);

        /**
         * @var VirtualMachine
         */
        $vm = $task->info->result->get($this->api);

        return $vm;
    }
}
