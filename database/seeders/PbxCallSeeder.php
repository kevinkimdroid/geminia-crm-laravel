<?php

namespace Database\Seeders;

use App\Models\PbxCall;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PbxCallSeeder extends Seeder
{
    public function run(): void
    {
        $calls = [
            [
                'call_status' => 'completed',
                'direction' => 'inbound',
                'customer_number' => '0711234567',
                'reason_for_calling' => 'Policy inquiry',
                'customer_name' => 'John Kamau',
                'user_name' => 'Felister Kibue',
                'recording_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                'duration_sec' => 60,
                'start_time' => Carbon::parse('2026-02-17 12:40'),
            ],
            [
                'call_status' => 'completed',
                'direction' => 'inbound',
                'customer_number' => '002547112345678',
                'reason_for_calling' => 'Claim status',
                'customer_name' => 'Mary Wanjiku',
                'user_name' => 'Peter Mugenya',
                'recording_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                'duration_sec' => 45,
                'start_time' => Carbon::parse('2026-02-16 13:26'),
            ],
            [
                'call_status' => 'busy',
                'direction' => 'inbound',
                'customer_number' => '0722345678',
                'reason_for_calling' => null,
                'customer_name' => null,
                'user_name' => null,
                'recording_url' => null,
                'duration_sec' => 0,
                'start_time' => Carbon::parse('2026-02-16 11:15'),
            ],
            [
                'call_status' => 'no-response',
                'direction' => 'inbound',
                'customer_number' => '0733456789',
                'reason_for_calling' => null,
                'customer_name' => null,
                'user_name' => null,
                'recording_url' => null,
                'duration_sec' => 0,
                'start_time' => Carbon::parse('2026-02-15 14:30'),
            ],
        ];

        foreach ($calls as $i => $call) {
            $call['external_id'] = 'sample-' . ($i + 1);
            PbxCall::updateOrCreate(
                ['external_id' => $call['external_id']],
                $call
            );
        }
    }
}
