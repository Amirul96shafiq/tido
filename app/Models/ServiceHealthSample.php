<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MonitoredService;
use App\Enums\ServiceHealthStatus;
use Illuminate\Database\Eloquent\Model;

class ServiceHealthSample extends Model
{
    protected $fillable = [
        'service',
        'status',
        'checked_at',
        'latency_ms',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'service' => MonitoredService::class,
            'status' => ServiceHealthStatus::class,
            'checked_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
