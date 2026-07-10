<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserDateFormat;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'notify_whatsapp_connection',
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
            'notify_whatsapp_connection' => 'boolean',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        $allowedPhone = PhoneNumber::normalize(
            is_string(config('services.evolution.personal_number'))
                ? config('services.evolution.personal_number')
                : null,
        );

        if ($allowedPhone === null) {
            return true;
        }

        return PhoneNumber::normalize($this->phone) === $allowedPhone;
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => PhoneNumber::normalize($value),
        );
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
