<?php

namespace Tests\Unit;

use App\Support\ErpHttpBaseUrl;
use PHPUnit\Framework\TestCase;

class ErpHttpBaseUrlTest extends TestCase
{
    public function test_derive_from_clients_http_url_preserves_path_prefix(): void
    {
        $this->assertSame(
            'https://gw.example.com/erp-layer',
            ErpHttpBaseUrl::deriveFromClientsHttpUrl('https://gw.example.com/erp-layer/api/clients')
        );
    }

    public function test_derive_from_clients_http_url_strips_trailing_clients_only(): void
    {
        $this->assertSame(
            'http://10.1.4.101:5000',
            ErpHttpBaseUrl::deriveFromClientsHttpUrl('http://10.1.4.101:5000/clients')
        );
    }

    public function test_normalize_base_strips_known_suffixes(): void
    {
        $this->assertSame(
            'http://10.0.0.1:5000',
            ErpHttpBaseUrl::normalizeBase('http://10.0.0.1:5000/api/clients')
        );
    }
}
