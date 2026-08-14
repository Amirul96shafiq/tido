<?php

declare(strict_types=1);

namespace App\Filament\Resources\PaymentMethods\Schemas;

use App\Filament\Forms\Components\IconPicker;
use App\Filament\Forms\Components\NotesRichEditor;
use App\Support\FieldCharacterLimits;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

class PaymentMethodForm
{
    /**
     * @return list<array{label: string, id: string}>
     */
    public static function sectionNavItems(): array
    {
        return [
            ['label' => 'Payment Method Appearance', 'id' => 'payment-method-appearance'],
            ['label' => 'Payment Method Details', 'id' => 'payment-method-details'],
            ['label' => 'Payment Method Notes', 'id' => 'payment-method-notes'],
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Grid::make(1)
                    ->columnSpan(2)
                    ->columnOrder([
                        'default' => 2,
                        'lg' => 1,
                    ])
                    ->extraAttributes(['class' => 'fi-payment-method-main-column'])
                    ->schema([
                        Section::make(fn (LivewireComponent $livewire): string => self::modelLabel($livewire).' Details')
                            ->id('payment-method-details')
                            ->columns(2)
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->characterLimit(FieldCharacterLimits::PAYMENT_METHOD_NAME)
                                    ->placeholder('Payment method name')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn ($state, callable $set) => $set('slug', Str::slug($state ?? ''))),

                                TextInput::make('slug')
                                    ->required()
                                    ->placeholder('payment-method-slug')
                                    ->disabled(fn ($record) => (bool) ($record?->is_system ?? false))
                                    ->dehydrated()
                                    ->unique(table: 'payment_methods', column: 'slug', ignoreRecord: true),

                                TagsInput::make('aliases')
                                    ->label('Aliases')
                                    ->placeholder('Add alias (e.g. grabpay)')
                                    ->helperText('Used for OCR and WhatsApp token matching. Normalized to lowercase.')
                                    ->columnSpanFull()
                                    ->splitKeys(['Tab', 'Enter', ','])
                                    ->reorderable(),
                            ]),

                        Section::make('Payment Method Notes')
                            ->id('payment-method-notes')
                            ->schema([
                                NotesRichEditor::make('notes')
                                    ->label('Payment Method Notes')
                                    ->hiddenLabel()
                                    ->columnSpanFull(),
                            ]),
                    ]),

                Grid::make(1)
                    ->columnSpan(1)
                    ->columnOrder([
                        'default' => 1,
                        'lg' => 2,
                    ])
                    ->extraAttributes(['class' => 'fi-payment-method-sidebar-sticky'])
                    ->schema([
                        Section::make(fn (LivewireComponent $livewire): string => self::modelLabel($livewire).' Appearance')
                            ->id('payment-method-appearance')
                            ->schema([
                                View::make('filament.forms.components.label-icon-preview')
                                    ->viewData(fn (Get $get, LivewireComponent $livewire): array => [
                                        'icon' => filled($get('icon')) ? (string) $get('icon') : 'heroicon-o-credit-card',
                                        'color' => filled($get('color')) ? (string) $get('color') : '#a1a1aa',
                                        'name' => filled($get('name'))
                                            ? (string) $get('name')
                                            : self::modelLabel($livewire).' preview',
                                    ]),

                                IconPicker::make('icon')
                                    ->label('Icon')
                                    ->live(),

                                ColorPicker::make('color')
                                    ->live(),
                            ]),
                    ]),
            ]);
    }

    protected static function modelLabel(LivewireComponent $livewire): string
    {
        if ($livewire instanceof ResourcePage) {
            return $livewire::getResource()::getTitleCaseModelLabel();
        }

        return 'Record';
    }
}
