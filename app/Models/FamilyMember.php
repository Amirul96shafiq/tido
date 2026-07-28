<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FamilyRelationship;
use App\Support\PhoneNumber;
use Database\Factories\FamilyMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class FamilyMember extends Model
{
    /** @use HasFactory<FamilyMemberFactory> */
    use HasFactory;

    protected $attributes = [
        'allowlist_enabled' => true,
        'login_enabled' => false,
    ];

    protected $fillable = [
        'name',
        'display_name',
        'avatar_url',
        'phone',
        'email',
        'relationship',
        'relationship_other',
        'date_of_birth',
        'allowlist_enabled',
        'login_enabled',
    ];

    protected $casts = [
        'allowlist_enabled' => 'boolean',
        'login_enabled' => 'boolean',
        'relationship' => FamilyRelationship::class,
        'date_of_birth' => 'date:Y-m-d',
    ];

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value): ?string => PhoneNumber::normalize($value),
        );
    }

    public function relationshipLabel(): ?string
    {
        if ($this->relationship === null) {
            return null;
        }

        if ($this->relationship === FamilyRelationship::Other) {
            return filled($this->relationship_other)
                ? (string) $this->relationship_other
                : FamilyRelationship::Other->label();
        }

        return $this->relationship->label();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url
            ? Storage::disk('public')->url($this->avatar_url)
            : null;
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAllowlisted(Builder $query): Builder
    {
        return $query->where('allowlist_enabled', true);
    }

    /**
     * @return HasOne<User, $this>
     */
    public function loginUser(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
