<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Models\ContentDraft;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;

trait RecoversContentDraft
{
    /**
     * Snapshot of the form after fill, used to skip drafts that match the loaded record.
     *
     * @var array<string, mixed>|null
     */
    public ?array $contentDraftReferenceState = null;

    abstract protected function contentDraftKey(): string;

    /**
     * Fields that must not be persisted in drafts (e.g. Livewire temp uploads).
     *
     * @return list<string>
     */
    protected function contentDraftExcludedFields(): array
    {
        return ['image_path'];
    }

    protected function afterFill(): void
    {
        $this->captureContentDraftReferenceState();
        $this->offerContentDraftRecovery();
    }

    protected function afterCreate(): void
    {
        $this->clearContentDraft();
    }

    protected function afterSave(): void
    {
        $this->clearContentDraft();
    }

    public function saveDraft(): void
    {
        $data = $this->getContentDraftPayload();

        if (! $this->contentDraftHasMeaningfulContent($data)) {
            return;
        }

        if ($this->matchesContentDraftReferenceState($data)) {
            $this->clearContentDraft();

            return;
        }

        ContentDraft::query()->updateOrCreate(
            [
                'user_id' => Auth::id(),
                'key' => $this->contentDraftKey(),
            ],
            [
                'payload' => $data,
            ],
        );

        $this->dispatch('content-draft-saved');
    }

    #[On('restore-content-draft')]
    public function restoreContentDraft(): void
    {
        $draft = $this->findContentDraft();

        if ($draft === null) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($draft->payload) ? $draft->payload : [];
        $payload = $this->sanitizeContentDraftPayload($payload);

        foreach ($this->contentDraftExcludedFields() as $field) {
            $payload[$field] = $this->data[$field] ?? null;
        }

        $this->form->fill($payload);

        $this->dispatch('close-notification', id: 'content-draft-recovery');

        Notification::make()
            ->title('Draft restored')
            ->success()
            ->send();
    }

    #[On('discard-content-draft')]
    public function discardContentDraft(): void
    {
        $this->clearContentDraft();

        $this->dispatch('close-notification', id: 'content-draft-recovery');

        Notification::make()
            ->title('Draft discarded')
            ->success()
            ->send();
    }

    public function clearContentDraft(): void
    {
        ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $this->contentDraftKey())
            ->delete();
    }

    protected function captureContentDraftReferenceState(): void
    {
        $this->contentDraftReferenceState = $this->getContentDraftPayload();
    }

    protected function offerContentDraftRecovery(): void
    {
        $draft = $this->findContentDraft();

        if ($draft === null) {
            return;
        }

        /** @var array<string, mixed> $payload */
        $payload = is_array($draft->payload) ? $draft->payload : [];
        $payload = $this->sanitizeContentDraftPayload($payload);

        if (! $this->contentDraftHasMeaningfulContent($payload)) {
            $this->clearContentDraft();

            return;
        }

        if ($this->matchesContentDraftReferenceState($payload)) {
            $this->clearContentDraft();

            return;
        }

        Notification::make('content-draft-recovery')
            ->title('Unsaved draft found')
            ->body('Would you like to restore your previous work?')
            ->warning()
            ->persistent()
            ->actions([
                Action::make('restore')
                    ->button()
                    ->label('Restore')
                    ->dispatch('restore-content-draft'),
                Action::make('discard')
                    ->label('Discard')
                    ->color('gray')
                    ->dispatch('discard-content-draft'),
            ])
            ->send();
    }

    protected function findContentDraft(): ?ContentDraft
    {
        return ContentDraft::query()
            ->where('user_id', Auth::id())
            ->where('key', $this->contentDraftKey())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getContentDraftPayload(): array
    {
        return $this->sanitizeContentDraftPayload($this->data ?? []);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sanitizeContentDraftPayload(array $data): array
    {
        foreach ($this->contentDraftExcludedFields() as $field) {
            unset($data[$field]);
        }

        return $data;
    }

    /**
     * True when the payload has any non-empty scalar/nested value.
     * Create/edit pages still skip unchanged forms via reference-state matching
     * (so schema defaults alone do not persist as drafts).
     *
     * @param  array<string, mixed>  $data
     */
    protected function contentDraftHasMeaningfulContent(array $data): bool
    {
        return $this->contentDraftValueIsMeaningful($data);
    }

    protected function contentDraftValueIsMeaningful(mixed $value): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if ($this->contentDraftValueIsMeaningful($item)) {
                    return true;
                }
            }

            return false;
        }

        if (is_bool($value)) {
            return true;
        }

        return filled($value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function matchesContentDraftReferenceState(array $data): bool
    {
        if ($this->contentDraftReferenceState === null) {
            return false;
        }

        return $this->normalizeContentDraftState($data)
            === $this->normalizeContentDraftState($this->contentDraftReferenceState);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function normalizeContentDraftState(array $data): string
    {
        $normalized = $this->sanitizeContentDraftPayload($data);
        $normalized = $this->sortContentDraftKeysRecursive($normalized);

        return json_encode($normalized, JSON_THROW_ON_ERROR);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function sortContentDraftKeysRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->sortContentDraftKeysRecursive($value);
            }
        }

        ksort($data);

        return $data;
    }
}
