<?php

namespace dnj\VsphereClone\ESXiHandler;

use dnj\Filesystem\Contracts\IFile;

class VmxParser
{
    protected IFile $file;

    /**
     * @var array<string,string|int|bool|array>
     */
    protected array $values = [];

    public function __construct(IFile $file)
    {
        $this->file = $file;
    }

    /**
     * @return array<string,string|int|bool|array>
     */
    public function parse(): array
    {
        $content = $this->file->read();
        $lines = array_filter(explode("\n", $content), fn ($l) => !empty($l));
        foreach ($lines as $line) {
            [$key, $value] = explode('=', $line);
            $key = trim($key);
            $value = trim($value);
            $value = json_decode($value);
            if ('TRUE' === $value) {
                $value = true;
            } elseif ('FALSE' === $value) {
                $value = false;
            } elseif (preg_match("/^-?\d+$/", $value)) {
                $value = intval($value);
            }
            $this->values[$key] = $value;
        }

        return $this->values;
    }
}
