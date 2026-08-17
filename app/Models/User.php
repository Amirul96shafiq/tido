<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HouseholdRole;
use App\Enums\UserDateFormat;
use App\Support\PhoneNumber;
use Carbon\CarbonInterface;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable implements FilamentUser, HasAvatar, HasLocalePreference
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'display_name',
        'email',
        'password',
        'avatar_url',
        'phone',
        'whatsapp_lid',
        'household_role',
        'family_member_id',
        'date_of_birth',
        'timezone',
        'locale',
        'date_format',
        'notify_budget_alerts',
        'notify_profile_updates',
        'notify_email_digest',
        'notify_evolution_api',
        'notify_recurring_reminders',
        'recurring_reminder_lead_days',
        'recurring_reminder_time',
        'stylized_background_enabled',
    ];

    protected $attributes = [
        'household_role' => 'primary',
        'stylized_background_enabled' => true,
        'notify_recurring_reminders' => true,
        'recurring_reminder_lead_days' => 7,
        'recurring_reminder_time' => '08:00:00',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'date_of_birth' => 'date:Y-m-d',
            'password' => 'hashed',
            'household_role' => HouseholdRole::class,
            'notify_budget_alerts' => 'boolean',
            'notify_profile_updates' => 'boolean',
            'notify_email_digest' => 'boolean',
            'notify_evolution_api' => 'boolean',
            'notify_recurring_reminders' => 'boolean',
            'recurring_reminder_lead_days' => 'integer',
            'stylized_background_enabled' => 'boolean',
        ];
    }

    /**
     * Preferred send-at time as H:i (Profile timezone).
     */
    public function recurringReminderTimeHi(): string
    {
        $raw = $this->recurring_reminder_time;

        if ($raw instanceof CarbonInterface) {
            return $raw->format('H:i');
        }

        $value = is_string($raw) ? $raw : '08:00:00';

        return substr($value, 0, 5);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isPrimary()) {
            return true;
        }

        if (! $this->isFamilyMember() || $this->family_member_id === null) {
            return false;
        }

        $member = $this->familyMember;

        return $member instanceof FamilyMember && $member->login_enabled;
    }

    public function isPrimary(): bool
    {
        return $this->household_role === HouseholdRole::Primary
            || $this->household_role === null;
    }

    public function isFamilyMember(): bool
    {
        return $this->household_role === HouseholdRole::FamilyMember;
    }

    /**
     * @return BelongsTo<FamilyMember, $this>
     */
    public function familyMember(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class);
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
