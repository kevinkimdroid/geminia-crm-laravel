<?php

namespace App\Console\Commands;

use App\Services\PbxConfigService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PbxTestCallCommand extends Command
{
    protected $signature = 'pbx:test-call {number : Phone number to call} {extension : Your SIP extension}
                            {--no-prefix : Do not add 254 prefix for Kenya numbers}';

    protected $description = 'Test PBX makeCall by sending a request directly (does not actually dial if PBX rejects)';

    public function handle(PbxConfigService $pbxConfig): int
    {
        $number = preg_replace('/\D/', '', $this->argument('number'));
        $extension = trim($this->argument('extension'));

        if (strlen($number) < 5) {
            $this->error('Invalid phone number (min 5 digits).');
            return self::FAILURE;
        }

        if (! $this->option('no-prefix') && config('services.pbx.number_add_prefix', true)) {
            $countryCode = config('services.pbx.number_country_code', '254');
            if (strlen($number) === 9 && $number[0] === '7') {
                $number = $countryCode . $number;
            } elseif (strlen($number) === 10 && $number[0] === '0') {
                $number = $countryCode . substr($number, 1);
            }
        }

        $base = rtrim($pbxConfig->getMakeCallBaseUrl() ?? '', '/');
        $url = $base . '/makecall?' . http_build_query([
            'event' => 'OutgoingCall',
            'secret' => $pbxConfig->getSecretKey() ?: '',
            'from' => $extension,
            'to' => $number,
            'context' => $pbxConfig->getOutboundContext() ?: 'vtiger_outbound',
            'record' => '',
        ]);
        if (! $url) {
            $this->error('PBX not configured. Run php artisan pbx:config');
            return self::FAILURE;
        }

        $context = $pbxConfig->getOutboundContext() ?: 'vtiger_outbound';
        $trunk = $pbxConfig->getOutboundTrunk() ?: 'default';

        $this->info('PBX Make Call Test (Vtiger connector format)');
        $this->line('URL: ' . $url);
        $this->newLine();

        try {
            $response = Http::timeout(15)
                ->withHeaders(['Accept' => '*/*'])
                ->post($url, []);

            $this->line('Status: ' . $response->status());
            $this->line('Response body:');
            $this->line($response->body() ?: '(empty)');

            if ($response->successful()) {
                $body = $response->json();
                $ok = ($body['success'] ?? $body['status'] ?? true) === true || ($body['success'] ?? null) === 'success';
                if ($ok) {
                    $this->newLine();
                    $this->warn('PBX returned success but if the phone did not ring, the issue is in:');
                    $this->line('  - Vtiger Asterisk Connector (check its logs, VtigerAsteriskConnector.properties)');
                    $this->line('  - Asterisk: run "asterisk -rvvv" and place a call to see AMI/Originate output');
                    $this->line('  - Dialplan: context=' . $context . ', trunk=' . $trunk);
                    $this->line('  - Number format: try PBX_NUMBER_ADD_PREFIX=false if trunk expects 0XXXXXXXXX');
                }
            } else {
                $this->error('Request failed.');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
