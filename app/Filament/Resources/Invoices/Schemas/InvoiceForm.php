<?php

declare(strict_types=1);

namespace App\Filament\Resources\Invoices\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                Section::make('Receipt Information')
                    ->description('General invoice details')
                    ->columnSpan('full')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('merchant_name')
                                    ->required(),
                                TextInput::make('invoice_number'),
                                DateTimePicker::make('date_time')
                                    ->required()
                                    ->default(now()),
                            ]),
                        
                        Grid::make(4)
                            ->schema([
                                TextInput::make('subtotal')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->required(),
                                
                                TextInput::make('total_tax')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->default(0.00),
                                
                                TextInput::make('total_amount')
                                    ->numeric()
                                    ->prefix('RM')
                                    ->required(),
                                
                                Select::make('currency')
                                    ->options([
                                        'MYR' => 'MYR (Malaysian Ringgit)',
                                    ])
                                    ->default('MYR')
                                    ->required(),
                            ]),
                        
                        Grid::make(2)
                            ->schema([
                                Select::make('source')
                                    ->options([
                                        'manual' => 'Manual Upload',
                                        'whatsapp' => 'WhatsApp',
                                        'google_drive' => 'Google Drive',
                                    ])
                                    ->default('manual')
                                    ->required(),
                                
                                Select::make('status')
                                    ->options([
                                        'pending' => 'Pending Parsing',
                                        'parsed' => 'Parsed by AI',
                                        'reviewed' => 'Reviewed',
                                        'requires_manual_review' => 'Requires Manual Review',
                                        'failed' => 'Parsing Failed',
                                    ])
                                    ->default('pending')
                                    ->required(),
                            ]),
                    ]),
                
                Section::make('Image & Uploads')
                    ->columnSpan('full')
                    ->schema([
                        FileUpload::make('image_path')
                            ->label('Receipt Image')
                            ->image()
                            ->maxSize(10240)
                            ->directory('receipts')
                            ->visibility('public'),
                        
                        Textarea::make('notes')
                            ->rows(3),
                    ]),

                Section::make('Line Items')
                    ->columnSpan('full')
                    ->schema([
                        Repeater::make('invoiceItems')
                            ->relationship('invoiceItems')
                            ->schema([
                                Grid::make(6)
                                    ->schema([
                                        TextInput::make('description')
                                            ->required()
                                            ->columnSpan(2),
                                        
                                        Select::make('category_id')
                                            ->relationship('category', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->columnSpan(2),
                                        
                                        TextInput::make('quantity')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->columnSpan(1),
                                        
                                        TextInput::make('unit_price')
                                            ->numeric()
                                            ->prefix('RM')
                                            ->required()
                                            ->columnSpan(1),
                                    ]),
                                
                                Grid::make(6)
                                    ->schema([
                                        TextInput::make('line_total')
                                            ->numeric()
                                            ->prefix('RM')
                                            ->required()
                                            ->columnSpan(2),
                                        
                                        DatePicker::make('warranty_expiry_date')
                                            ->columnSpan(2),
                                        
                                        TextInput::make('serial_number')
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->columns(1),
                    ]),
            ]);
    }
}
