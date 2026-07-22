<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\FamilyMemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    /** @use HasFactory<FamilyMemberFactory> */
    use HasFactory;

    protected $attributes = [
        'allowlist_enabled' => true,
    ];

    protected $fillable = [
        'name',
        'phone',
        'allowlist_enabled',
    ];

    protected $casts = [
        'allowlist_enabled' => 'boolean',
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

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAllowlisted(Builder $query): Builder
    {
        return $query->where('allowlist_enabled', true);
    }
}
