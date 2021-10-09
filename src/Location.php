<?php

namespace dnj\VsphereClone;

use dnj\phpvmomi\ManagedObjects\Datastore;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use dnj\phpvmomi\Utils\Path;
use dnj\VsphereClone\Contracts\ILocation;

class Location implements ILocation
{
    public static function getVMHost(VirtualMachine $vm): ?string
    {
        return $vm->runtime->host->_;
    }

    public static function getVMDatastore(VirtualMachine $vm): ?string
    {
        if (isset($vm->datastore->ManagedObjectReference)) {
            $path = Path::fromDSPath($vm->config->files->vmPathName);
            $datastores = (new Datastore($vm->getAPI()))->list();
            foreach ($datastores as $item) {
                if ($item->name == $path->datastore) {
                    return $item->id;
                }
            }
        }

        return null;
    }

    public static function getVMResourcePool(VirtualMachine $vm): ?string
    {
        if ($vm->resourcePool) {
            return $vm->resourcePool->_;
        }

        return $vm->getResourcePool()->id;
    }

    protected ?string $datastore = null;
    protected ?string $host = null;
    protected ?string $resourcePool = null;

    public function getDatastore(): ?string
    {
        return $this->datastore;
    }

    /**
     * @return static
     */
    public function setDatastore(?string $datastore): self
    {
        $this->datastore = $datastore;

        return $this;
    }

    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * @return static
     */
    public function setHost(?string $host): self
    {
        $this->host = $host;

        return $this;
    }

    public function getResourcePool(): ?string
    {
        return $this->resourcePool;
    }

    /**
     * @return static
     */
    public function setResourcePool(?string $resourcePool): self
    {
        $this->resourcePool = $resourcePool;

        return $this;
    }
}
