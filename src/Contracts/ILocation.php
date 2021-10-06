<?php

namespace dnj\VsphereClone\Contracts;

interface ILocation
{
    public function getDatastore(): ?string;

    public function getHost(): ?string;

    public function getResourcePool(): ?string;
}
