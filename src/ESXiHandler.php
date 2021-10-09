<?php

namespace dnj\VsphereClone;

use dnj\Filesystem\Tmp;
use dnj\phpvmomi\ManagedObjects\Datastore;
use dnj\phpvmomi\ManagedObjects\Folder;
use dnj\phpvmomi\ManagedObjects\HostSystem;
use dnj\phpvmomi\ManagedObjects\ResourcePool;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use dnj\phpvmomi\Utils\Path;
use dnj\VsphereClone\ESXiHandler\VmxFormatter;
use dnj\VsphereClone\ESXiHandler\VmxParser;
use Exception;

class ESXiHandler extends HandlerAbstract
{
    public function cloneTo(string $name): VirtualMachine
    {
        if ('HostAgent' != $this->api->getApiType()) {
            throw new Exception('You should use VCenterHandler');
        }
        if ($this->source instanceof VirtualMachine and !$this->source->isOff()) {
            throw new Exception('Source machine must be off');
        }
        [$host, $resourcePool, $datastore] = $this->insureLocation();

        $rootPath = $datastore->getPath($name);
        $vmxPath = $rootPath->concat("{$name}.vmx");
        $datastore->makeDirectory($name, false);

        $vmxResult = $this->handleVMX($name);
        $this->api->getFileManager()->upload($vmxPath->toURL($this->api), $vmxResult['vmx']);

        foreach ($vmxResult['vmdks'] as $vmdk) {
            $this->duplicateVMDK($vmdk['source'], $rootPath->concat($vmdk['dest']));
        }

        $vm = $this->registerVM($vmxPath, $host, $resourcePool, $datastore);

        if ($this->powerOn) {
            $vm->_PowerOnVM_Task()->waitFor(0);
        }

        return $vm;
    }

    protected function registerVM(Path $vmxPath, HostSystem $host, ResourcePool $resourcePool, Datastore $datastore): VirtualMachine
    {
        $datacenter = $host->getDatacenter();

        /** @var Folder */
        $vmFolder = $datacenter->vmFolder->init($this->api);

        $task = $vmFolder->_RegisterVM_Task(
            $vmxPath->toDSPath(),
            $this->template,
            null,
            $resourcePool->ref(),
            $host->ref(),
        );
        $task->waitFor(0);

        /** @var VirtualMachine */
        $vm = $task->info->result->get($this->api);

        return $vm;
    }

    /**
     * @return array{"vmdks":array<array{"source":Path,"dest":string}>,"vmx":Tmp\File}
     */
    protected function handleVMX(string $name)
    {
        $source = $this->getSourcePath();
        $vmx = new Tmp\File();
        $this->api->getFileManager()->download($source->toURL($this->api), $vmx);

        $parser = new VmxParser($vmx);
        $values = $parser->parse();
        $values['displayName'] = $name;
        foreach (array_keys($values) as $key) {
            if (
                'uuid.' == substr($key, 0, strlen('uuid.')) or
                preg_match("/^ethernet\d+\.generatedAddress/", $key) or
                preg_match("/^sata\d+:\d+\.fileName/", $key)
            ) {
                unset($values[$key]);
            }
        }
        unset($values['nvram'], $values['sched.swap.derivedName'], $values['migrate.hostlog'], $values['vc.uuid']);

        /**
         * @var array<array{"source":Path,"dest":string}>
         */
        $vmdks = [];
        $basename = str_replace(['.vmx', '.vmtx'], '', $source->getBaseName());
        foreach ($values as $key => $value) {
            if (is_string($value) and preg_match("/\.fileName$/", $key) and preg_match("/\.vmdk$/", $value)) {
                $newName = str_replace($basename, $name, $value);
                $values[$key] = $newName;
                $vmdks[] = [
                    'source' => $source->getDirname()->concat($value),
                    'dest' => $newName,
                ];
            }
        }
        $formatter = new VmxFormatter($values);
        $formatter->writeToFile($vmx);

        return [
            'vmdks' => $vmdks,
            'vmx' => $vmx,
        ];
    }

    protected function getSourcePath(): Path
    {
        return Path::fromDSPath(is_string($this->source) ? $this->source : $this->source->config->files->vmPathName);
    }

    protected function getSourceDatastore(): Datastore
    {
        return $this->getDatastoreByName($this->getSourcePath()->datastore);
    }

    protected function getDatastoreByName(string $name): Datastore
    {
        $datastores = (new Datastore($this->api))->list();
        foreach ($datastores as $item) {
            if ($item->name == $name) {
                return $item;
            }
        }
        throw new Exception("Datastore notfound with name: {$name}");
    }

    protected function duplicateVMDK(Path $source, Path $dest): void
    {
        $this->api
            ->getVirtualDiskManager()
            ->_CopyVirtualDisk_Task($source->toDSPath(), $dest->toDSPath())
            ->waitFor(3600);
    }

    /**
     * @return array{0:HostSystem,1:ResourcePool,2:Datastore}
     */
    protected function insureLocation(): array
    {
        $location = $this->location ?? new Location();

        $datastoreID = $location->getDatastore();
        if ($datastoreID) {
            $datastore = (new Datastore($this->api))->byID($datastoreID);
        } else {
            $datastore = $this->getSourceDatastore();
        }

        $hostID = $location->getHost() ?? 'ha-host';
        $host = (new HostSystem($this->api))->byID($hostID);

        $resourcePoolID = $location->getResourcePool() ?? 'ha-root-pool';
        $resourcePool = (new ResourcePool($this->api))->byID($resourcePoolID);

        return [$host, $resourcePool, $datastore];
    }
}
