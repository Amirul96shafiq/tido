<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
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
        'total_amount',
        'currency',
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
        'total_amount' => 'decimal:2',
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
     * @param  Builder<Invoice>  $query
     */
    public function scopeInPeriod(Builder $query, CarbonInterface $start, CarbonInterface $end): void
    {
        $query->whereBetween('date_time', [$start, $end]);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
