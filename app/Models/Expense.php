<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\TracksResourceEdits;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Expense extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, TracksResourceEdits;

    public const CURRENCY_MYR = 'MYR';

    public const CURRENCY_UNKNOWN = 'UNK';

    public const CONVERSION_NOT_REQUIRED = 'not_required';

    public const CONVERSION_CONVERTED = 'converted';

    public const CONVERSION_PENDING = 'pending';

    public const CONVERSION_FAILED = 'failed';

    /**
     * @var list<string>
     */
    public const CANONICAL_CONVERSION_STATUSES = [
        self::CONVERSION_NOT_REQUIRED,
        self::CONVERSION_CONVERTED,
    ];

    protected $attributes = [
        'currency' => self::CURRENCY_MYR,
        'currency_conversion_status' => self::CONVERSION_NOT_REQUIRED,
    ];

    protected $fillable = [
        'merchant_name',
        'invoice_number',
        'receipt_hash',
        'date_time',
        'subtotal',
        'total_tax',
        'discount_total',
        'rounding_amount',
        'total_amount',
        'currency',
        'original_currency',
        'original_total_amount',
        'currency_conversion_status',
        'currency_conversion_rate',
        'currency_conversion_date',
        'currency_conversion_provider',
        'currency_conversion_fetched_at',
        'payment_method_id',
        'source',
        'whatsapp_sender',
        'whatsapp_message_id',
        'family_member_id',
        'status',
        'google_drive_file_id',
        'original_filename',
        'image_path',
        'file_mime_type',
        'file_page_count',
        'raw_ai_response',
        'notes',
    ];

    protected $casts = [
        'date_time' => 'datetime',
        'subtotal' => 'decimal:2',
        'total_tax' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'rounding_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'original_total_amount' => 'decimal:2',
        'currency_conversion_rate' => 'decimal:10',
        'currency_conversion_date' => 'date',
        'currency_conversion_fetched_at' => 'datetime',
        'file_page_count' => 'integer',
        'raw_ai_response' => 'array',
    ];

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
    }

    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeProcessed(Builder $query): void
    {
        $query->whereIn('status', ['parsed', 'reviewed']);
    }

    /**
     * Expenses with AI-extracted line items that should appear on dashboard analytics.
     *
     * @param  Builder<Expense>  $query
     */
    public function scopeDashboardAnalyticsEligible(Builder $query): void
    {
        $query
            ->whereIn('status', self::dashboardAnalyticsStatuses())
            ->canonicalMyr();
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeCanonicalMyr(Builder $query): void
    {
        $query
            ->where('currency', self::CURRENCY_MYR)
            ->whereIn('currency_conversion_status', self::CANONICAL_CONVERSION_STATUSES);
    }

    public function isCanonicalMyr(): bool
    {
        return $this->currency === self::CURRENCY_MYR
            && in_array($this->currency_conversion_status, self::CANONICAL_CONVERSION_STATUSES, true);
    }

    public function displayCurrency(): ?string
    {
        if ($this->isCanonicalMyr()) {
            return self::CURRENCY_MYR;
        }

        if (filled($this->original_currency)) {
            return strtoupper((string) $this->original_currency);
        }

        if (filled($this->currency) && $this->currency !== self::CURRENCY_UNKNOWN) {
            return strtoupper((string) $this->currency);
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function dashboardAnalyticsStatuses(): array
    {
        return ['parsed', 'reviewed', 'requires_manual_review'];
    }

    /**
     * @param  Builder<Expense>  $query
     */
    public function scopeInPeriod(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->whereBetween('date_time', [$start, $end]);
    }

    public function fileUrl(): ?string
    {
        if (blank($this->image_path)) {
            return null;
        }

        if (Storage::exists($this->image_path)) {
            return Storage::temporaryUrl($this->image_path, now()->addMinutes(30));
        }

        if (Storage::disk('public')->exists($this->image_path)) {
            return Storage::disk('public')->url($this->image_path);
        }

        return null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
