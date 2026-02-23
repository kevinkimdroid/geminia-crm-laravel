<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'template_name' => 'Birthdays',
                'subject' => 'Happy Birthday',
                'description' => 'Sending Messages to Life Customers on their Birthday',
                'module_name' => 'Customers',
                'body' => "Dear {{firstname}},\n\nWishing you a very Happy Birthday! Thank you for being a valued customer.\n\nBest regards,\nGeminia Life Insurance",
            ],
            [
                'template_name' => 'Invite Users',
                'subject' => 'Invitation',
                'description' => 'Invite Users',
                'module_name' => 'Events',
                'body' => "Hello,\n\nYou are invited to join our event.\n\nRegards,\nGeminia Life Insurance",
            ],
            [
                'template_name' => 'ToDo Reminder',
                'subject' => 'Activity Reminder',
                'description' => 'Reminder',
                'module_name' => 'Events',
                'body' => "Dear {{firstname}},\n\nThis is a reminder for your upcoming activity.\n\nRegards,\nGeminia Life Insurance",
            ],
            [
                'template_name' => 'Activity Reminder',
                'subject' => 'Reminder',
                'description' => 'Reminder',
                'module_name' => 'Events',
                'body' => "Hello,\n\nThis is a reminder for your scheduled activity.\n\nRegards,\nGeminia Life Insurance",
            ],
        ];

        foreach ($templates as $t) {
            EmailTemplate::updateOrCreate(
                ['template_name' => $t['template_name'], 'subject' => $t['subject']],
                $t
            );
        }
    }
}
