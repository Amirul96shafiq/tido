<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods;

use App\Filament\Concerns\RequiresPrimaryHouseholdAccess;
use App\Filament\Resources\PaymentMethods\Pages\CreatePaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\EditPaymentMethod;
use App\Filament\Resources\PaymentMethods\Pages\ListPaymentMethods;
use App\Filament\Resources\PaymentMethods\Schemas\PaymentMethodForm;
use App\Filament\Resources\PaymentMethods\Tables\PaymentMethodsTable;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodDuplicator;
use Filament\Actions\BulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PaymentMethodResource extends Resource
{
    use RequiresPrimaryHouseholdAccess;

    protected static ?string $model = PaymentMethod::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = true;

    protected static ?int $globalSearchSort = 3;

    protected static ?string $slug = 'payment-methods';

    protected static ?string $navigationLabel = 'Payment Methods';

    protected static ?string $modelLabel = 'Payment Method';

    protected static ?string $pluralModelLabel = 'Payment Methods';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return PaymentMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentMethodsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentMethods::route('/'),
            'create' => CreatePaymentMethod::route('/create'),
            'edit' => EditPaymentMethod::route('/{record}/edit'),
        ];
    }

    public static function duplicateAction(): ReplicateAction
    {
        return ReplicateAction::make()
            ->label('Duplicate')
            ->requiresConfirmation()
            ->modalHeading(fn (PaymentMethod $record): string => 'Duplicate '.$record->name)
            ->modalDescription('Creates a user payment method with the same appearance, aliases, and notes. Expenses are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->successNotificationTitle('Payment method duplicated')
            ->excludeAttributes(PaymentMethodDuplicator::EXCLUDED_ATTRIBUTES)
            ->beforeReplicaSaved(function (Model $replica): void {
                /** @var PaymentMethod $replica */
                app(PaymentMethodDuplicator::class)->prepareReplica($replica);
            })
            ->successRedirectUrl(fn (Model $replica): string => static::getUrl('edit', [
                'record' => $replica,
            ]));
    }

    public static function duplicateBulkAction(): BulkAction
    {
        return BulkAction::make('duplicate')
            ->label('Duplicate')
            ->icon(Heroicon::Square2Stack)
            ->requiresConfirmation()
            ->modalHeading('Duplicate selected payment methods')
            ->modalDescription('Creates user payment methods with the same appearance, aliases, and notes. Expenses are not copied.')
            ->modalSubmitActionLabel('Duplicate')
            ->deselectRecordsAfterCompletion()
            ->successNotificationTitle(function (Collection $records): string {
                $count = $records->count();

                return $count === 1
                    ? '1 payment method duplicated'
                    : "{$count} payment methods duplicated";
            })
            ->action(function (Collection $records, PaymentMethodDuplicator $duplicator): void {
                $duplicator->duplicateMany($records);
            });
    }

    /**
     * @return list<string>
     */
    public static function getGloballySearchableAttributes(): array
    {
        return [
            'name',
            'slug',
            'notes',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Slug' => (string) $record->slug,
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
