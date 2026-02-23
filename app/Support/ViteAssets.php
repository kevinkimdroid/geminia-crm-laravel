<?php

namespace App\Support;

class ViteAssets
{
    /**
     * Generate Vite asset tags for Laravel 8 (no built-in @vite directive).
     * Supports both dev server (hot file) and production builds (manifest).
     */
    public function toHtml(array $entrypoints): string
    {
        $hotFile = public_path('hot');
        if (file_exists($hotFile)) {
            return $this->devServerTags(trim(file_get_contents($hotFile)), $entrypoints);
        }
        return $this->productionTags($entrypoints);
    }

    protected function devServerTags(string $url, array $entrypoints): string
    {
        $url = rtrim($url, '/');
        $tags = ['<script type="module" src="' . $url . '/@vite/client"></script>'];
        foreach ($entrypoints as $entry) {
            if (strpos($entry, '.css') !== false) {
                $tags[] = '<link rel="stylesheet" href="' . $url . '/' . $entry . '">';
            } else {
                $tags[] = '<script type="module" src="' . $url . '/' . $entry . '"></script>';
            }
        }
        return implode("\n    ", $tags);
    }

    protected function productionTags(array $entrypoints): string
    {
        $manifestPath = public_path('build/manifest.json');
        if (!file_exists($manifestPath)) {
            return '<!-- Vite manifest not found. Run: npm run build -->';
        }
        $manifest = json_decode(file_get_contents($manifestPath), true);
        if (!$manifest) {
            return '<!-- Invalid Vite manifest -->';
        }
        $tags = [];
        $base = asset('build');
        foreach ($entrypoints as $entry) {
            $asset = $manifest[$entry] ?? null;
            if (!$asset || empty($asset['file'])) {
                continue;
            }
            $file = $asset['file'];
            if (substr($file, -4) === '.css') {
                $tags[] = '<link rel="stylesheet" href="' . $base . '/' . $file . '">';
            } else {
                $tags[] = '<script type="module" src="' . $base . '/' . $file . '"></script>';
            }
        }
        return implode("\n    ", $tags);
    }
}
