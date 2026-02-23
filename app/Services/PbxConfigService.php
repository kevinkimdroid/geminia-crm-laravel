<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PbxConfigService
{
    protected ?array $gatewayParams = null;

    public function getWebappUrl(): ?string
    {
        $env = config('services.pbx.webapp_url');
        if ($env !== null && $env !== '') {
            return $env;
        }
        return $this->getGatewayParam('webappurl');
    }

    public function getSecretKey(): ?string
    {
        $env = config('services.pbx.secret_key');
        if ($env !== null && $env !== '') {
            return $env;
        }
        return $this->getGatewayParam('vtigersecretkey');
    }

    public function getOutboundContext(): ?string
    {
        $env = config('services.pbx.outbound_context');
        if ($env !== null && $env !== '') {
            return $env;
        }
        return $this->getGatewayParam('outboundcontext');
    }

    public function getOutboundTrunk(): ?string
    {
        $env = config('services.pbx.outbound_trunk');
        if ($env !== null && $env !== '') {
            return $env;
        }
        return $this->getGatewayParam('outboundtrunk');
    }

    public function getRecordingUrl(string $sourceUuid): string
    {
        $base = rtrim($this->getWebappUrl() ?? '', '/');
        return $base ? "{$base}/recording?id={$sourceUuid}" : '';
    }

    /**
     * Get the base URL for makeCall API (e.g. http://10.1.1.86:8383).
     */
    public function getMakeCallBaseUrl(): ?string
    {
        $base = rtrim($this->getWebappUrl() ?? '', '/');
        return $base ?: null;
    }

    /**
     * Get custom make call endpoint URL if set in config (overrides auto-discovery).
     */
    public function getMakeCallUrl(): ?string
    {
        return config('services.pbx.make_call_url') ?: null;
    }

    public function isConfigured(): bool
    {
        return (bool) ($this->getWebappUrl() || config('services.pbx.make_call_url'));
    }

    protected function getGatewayParam(string $key): ?string
    {
        $params = $this->getGatewayParameters();
        $aliases = [
            'webappurl' => ['webappUrl'],
            'vtigersecretkey' => ['vtigerSecretKey'],
            'outboundcontext' => ['outboundContext'],
            'outboundtrunk' => ['outboundTrunk'],
        ];
        $keysToTry = array_merge([$key], $aliases[$key] ?? []);
        foreach ($keysToTry as $k) {
            $val = $params[$k] ?? null;
            if ($val !== null && $val !== '') {
                return (string) $val;
            }
        }
        return null;
    }

    protected function getGatewayParameters(): array
    {
        if ($this->gatewayParams !== null) {
            return $this->gatewayParams;
        }

        $this->gatewayParams = Cache::remember('geminia_pbx_gateway_params', 300, function () {
            try {
                $row = DB::connection('vtiger')
                    ->table('vtiger_pbxmanager_gateway')
                    ->where('gateway', 'PBXManager')
                    ->first();
                if (! $row || empty($row->parameters)) {
                    return [];
                }
                $decoded = json_decode($row->parameters, true);
                return is_array($decoded) ? $decoded : [];
            } catch (\Throwable $e) {
                return [];
            }
        });

        return $this->gatewayParams;
    }
}
