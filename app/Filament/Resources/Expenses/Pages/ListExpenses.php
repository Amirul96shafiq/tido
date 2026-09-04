<?php

declare(strict_types=1);

namespace App\Filament\Resources\Expenses\Pages;

use App\Filament\Concerns\PrependsHomeBreadcrumb;
use App\Filament\Concerns\RefreshesTableOnExpenseBroadcast;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Tables\ExpensesTable;
use App\Models\Expense;
use App\Support\HouseholdAccess;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ListExpenses extends ListRecords
{
    use PrependsHomeBreadcrumb;
    use RefreshesTableOnExpenseBroadcast;

    protected static string $resource = ExpenseResource::class;

    public function updateExpenseInlineSelect(string $attribute, string $recordKey, mixed $value = null): void
    {
        if (! in_array($attribute, ['status', 'family_member_id'], true)) {
            return;
        }

        $expense = Expense::query()->find($recordKey);

        if (! $expense instanceof Expense) {
            return;
        }

        if (! HouseholdAccess::canMutateExpense($expense)) {
            return;
        }

        if ($attribute === 'family_member_id' && HouseholdAccess::isFamilyMember()) {
            return;
        }

        if ($attribute === 'status') {
            $this->updateInlineStatus($expense, $value);

            return;
        }

        $this->updateInlineUploadedBy($expense, $value);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    private function updateInlineStatus(Expense $expense, mixed $value): void
    {
        $statusOptions = ExpensesTable::statusOptions();
        $nextStatus = is_string($value) ? $value : null;

        try {
            validator(
                ['status' => $nextStatus],
                ['status' => ['required', Rule::in(array_keys($statusOptions))]],
            )->validate();
        } catch (ValidationException) {
            return;
        }

        $previousStatus = (string) $expense->status;

        if ($previousStatus === $nextStatus) {
            return;
        }

        $expense->status = $nextStatus;
        $expense->save();

        Notification::make()
            ->title('Status Updated')
            ->body("Expense ID {$expense->getKey()}'s status changed from ".($statusOptions[$previousStatus] ?? $previousStatus).' to '.($statusOptions[$nextStatus] ?? $nextStatus).'.')
            ->success()
            ->send();
    }

    private function updateInlineUploadedBy(Expense $expense, mixed $value): void
    {
        $nextId = filled($value) ? (int) $value : null;

        try {
            validator(
                ['family_member_id' => $nextId],
                ['family_member_id' => ['nullable', Rule::exists('family_members', 'id')]],
            )->validate();
        } catch (ValidationException) {
            return;
        }

        $previousId = $expense->family_member_id !== null ? (int) $expense->family_member_id : null;

        if ($previousId === $nextId) {
            return;
        }

        $expense->family_member_id = $nextId;
        $expense->save();

        Notification::make()
            ->title('Uploaded By Updated')
            ->body("Expense ID {$expense->getKey()}'s uploaded by changed from ".ExpensesTable::uploadedByLabel($previousId).' to '.ExpensesTable::uploadedByLabel($nextId).'.')
            ->success()
            ->send();
    }
}
