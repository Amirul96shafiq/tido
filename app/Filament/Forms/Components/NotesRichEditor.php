<?php

declare(strict_types=1);

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\RichEditor;

/**
 * Shared rich notes field for tido forms (Budget, Invoice, future notes columns).
 *
 * @see docs/ui-notes-rich-editor.md
 */
class NotesRichEditor extends RichEditor
{
    public const EXTRA_CLASS = 'fi-notes-rich-editor';

    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->toolbarButtons([
                ['bold', 'italic', 'underline', 'strike', 'link'],
                ['bulletList', 'orderedList'],
                ['undo', 'redo'],
            ])
            ->extraAttributes(['class' => self::EXTRA_CLASS], merge: true);
    }
}
