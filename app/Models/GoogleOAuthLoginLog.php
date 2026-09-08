<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GoogleOAuthLoginEvent;
use App\Models\Concerns\BelongsToHousehold;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoogleOAuthLoginLog extends Model
{
    use BelongsToHousehold;

    protected $table = 'google_oauth_login_logs';

    protected $fillable = [
        'household_id',
        'event',
        'status',
        'user_id',
        'message',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => GoogleOAuthLoginEvent::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
