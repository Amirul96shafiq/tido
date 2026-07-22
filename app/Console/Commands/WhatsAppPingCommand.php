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

        $exists = $whatsApp->isWhatsAppNumber($phone);

        if ($exists === false) {
            $this->error("{$phone} is not registered on WhatsApp. Update Profile WhatsApp Number, then try again.");

            return self::FAILURE;
        }

        $this->info("Sending ping to {$phone}…");

        $result = $whatsApp->sendMessageResult($phone, $message);

        if (! $result->ok) {
            $error = match ($result->reason) {
                'not_on_whatsapp' => "{$phone} is not registered on WhatsApp. Update Profile WhatsApp Number, then try again.",
                'connection_error' => 'Could not reach Evolution API. Is the service running?',
                default => 'Failed to send. Check Evolution API URL, key, instance pairing, Profile WhatsApp Number, and logs.',
            };

            $this->error($error);

            return self::FAILURE;
        }

        $this->info('Ping sent successfully.');

        return self::SUCCESS;
    }
}
