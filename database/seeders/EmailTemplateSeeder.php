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
            [
                'template_name' => 'Broadcast — warm renewal reminder',
                'subject' => 'Friendly reminder about your policy',
                'description' => 'Polite renewal or engagement message for mass email (edit before send).',
                'module_name' => 'Broadcast',
                'body' => "Dear {{firstname}},\n\nThank you for choosing Geminia. We wanted to share a short update regarding your cover.\n\nIf you have any questions, reply to this email or contact us on 0709 551 150 / life@geminialife.co.ke.\n\nKind regards,\nGeminia Life Insurance",
            ],
            [
                'template_name' => 'Marketing — product spotlight',
                'subject' => 'Something new at Geminia Life',
                'description' => 'General marketing announcement; replace the bracketed line before sending.',
                'module_name' => 'Marketing',
                'body' => "Hi {{firstname}},\n\nWe have an update we think you will find useful: [add your offer or product detail here].\n\nBest regards,\nGeminia Life Insurance\nwww.geminialife.co.ke",
            ],
            [
                'template_name' => 'Broadcast SMS — short promo',
                'subject' => 'SMS',
                'description' => 'Short SMS; subject is not used when sending SMS from the broadcast page.',
                'module_name' => 'Broadcast SMS',
                'body' => 'Hi {{firstname}}, Geminia Life: [one-line offer]. Questions? 0709551150.',
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
