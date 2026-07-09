<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserDateFormat;
use Carbon\CarbonInterface;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasLocalePreference
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'avatar_url',
        'phone',
        'timezone',
        'locale',
        'date_format',
        'notify_budget_alerts',
        'notify_profile_updates',
        'notify_email_digest',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'notify_budget_alerts' => 'boolean',
            'notify_profile_updates' => 'boolean',
            'notify_email_digest' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url
            ? Storage::disk('public')->url($this->avatar_url)
            : null;
    }

    public function preferredLocale(): string
    {
        return $this->locale ?? 'en';
    }

    public function preferredTimezone(): string
    {
        return $this->timezone ?? 'Asia/Kuala_Lumpur';
    }

    public function preferredDateFormat(): string
    {
        return $this->date_format ?? UserDateFormat::DmySlash->value;
    }

    public function preferredDateTimeFormat(): string
    {
        return $this->preferredDateFormat().' H:i';
    }

    public function formatDate(CarbonInterface $date): string
    {
        return $date->format($this->preferredDateFormat());
    }

    public function formatDateTime(CarbonInterface $date): string
    {
        return $date
            ->timezone($this->preferredTimezone())
            ->format($this->preferredDateTimeFormat());
    }
}
