<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WhatsAppConnectionEvent;
use Database\Factories\WhatsAppConnectionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppConnectionLog extends Model
{
    /** @use HasFactory<WhatsAppConnectionLogFactory> */
    use HasFactory;

    protected $table = 'whatsapp_connection_logs';

    protected $fillable = [
        'event',
        'status',
        'connected_number',
        'profile_name',
        'instance_name',
        'message',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WhatsAppConnectionEvent::class,
            'meta' => 'array',
        ];
    }
}
