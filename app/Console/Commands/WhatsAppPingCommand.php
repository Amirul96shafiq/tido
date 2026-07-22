<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WhatsAppNotificationService;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use Illuminate\Console\Command;

class WhatsAppPingCommand extends Command
{
    protected $signature = 'whatsapp:ping {--message=}';

    protected $description = 'Send a test WhatsApp message to the profile WhatsApp number via Evolution API';

    public function handle(WhatsAppNotificationService $whatsApp): int
    {
        $phone = PhoneNumber::primaryWhatsAppNumber();

        if ($phone === null) {
            $this->error('Set a WhatsApp number on a user Profile first (e.g. 60123456789).');

            return self::FAILURE;
        }

        $message = trim((string) $this->option('message'));

        if ($message === '') {
            $message = WhatsAppMessage::compose(
                '✅',
                'Test ping',
                "Outbound WhatsApp delivery is working correctly.\n\nSend a document anytime to start tracking expenses.",
            );
        }

        $this->info("Sending ping to {$phone}…");

        $sent = $whatsApp->sendMessage($phone, $message);

        if (! $sent) {
            $this->error('Failed to send. Check Evolution API URL, key, instance pairing, and logs.');

            return self::FAILURE;
        }

        $this->info('Ping sent successfully.');

        return self::SUCCESS;
    }
}
