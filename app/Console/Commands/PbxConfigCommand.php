<?php

namespace App\Console\Commands;

use App\Services\PbxConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PbxConfigCommand extends Command
{
    protected $signature = 'pbx:config
                            {--export : Show .env lines to copy}
                            {--json : Output raw gateway parameters as JSON}';

    protected $description = 'Fetch and display PBX settings from the previous CRM (vtiger_pbxmanager_gateway)';

    public function handle(PbxConfigService $pbxConfig): int
    {
        $this->info('PBX configuration from previous CRM (vtiger database)');
        $this->newLine();

        try {
            $row = DB::connection('vtiger')
                ->table('vtiger_pbxmanager_gateway')
                ->where('gateway', 'PBXManager')
                ->first();
        } catch (\Throwable $e) {
            $this->error('Could not connect to vtiger database: ' . $e->getMessage());
            $this->line('Check DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD in .env');
            return self::FAILURE;
        }

        if (! $row || empty($row->parameters)) {
            $this->warn('No PBXManager gateway found in vtiger_pbxmanager_gateway.');
            $this->line('Configure PBX in the old CRM first (Settings → PBX Manager → Asterisk/connector).');
            return self::FAILURE;
        }

        $params = json_decode($row->parameters, true);
        if (! is_array($params)) {
            $this->error('Invalid parameters JSON in gateway.');
            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line(json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $keys = [
            'webappurl' => 'Webapp URL (Asterisk App / PBX)',
            'webappUrl' => 'Webapp URL (alt)',
            'vtigersecretkey' => 'Vtiger Secret Key',
            'vtigerSecretKey' => 'Vtiger Secret Key (alt)',
            'outboundcontext' => 'Outbound Context (Asterisk)',
            'outboundContext' => 'Outbound Context (alt)',
            'outboundtrunk' => 'Outbound Trunk',
            'outboundTrunk' => 'Outbound Trunk (alt)',
        ];
        $shown = [];
        foreach ($keys as $key => $label) {
            if (isset($params[$key]) && $params[$key] !== '' && ! isset($shown[strtolower($key)])) {
                $val = $params[$key];
                if (str_contains(strtolower($key), 'secret')) {
                    $val = strlen($val) ? '***' . substr($val, -4) : '(empty)';
                }
                $this->line(sprintf('  %-22s %s', $label . ':', $val));
                $shown[strtolower($key)] = true;
            }
        }
        foreach ($params as $k => $v) {
            if (! isset($keys[$k]) && $v !== '' && $v !== null) {
                $this->line(sprintf('  %-22s %s', $k . ':', is_scalar($v) ? $v : (is_array($v) ? json_encode($v) : '(object)')));
            }
        }

        $this->newLine();
        $this->line('Resolved values (used by app):');
        $this->line('  webapp_url:   ' . ($pbxConfig->getWebappUrl() ?: '(none)'));
        $this->line('  context:      ' . ($pbxConfig->getOutboundContext() ?: 'vtiger_outbound (default)'));
        $this->line('  trunk:        ' . ($pbxConfig->getOutboundTrunk() ?: 'default'));
        $this->line('  secret:       ' . ($pbxConfig->getSecretKey() ? '***' . substr($pbxConfig->getSecretKey(), -4) : '(none)'));
        $this->line('  configured:   ' . ($pbxConfig->isConfigured() ? 'yes' : 'no'));

        if ($this->option('export')) {
            $this->newLine();
            $this->info('Add these to .env if the vtiger DB is unavailable:');
            $this->line('');
            $url = $params['webappurl'] ?? $params['webappUrl'] ?? '';
            if ($url) {
                $this->line('PBX_WEBAPP_URL=' . $url);
            }
            $customUrl = config('services.pbx.make_call_url');
            if (! $customUrl && $url) {
                $this->line('# Optional full makeCall URL:');
                $this->line('# PBX_MAKE_CALL_URL=' . rtrim($url, '/') . '/makeCall');
            }
            $secret = $params['vtigersecretkey'] ?? $params['vtigerSecretKey'] ?? '';
            if ($secret) {
                $this->line('# Secret is used from vtiger gateway (no .env override)');
            }
            $ctx = $params['outboundcontext'] ?? $params['outboundContext'] ?? '';
            $trunk = $params['outboundtrunk'] ?? $params['outboundTrunk'] ?? '';
            if ($ctx || $trunk) {
                $this->line('# Note: outboundcontext and outboundtrunk are not in .env; kept in vtiger gateway.');
            }
            $this->line('');
        }

        return self::SUCCESS;
    }
}
