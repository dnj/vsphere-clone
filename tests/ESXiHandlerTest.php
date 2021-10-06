<?php

namespace dnj\VsphereClone\tests;

use dnj\VsphereClone\ESXiHandler;

class ESXiHandlerTest extends TestCase
{
    public function testCloneByPath(): void
    {
        $api = $this->getAPI();
        if (!$api) {
            $this->markTestSkipped('This test needs API');
        }

        $path = $this->getPath();
        if (!$path) {
            $this->markTestSkipped('This test needs path to a vmtx file');
        }

        $handler = new ESXiHandler($api, $path);
        $handler->cloneTo('new-clone');
    }

    protected function getPath(): ?string
    {
        return getenv('VSPHERE_CLONE_ESXI_HANDLER_SOURCE_PATH') ?: null;
    }
}
