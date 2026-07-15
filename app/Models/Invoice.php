<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;

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
        'payment_method',
        'source',
        'status',
        'google_drive_file_id',
        'original_filename',
        'image_path',
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
        'payment_method' => PaymentMethod::class,
        'raw_ai_response' => 'array',
    ];

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * @param  Builder<Invoice>  $query
     */
    public function scopeProcessed(Builder $query): void
    {
        $query->whereIn('status', ['parsed', 'reviewed']);
    }

    /**
     * Invoices with AI-extracted line items that should appear on dashboard analytics.
     *
     * @param  Builder<Invoice>  $query
     */
    public function scopeDashboardAnalyticsEligible(Builder $query): void
    {
        $query->whereIn('status', self::dashboardAnalyticsStatuses());
    }

    /**
     * @return list<string>
     */
    public static function dashboardAnalyticsStatuses(): array
    {
        return ['parsed', 'reviewed', 'requires_manual_review'];
    }

    /**
     * @param  Builder<Invoice>  $query
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
