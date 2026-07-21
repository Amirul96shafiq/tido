<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvolutionApiConnectionEvent;
use App\Enums\EvolutionApiConnectMethod;
use Database\Factories\EvolutionApiConnectionLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EvolutionApiConnectionLog extends Model
{
    /** @use HasFactory<EvolutionApiConnectionLogFactory> */
    use HasFactory;

    protected $table = 'evolution_api_connection_logs';

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
            'event' => EvolutionApiConnectionEvent::class,
            'meta' => 'array',
        ];
    }

    public function connectMethod(): ?EvolutionApiConnectMethod
    {
        $value = data_get($this->meta, 'connect_method');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return EvolutionApiConnectMethod::tryFrom($value);
    }
}
