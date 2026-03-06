<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

class GenerateFeedbackUrlCommand extends Command
{
    protected $signature = 'feedback:url {ticket_id : The closed ticket ID}';
    protected $description = 'Generate a signed feedback URL for testing (ticket must be closed)';

    public function handle(): int
    {
        $ticketId = (int) $this->argument('ticket_id');
        $crm = app(\App\Services\CrmService::class);
        $ticket = $crm->getTicket($ticketId);

        if (! $ticket) {
            $this->error("Ticket {$ticketId} not found.");
            return 1;
        }

        if (($ticket->status ?? '') !== 'Closed') {
            $this->warn("Ticket {$ticketId} is not closed. The feedback form requires a closed ticket.");
            $this->info('Closing it first, or use a different ticket.');
        }

        $url = URL::temporarySignedRoute('feedback.form', now()->addDays(7), ['ticket' => $ticketId]);
        $this->info('Feedback URL (valid 7 days):');
        $this->line($url);
        $this->newLine();
        $this->info('Copy and open in a browser to test the rating form.');

        return 0;
    }
}
