<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

class StoragePaths
{
    /**
     * Ensure Laravel storage subdirectories exist (git only tracks .gitignore placeholders).
     */
    public static function ensure(): void
    {
        foreach (self::requiredDirectories() as $path) {
            if (is_dir($path)) {
                continue;
            }

            if (@mkdir($path, 0775, true) || is_dir($path)) {
                continue;
            }

            Log::warning('Could not create storage directory', ['path' => $path]);
        }
    }

    /**
     * @return list<string>
     */
    public static function requiredDirectories(): array
    {
        return [
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('framework/testing'),
            storage_path('logs'),
            storage_path('app/public'),
            base_path('bootstrap/cache'),
        ];
    }
}
