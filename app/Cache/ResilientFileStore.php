<?php

namespace App\Cache;

use Illuminate\Cache\FileStore;
use Throwable;

/**
 * File cache store that ensures the base directory exists and never throws
 * when the filesystem is missing or unwritable (falls back to uncached behaviour).
 */
class ResilientFileStore extends FileStore
{
    public function put($key, $value, $seconds)
    {
        try {
            $this->ensureBaseDirectoryExists();

            return parent::put($key, $value, $seconds);
        } catch (Throwable) {
            return false;
        }
    }

    public function add($key, $value, $seconds)
    {
        try {
            $this->ensureBaseDirectoryExists();

            return parent::add($key, $value, $seconds);
        } catch (Throwable) {
            return false;
        }
    }

    public function forever($key, $value)
    {
        try {
            $this->ensureBaseDirectoryExists();

            return parent::forever($key, $value);
        } catch (Throwable) {
            return false;
        }
    }

    protected function ensureCacheDirectoryExists($path)
    {
        try {
            $this->ensureBaseDirectoryExists();
            parent::ensureCacheDirectoryExists($path);
        } catch (Throwable) {
            // parent::put will fail gracefully if the directory still cannot be created
        }
    }

    protected function ensureBaseDirectoryExists(): void
    {
        $directory = $this->getDirectory();

        if ($this->files->isDirectory($directory)) {
            return;
        }

        $this->files->makeDirectory($directory, 0775, true, true);
    }
}
