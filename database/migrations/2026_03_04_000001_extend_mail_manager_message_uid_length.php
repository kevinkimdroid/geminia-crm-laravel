<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection('vtiger')->getDriverName();
        if ($driver === 'mysql') {
            DB::connection('vtiger')->statement('ALTER TABLE mail_manager_emails MODIFY message_uid VARCHAR(512) NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = DB::connection('vtiger')->getDriverName();
        if ($driver === 'mysql') {
            DB::connection('vtiger')->statement('ALTER TABLE mail_manager_emails MODIFY message_uid VARCHAR(100) NOT NULL');
        }
    }
};
