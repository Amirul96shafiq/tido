<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Tables;

use App\Filament\Resources\FamilyMembers\FamilyMemberResource;
use App\Filament\Support\RecordActionsGroup;
use App\Models\FamilyMember;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class FamilyMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->extraAttributes(['class' => 'tido-family-members-table'])
            ->recordClasses(fn (FamilyMember $record): array => array_values(array_filter([
                'tido-family-member-row',
                $record->profile_banner ? 'has-profile-banner' : 'no-profile-banner',
                $record->profile_banner ? ('family-member-banner-'.$record->id) : null,
            ])))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(function (int|string $state, FamilyMember $record): HtmlString {
                        $bannerUrl = $record->getProfileBannerUrl();

                        if (! $bannerUrl) {
                            return new HtmlString(e((string) $state));
                        }

                        $escapedUrl = addcslashes($bannerUrl, "'\\");
                        $style = sprintf(
                            '<style>'
                            .'.tido-family-members-table .fi-ta-table > tbody > tr.fi-ta-row.family-member-banner-%d,'
                            .'.tido-family-members-table .fi-ta-row.family-member-banner-%d {'
                            .'    background-image: linear-gradient(90deg, rgba(15, 23, 42, 0.78) 0%%, rgba(15, 23, 42, 0.50) 35%%, rgba(15, 23, 42, 0.50) 65%%, rgba(15, 23, 42, 0.78) 100%%), url(\'%s\') !important;'
                            .'    background-size: cover !important;'
                            .'    background-position: center !important;'
                            .'    background-repeat: no-repeat !important;'
                            .'}'
                            .'.tido-family-members-table .fi-ta-table > tbody > tr.fi-ta-row.family-member-banner-%d:hover,'
                            .'.tido-family-members-table .fi-ta-row.family-member-banner-%d:hover {'
                            .'    background-image: linear-gradient(90deg, rgba(15, 23, 42, 0.65) 0%%, rgba(15, 23, 42, 0.38) 35%%, rgba(15, 23, 42, 0.38) 65%%, rgba(15, 23, 42, 0.65) 100%%), url(\'%s\') !important;'
                            .'}'
                            .'</style>',
                            $record->id,
                            $record->id,
                            $escapedUrl,
                            $record->id,
                            $record->id,
                            $escapedUrl,
                        );

                        return new HtmlString($style.e((string) $state));
                    }),

                ImageColumn::make('avatar_url')
                    ->label('')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (FamilyMember $record): string => app(UiAvatarsProvider::class)->get($record)),

                TextColumn::make('name')
                    ->label('Full Name')
                    ->searchable()
                    ->sortable()
                    ->limit(24)
                    ->tooltip(function (TextColumn $column, ?string $state): ?string {
                        if (blank($state) || mb_strlen((string) $state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        return (string) $state;
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('display_name')
                    ->label('Display Name')
                    ->searchable()
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
                    ->searchable()
                    ->sortable()
                    ->fontFamily(FontFamily::Mono)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('relationship')
                    ->label('Relationship')
                    ->formatStateUsing(fn (FamilyMember $record): ?string => $record->relationshipLabel())
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('date_of_birth')
                    ->label('Date of Birth')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                IconColumn::make('allowlist_enabled')
                    ->label('Contact Allowlist')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('login_enabled')
                    ->label('Panel Login')
                    ->boolean()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('editedBy.name')
                    ->label('Edited By')
                    ->formatStateUsing(fn (?string $state, FamilyMember $record): ?string => filled($record->editedBy?->display_name)
                        ? (string) $record->editedBy->display_name
                        : $state)
                    ->placeholder('System')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Edited At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('updated_at', 'desc')
            ->paginated([5])
            ->filters([
                TernaryFilter::make('allowlist_enabled')
                    ->label('Contact Allowlist')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled')
                    ->searchable(),

                TernaryFilter::make('login_enabled')
                    ->label('Panel Login')
                    ->trueLabel('Enabled')
                    ->falseLabel('Disabled')
                    ->searchable(),

                TrashedFilter::make()
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->slideOver()
                    ->extraModalOverlayAttributes(['class' => 'fi-modal-overlay-blur'], merge: true),
                RecordActionsGroup::make([
                    EditAction::make(),
                    FamilyMemberResource::duplicateAction(),
                    DeleteAction::make(),
                    RestoreAction::make(),
                    ForceDeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
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
