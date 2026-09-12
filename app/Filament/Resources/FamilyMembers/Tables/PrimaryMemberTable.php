<?php

declare(strict_types=1);

namespace App\Filament\Resources\FamilyMembers\Tables;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Support\PrimaryOnlyMutationAuthorization;
use App\Models\User;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\AvatarProviders\UiAvatarsProvider;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class PrimaryMemberTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->deferLoading()
            ->heading('Primary Member')
            ->extraAttributes(['class' => 'tido-primary-member-table'])
            ->queryStringIdentifier('primaryMember')
            ->query(
                User::query()->whereKey(PhoneNumber::primaryUser()?->id),
            )
            ->recordClasses(fn (User $record): array => array_values(array_filter([
                'tido-primary-member-row',
                $record->profile_banner ? 'has-profile-banner' : 'no-profile-banner',
                $record->profile_banner ? ('primary-member-banner-'.$record->id) : null,
            ])))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->formatStateUsing(function (int|string $state, User $record): HtmlString {
                        $bannerUrl = $record->getProfileBannerUrl();

                        if (! $bannerUrl) {
                            return new HtmlString(e((string) $state));
                        }

                        $escapedUrl = addcslashes($bannerUrl, "'\\");
                        $style = sprintf(
                            '<style>'
                            .'.tido-primary-member-table .fi-ta-table > tbody > tr.fi-ta-row.primary-member-banner-%d,'
                            .'.tido-primary-member-table .fi-ta-row.primary-member-banner-%d {'
                            .'    background-image: linear-gradient(90deg, rgba(255, 255, 255, 0.88) 0%%, rgba(255, 255, 255, 0.72) 35%%, rgba(255, 255, 255, 0.72) 65%%, rgba(255, 255, 255, 0.88) 100%%), url(\'%s\') !important;'
                            .'    background-size: cover !important;'
                            .'    background-position: center !important;'
                            .'    background-repeat: no-repeat !important;'
                            .'}'
                            .'.tido-primary-member-table .fi-ta-table > tbody > tr.fi-ta-row.primary-member-banner-%d:hover,'
                            .'.tido-primary-member-table .fi-ta-row.primary-member-banner-%d:hover {'
                            .'    background-image: linear-gradient(90deg, rgba(255, 255, 255, 0.78) 0%%, rgba(255, 255, 255, 0.58) 35%%, rgba(255, 255, 255, 0.58) 65%%, rgba(255, 255, 255, 0.78) 100%%), url(\'%s\') !important;'
                            .'}'
                            .'.dark .tido-primary-member-table .fi-ta-table > tbody > tr.fi-ta-row.primary-member-banner-%d,'
                            .'.dark .tido-primary-member-table .fi-ta-row.primary-member-banner-%d {'
                            .'    background-image: linear-gradient(90deg, rgba(15, 23, 42, 0.78) 0%%, rgba(15, 23, 42, 0.50) 35%%, rgba(15, 23, 42, 0.50) 65%%, rgba(15, 23, 42, 0.78) 100%%), url(\'%s\') !important;'
                            .'}'
                            .'.dark .tido-primary-member-table .fi-ta-table > tbody > tr.fi-ta-row.primary-member-banner-%d:hover,'
                            .'.dark .tido-primary-member-table .fi-ta-row.primary-member-banner-%d:hover {'
                            .'    background-image: linear-gradient(90deg, rgba(15, 23, 42, 0.65) 0%%, rgba(15, 23, 42, 0.38) 35%%, rgba(15, 23, 42, 0.38) 65%%, rgba(15, 23, 42, 0.65) 100%%), url(\'%s\') !important;'
                            .'}'
                            .'</style>',
                            $record->id,
                            $record->id,
                            $escapedUrl,
                            $record->id,
                            $record->id,
                            $escapedUrl,
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
                PrimaryOnlyMutationAuthorization::apply(
                    Action::make('edit')
                        ->label('Edit')
                        ->icon(Heroicon::PencilSquare)
                        ->url(EditProfile::getUrl()),
                ),
            ]);
    }
}
