<?php

namespace dnj\VsphereClone\tests;

use dnj\phpvmomi\API;
use Exception;

class TestCase extends \PHPUnit\Framework\TestCase
{
    protected ?API $api = null;

    public function getAPI(): ?API
    {
        if (!$this->api) {
            $sdk = $this->getApiSdkUrl();
            if (!$sdk) {
                return null;
            }
            $username = $this->getApiUsername();
            if (!$username) {
                throw new Exception('sdk url is provided but not username');
            }
            $password = $this->getApiPassword();
            if (!$password) {
                throw new Exception('sdk password is provided but not password');
            }
            $this->api = new API([
                'sdk' => $sdk,
                'username' => $username,
                'password' => $password,
                'ssl_verify' => false,
            ]);
        }

        return $this->api;
    }

    public function getApiSdkUrl(): ?string
    {
        return getenv('PHPVOMI_API_URL') ?: null;
    }

    public function getApiUsername(): ?string
    {
        return getenv('PHPVOMI_API_USER') ?: null;
    }

    public function getApiPassword(): ?string
    {
        return getenv('PHPVOMI_API_PASS') ?: null;
    }
}
