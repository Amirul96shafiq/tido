<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Tables;

use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Models\FamilyMember;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class FamilyMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar_url')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (FamilyMember $record): string => app(UiAvatarsProvider::class)->get($record)),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->limit(24)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    }),

                TextColumn::make('phone')
                    ->label('WhatsApp')
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono),

                ToggleColumn::make('allowlist_enabled')
                    ->label('Allowlist')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No family members yet')
            ->emptyStateDescription('Add family WhatsApp numbers to include them in the bot contact allowlist.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->emptyStateActions([
                Action::make('create')
                    ->label('New family member')
                    ->icon(Heroicon::Plus)
                    ->url(FamilyMemberResource::getUrl('create'))
                    ->button(),
            ]);
    }
}
