<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Schemas;

use App\Models\FamilyMember;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;

class FamilyMemberInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Family Member Details')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('avatar_url')
                            ->hiddenLabel()
                            ->disk('public')
                            ->circular()
                            ->defaultImageUrl(fn (FamilyMember $record): string => app(UiAvatarsProvider::class)->get($record))
                            ->columnSpanFull(),
                        TextEntry::make('name'),
                        TextEntry::make('phone')
                            ->label('WhatsApp Number')
                            ->fontFamily(FontFamily::Mono),
                        IconEntry::make('allowlist_enabled')
                            ->label('Include in contact allowlist')
                            ->boolean()
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
