<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\WhatsAppNotificationService;
use App\Support\DashboardSpenderScope;
use App\Support\ExpenseSenderAttribution;
use App\Support\PhoneNumber;
use App\Support\WhatsAppMessage;
use App\Support\WhatsAppProcessingJobKey;
use App\Support\WhatsAppSpendingCommandParser;
use App\Support\WhatsAppSpendingReplyBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ProcessWhatsAppTextReplyJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout;

    public int $uniqueFor;

    public function __construct(
        public string $senderNumber,
        public string $originalText,
        public string $messageId,
    ) {
        $this->onQueue('whatsapp');
        $this->timeout = max(1, (int) config('services.evolution.timeout', 15)) + 15;
        $this->uniqueFor = WhatsAppProcessingJobKey::uniqueForSeconds();
    }

    public function uniqueId(): string
    {
        return WhatsAppProcessingJobKey::forMessage($this->messageId, 'text-reply');
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->uniqueId()))
                ->expireAfter($this->timeout + 60)
                ->releaseAfter(10),
            new RateLimited('evolution-send'),
        ];
    }

    public function handle(WhatsAppNotificationService $waService): void
    {
        if (Cache::has(WhatsAppProcessingJobKey::textReplySentCacheKey($this->messageId))) {
            Log::info('ProcessWhatsAppTextReplyJob skipped duplicate reply', [
                'message_id' => $this->messageId,
                'sender' => explode('@', $this->senderNumber)[0] ?: $this->senderNumber,
            ]);

            return;
        }

        if (! PhoneNumber::isAllowedWhatsAppSender($this->senderNumber)) {
            Log::info('ProcessWhatsAppTextReplyJob skipped non-allowlisted sender', [
                'sender' => explode('@', $this->senderNumber)[0] ?: $this->senderNumber,
            ]);

            return;
        }

        $reply = $this->buildReply($this->originalText);
        $result = $waService->sendMessageResult($this->senderNumber, $reply);

        if (! $result->ok) {
            throw new RuntimeException('Unable to send the WhatsApp text reply: '.$result->reason);
        }

        Cache::put(
            WhatsAppProcessingJobKey::textReplySentCacheKey($this->messageId),
            true,
            WhatsAppProcessingJobKey::uniqueForSeconds(),
        );
    }

    protected function buildReply(string $originalText): string
    {
        $spendingCommand = WhatsAppSpendingCommandParser::parse($originalText);

        if ($spendingCommand !== null) {
            $familyMemberId = ExpenseSenderAttribution::familyMemberIdForSender($this->senderNumber);
            $spenderScope = $spendingCommand['scope'] === WhatsAppSpendingCommandParser::SCOPE_ALL
                ? new DashboardSpenderScope(DashboardSpenderScope::ALL)
                : ($familyMemberId === null
                    ? new DashboardSpenderScope(DashboardSpenderScope::PRIMARY)
                    : new DashboardSpenderScope(DashboardSpenderScope::familyValue($familyMemberId)));

            return (new WhatsAppSpendingReplyBuilder(
                $spendingCommand['month'],
                $spendingCommand['mode'],
                $spenderScope,
                $familyMemberId,
            ))->build();
        }

        $text = strtolower(trim($originalText));

        if (str_contains($text, 'finance others')) {
            return WhatsAppMessage::financeKeywords();
        }

        if (str_contains($text, 'manual way') || preg_match('/\bmanual\b/u', $text) === 1) {
            return WhatsAppMessage::manualApproach();
        }

        return WhatsAppMessage::help();
    }
}
