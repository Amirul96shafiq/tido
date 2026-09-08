<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Tables;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class PrimaryMemberTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Primary Member')
            ->extraAttributes(['class' => 'tido-primary-member-table'])
            ->queryStringIdentifier('primaryMember')
            ->query(
                User::query()->whereKey(Auth::id()),
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

                ImageColumn::make('avatar_url')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->imageSize(64)
                    ->defaultImageUrl(fn (User $record): string => app(UiAvatarsProvider::class)->get($record)),

                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->sortable()
                    ->placeholder('—')
                    ->limit(24)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->sortable()
                    ->fontFamily(FontFamily::Mono),

                TextColumn::make('date_of_birth')
                    ->label('Date of Birth')
                    ->date()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Edited At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->searchable(false)
            ->columnManager(false)
            ->selectable()
            ->paginated(false)
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon(Heroicon::PencilSquare)
                    ->url(EditProfile::getUrl()),
            ]);
    }
}
