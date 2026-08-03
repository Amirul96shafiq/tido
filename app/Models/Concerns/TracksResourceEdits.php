<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait TracksResourceEdits
{
    protected static function bootTracksResourceEdits(): void
    {
        static::creating(function (Model $model): void {
            /** @var self $model */
            $model->stampEditor();
        });

        static::updating(function (Model $model): void {
            /** @var self $model */
            $model->stampEditor();
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    protected function stampEditor(): void
    {
        $user = Auth::user();

        $this->setAttribute(
            'edited_by',
            $user instanceof User ? $user->getKey() : null,
        );
    }
}
