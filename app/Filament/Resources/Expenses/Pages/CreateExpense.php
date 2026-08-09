<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Concerns\HasStickyBlurFormActions;
use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RecoversContentDraft;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Schemas\ExpenseForm;
use App\Models\User;
use App\Support\HouseholdAccess;
use Filament\Resources\Pages\CreateRecord;

class CreateExpense extends CreateRecord
{
    use HasStickyBlurFormActions;
    use PrependsHomeBreadcrumb;
    use RecoversContentDraft;

    protected static string $resource = ExpenseResource::class;

    /**
     * @return array<string>
     */
    public function getPageClasses(): array
    {
        return [
            ...parent::getPageClasses(),
            'fi-expense-form-page',
        ];
    }

    protected function contentDraftKey(): string
    {
        return 'expense-create';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! HouseholdAccess::isFamilyMember()) {
            return $data;
        }

        $user = HouseholdAccess::user();

        if ($user instanceof User && $user->family_member_id !== null) {
            $data['family_member_id'] = $user->family_member_id;
        }

        return $data;
    }

    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return ExpenseForm::sectionNavItems();
    }

    public function sectionNavAriaLabel(): string
    {
        return 'Expense sections';
    }
}
