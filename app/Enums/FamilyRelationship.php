<?php

declare(strict_types=1);

namespace App\Enums;

enum FamilyRelationship: string
{
    case Spouse = 'spouse';
    case Partner = 'partner';
    case Parent = 'parent';
    case Child = 'child';
    case Sibling = 'sibling';
    case Grandparent = 'grandparent';
    case Grandchild = 'grandchild';
    case Uncle = 'uncle';
    case Aunt = 'aunt';
    case Cousin = 'cousin';
    case InLaw = 'in_law';
    case Friend = 'friend';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'Spouse',
            self::Partner => 'Partner',
            self::Parent => 'Parent',
            self::Child => 'Child',
            self::Sibling => 'Sibling',
            self::Grandparent => 'Grandparent',
            self::Grandchild => 'Grandchild',
            self::Uncle => 'Uncle',
            self::Aunt => 'Aunt',
            self::Cousin => 'Cousin',
            self::InLaw => 'In-law',
            self::Friend => 'Friend',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
