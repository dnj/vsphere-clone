<?php

namespace dnj\VsphereClone;

use dnj\Filesystem\Tmp;
use dnj\phpvmomi\API;
use dnj\phpvmomi\DataObjects\FolderFileInfo;
use dnj\phpvmomi\DataObjects\ManagedObjectReference;
use dnj\phpvmomi\ManagedObjects\Datastore;
use dnj\phpvmomi\ManagedObjects\Folder;
use dnj\phpvmomi\ManagedObjects\HostDatastoreBrowser;
use dnj\phpvmomi\ManagedObjects\HostSystem;
use dnj\phpvmomi\ManagedObjects\VirtualMachine;
use dnj\VsphereClone\ESXiHandler\VmxFormatter;
use dnj\VsphereClone\ESXiHandler\VmxParser;
use Exception;

class ESXiHandler extends HandlerAbstract
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
        if ('HostAgent' != $this->api->getApiType()) {
            throw new Exception('You should use VCenterHandler');
        }
        $datastore = $this->getSourceDatastore();
        $location = $this->location ?? new Location();

        $destDatastore = null;
        $datastoreID = $location->getDatastore();
        if ($datastoreID) {
            $destDatastore = (new Datastore($this->api))->byID($datastoreID);
        } else {
            $destDatastore = $datastore;
        }
        $dsPath = $this->makeNewVMDirectory($destDatastore, $name);
        $vmxResult = $this->handleVMX($datastore, $name);

        $vmxPath = "{$name}/{$name}.vmx";
        $this->api->getFileManager()->upload($destDatastore->getFileURL($vmxPath), $vmxResult['vmx']);

        foreach ($vmxResult['vmdks'] as $source => $file) {
            $this->duplicateVMDK($source, $dsPath.'/'.$file);
        }

        $vmRef = $this->registerVM($destDatastore->getDatastorePath($vmxPath), $location);

        /** @var VirtualMachine */
        $vm = $vmRef->init($this->api);
        if ($this->powerOn) {
            $task = $vm->_PowerOnVM_Task();
            $task->waitFor(0);
        }

        return $vmRef->_;
    }

    protected function registerVM(string $vmxPath, Contracts\ILocation $location): ManagedObjectReference
    {
        $resourcePoolID = $location->getResourcePool() ?? 'ha-root-pool';
        $resourcePool = new ManagedObjectReference('ResourcePool', $resourcePoolID);

        $hostID = $location->getHost() ?? 'ha-host';
        $host = (new HostSystem($this->api))->byID($hostID);

        $datacenter = $host->getDatacenter();

        /** @var Folder */
        $vmFolder = $datacenter->vmFolder->init($this->api);

        $task = $vmFolder->_RegisterVM_Task(
            $vmxPath,
            $this->template,
            null,
            $resourcePool,
            $host->ref(),
        );
        $task->waitFor(0);

        return $task->info->result;
    }

    protected function makeNewVMDirectory(Datastore $datastore, string $name): string
    {
        $browser = $datastore->browser->get($this->api);
        $alreadyExists = $this->isDirectoryExists($datastore, $name);
        $dsPath = $datastore->getDatastorePath($name);
        if ($alreadyExists) {
            throw new Exception($dsPath.' already exists');
        }
        $datastore->makeDirectory($name, false);

        return $dsPath;
    }

    protected function isDirectoryExists(Datastore $datastore, string $name): bool
    {
        /**
         * @var HostDatastoreBrowser
         */
        $browser = $datastore->browser->get($this->api);
        $items = $browser->search($datastore->getDatastorePath('/'));
        foreach ($items as $item) {
            if ($item instanceof FolderFileInfo and $item->path === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{"vmdks":array<string,string>,"vmx":Tmp\File}
     */
    protected function handleVMX(Datastore $datastore, string $name)
    {
        $vmx = new Tmp\File();
        $this->api->getFileManager()->download($datastore->getFileURL($this->parseDatastorePath()['path']), $vmx);
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
         * @var array<string,string>
         */
        $vmdks = [];
        $basename = substr($this->getBasename($this->sourceID), 0, -strlen('.vmtx'));
        foreach ($values as $key => $value) {
            if (is_string($value) and preg_match("/\.fileName$/", $key) and preg_match("/\.vmdk$/", $value)) {
                $newName = str_replace($basename, $name, $value);
                $values[$key] = $newName;
                $vmdks[$value] = $newName;
            }
        }
        $formatter = new VmxFormatter($values);
        $formatter->writeToFile($vmx);

        return [
            'vmdks' => $vmdks,
            'vmx' => $vmx,
        ];
    }

    /**
     * @return array{"dsName":string,"path":string}|null
     */
    protected function sourceIsDatastorePath(): ?array
    {
        if (!preg_match("/^\[(.+)\]\s(.*\.vmtx)$/", $this->sourceID, $matches)) {
            return null;
        }

        return [
            'dsName' => $matches[1],
            'path' => $matches[2],
        ];
    }

    /**
     * @return array{"dsName":string,"path":string}
     */
    protected function parseDatastorePath(): array
    {
        $result = $this->sourceIsDatastorePath();
        if (!$result) {
            throw new Exception();
        }

        return $result;
    }

    protected function getSourceDatastore(): Datastore
    {
        $dsPath = $this->parseDatastorePath();
        $datastore = $this->getDatastoreByName($dsPath['dsName']);

        return $datastore;
    }

    protected function getDirname(string $datastorePath): string
    {
        $pos = strrpos($datastorePath, '/');
        if (false === $pos) {
            return $datastorePath;
        }

        return substr($datastorePath, 0, $pos);
    }

    protected function getBasename(string $datastorePath): string
    {
        $pos = strrpos($datastorePath, '/');
        if (false === $pos) {
            return '';
        }

        return substr($datastorePath, $pos + 1);
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

    /**
     * @param string $source path is relative to sourceID
     * @param string $dest   path is datstore path
     */
    protected function duplicateVMDK(string $source, string $dest): void
    {
        $this->api
            ->getVirtualDiskManager()
            ->_CopyVirtualDisk_Task($this->getDirname($this->sourceID).'/'.$source, $dest)
            ->waitFor(0);
    }
}
