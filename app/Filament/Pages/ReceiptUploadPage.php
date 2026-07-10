<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Invoice;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class ReceiptUploadPage extends Page implements HasForms, HasTable
{
    use InteractsWithForms, InteractsWithTable;

    protected string $view = 'filament.pages.receipt-upload-page';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-on-square';

    protected static ?string $navigationLabel = 'Upload Receipts';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?string $title = 'Upload Receipts';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                FileUpload::make('receipts')
                    ->label('Select or Drop Receipt Images')
                    ->multiple()
                    ->image()
                    ->maxSize(10240)
                    ->directory('receipts')
                    ->required(),
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        foreach ($state['receipts'] as $filePath) {
            Invoice::create([
                'merchant_name' => 'Pending AI Extraction...',
                'date_time' => now(),
                'subtotal' => 0.00,
                'total_tax' => 0.00,
                'total_amount' => 0.00,
                'currency' => 'MYR',
                'source' => 'manual',
                'status' => 'pending',
                'image_path' => $filePath,
                'original_filename' => basename($filePath),
            ]);
        }

        $this->form->fill();

        Notification::make()
            ->title('Receipts uploaded successfully')
            ->body('AI extraction queue has started processing them.')
            ->success()
            ->send();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Invoice::query())
            ->defaultSort('created_at', 'desc')
            ->poll('5s')
            ->columns([
                TextColumn::make('original_filename')
                    ->label('Filename')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->color(fn (Invoice $record): ?string => filled($record->image_path) ? 'primary' : null)
                    ->tooltip(fn (Invoice $record): ?string => filled($record->image_path) ? 'View file' : null)
                    ->url(
                        fn (Invoice $record): ?string => $this->receiptFileUrl($record),
                        shouldOpenInNewTab: true,
                    ),

                TextColumn::make('merchant_name')
                    ->label('Merchant')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Uploaded At')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->money('MYR')
                    ->sortable(),

                TextColumn::make('source')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'info',
                        'whatsapp' => 'success',
                        'google_drive' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'parsed' => 'info',
                        'reviewed' => 'success',
                        'requires_manual_review' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending Parsing',
                        'parsed' => 'Parsed by AI',
                        'reviewed' => 'Reviewed',
                        'requires_manual_review' => 'Requires Manual Review',
                        'failed' => 'Parsing Failed',
                    ])
                    ->searchable(),

                SelectFilter::make('source')
                    ->options([
                        'manual' => 'Manual',
                        'whatsapp' => 'WhatsApp',
                        'google_drive' => 'Google Drive',
                    ])
                    ->searchable(),
            ]);
    }

    protected function receiptFileUrl(Invoice $record): ?string
    {
        if (blank($record->image_path)) {
            return null;
        }

        if (Storage::exists($record->image_path)) {
            return Storage::temporaryUrl($record->image_path, now()->addMinutes(30));
        }

        if (Storage::disk('public')->exists($record->image_path)) {
            return Storage::disk('public')->url($record->image_path);
        }

        return null;
    }
}
