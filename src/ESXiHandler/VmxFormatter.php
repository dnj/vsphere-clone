<?php

namespace dnj\VsphereClone\ESXiHandler;

use dnj\Filesystem\Contracts\IFile;

class VmxFormatter
{
    /**
     * @var array<string,string|int|bool|array>
     */
    protected array $values = [];

    /**
     * @param array<string,string|int|bool|array> $values
     */
    public function __construct(array $values)
    {
        $this->values = $values;
    }

    public function toString(): string
    {
        $lines = [];
        foreach ($this->values as $key => $value) {
            if (true === $value) {
                $value = 'TRUE';
            } elseif (false === $value) {
                $value = 'FALSE';
            }
            if (!is_array($value)) {
                $lines[] = "{$key} = \"{$value}\"";
            }
        }

        return implode("\n", $lines);
    }

    public function writeToFile(IFile $file): void
    {
        $file->write($this->toString());
    }
}
