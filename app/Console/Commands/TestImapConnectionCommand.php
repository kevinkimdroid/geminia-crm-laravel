<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;

class TestImapConnectionCommand extends Command
{
    protected $signature = 'mail:test-imap';

    protected $description = 'Test IMAP connection for Mail Manager';

    public function handle(): int
    {
        if (! extension_loaded('imap')) {
            $this->error('PHP IMAP extension is not loaded.');
            $this->line('Enable it in php.ini: remove semicolon from ;extension=imap');
            $this->line('XAMPP: C:\xampp\php\php.ini');
            return self::FAILURE;
        }

        $host = config('imap.accounts.geminia.host', env('IMAP_HOST', 'smtp.office365.com'));
        $port = (int) config('imap.accounts.geminia.port', env('IMAP_PORT', 993));
        $user = config('imap.accounts.geminia.username', env('IMAP_USERNAME'));
        $encryption = config('imap.accounts.geminia.encryption', env('IMAP_ENCRYPTION', 'ssl'));

        $this->info('Testing IMAP connection...');
        $this->line("Host: {$host}:{$port} ({$encryption}), User: {$user}");

        // Pre-check: DNS
        $ip = gethostbyname($host);
        if ($ip === $host) {
            $this->warn("DNS: Could not resolve '{$host}' — check hostname or firewall.");
        } else {
            $this->line("DNS: {$host} → {$ip}");
        }

        // Pre-check: port reachable
        $errno = 0;
        $errstr = '';
        $scheme = ($encryption === 'ssl') ? 'ssl' : 'tcp';
        $target = ($encryption === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $fp = @stream_socket_client($target, $errno, $errstr, 8);
        if (! $fp) {
            $this->warn("Port: Cannot reach {$host}:{$port} — {$errstr} ({$errno})");
            $this->line('Check: firewall, VPN, or host/port from your mail provider.');
        } else {
            $this->line('Port: Reachable');
            fclose($fp);
        }

        try {
            $cm = new ClientManager(config('imap'));
            $client = $cm->account('geminia');
            $client->connect();
            $this->info('IMAP: Connected successfully.');
            $folders = $client->getFolders();
            $this->line('Folders: ' . $folders->pluck('path')->implode(', '));
            $client->disconnect();
            return self::SUCCESS;
        } catch (ConnectionFailedException $e) {
            $msg = $e->getMessage();
            $prev = $e->getPrevious();
            if ($prev) {
                $msg .= ' | ' . $prev->getMessage();
            }
            $this->error('IMAP failed: ' . $msg);
            $this->newLine();
            $this->line('Alternatives:');
            $this->line('  1. Microsoft Graph (Office 365): docs/MICROSOFT-GRAPH-SETUP.md');
            $this->line('  2. Create Email: Tools → Mail Manager → Create Email (manual entry)');
            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
