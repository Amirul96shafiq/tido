<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Label;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure categories exist first
        $this->call(LabelSeeder::class);

        $labels = Label::all()->keyBy('name');

        // Define realistic merchants with their typical categories and price ranges
        $merchants = [
            [
                'name' => 'Petronas',
                'items' => [
                    ['category' => 'Transportation & Fuel', 'desc' => 'RON 95 Petrol', 'min' => 40, 'max' => 120],
                    ['category' => 'Transportation & Fuel', 'desc' => 'RON 97 Petrol', 'min' => 50, 'max' => 150],
                ],
                'frequency' => 4,
            ],
            [
                'name' => 'Jaya Grocer',
                'items' => [
                    ['category' => 'Groceries & Household', 'desc' => 'Fresh vegetables & fruits', 'min' => 15, 'max' => 60],
                    ['category' => 'Groceries & Household', 'desc' => 'Dairy & eggs', 'min' => 10, 'max' => 35],
                    ['category' => 'Groceries & Household', 'desc' => 'Snacks & beverages', 'min' => 8, 'max' => 40],
                ],
                'frequency' => 5,
            ],
            [
                'name' => 'Grab Food',
                'items' => [
                    ['category' => 'Food & Dining', 'desc' => 'Nasi Lemak set', 'min' => 12, 'max' => 25],
                    ['category' => 'Food & Dining', 'desc' => 'Chicken Rice', 'min' => 10, 'max' => 18],
                    ['category' => 'Food & Dining', 'desc' => 'Mee Goreng Mamak', 'min' => 8, 'max' => 15],
                ],
                'frequency' => 8,
            ],
            [
                'name' => 'Shell',
                'items' => [
                    ['category' => 'Transportation & Fuel', 'desc' => 'V-Power Racing', 'min' => 60, 'max' => 160],
                    ['category' => 'Food & Dining', 'desc' => 'Deli2go Sandwich', 'min' => 5, 'max' => 12],
                ],
                'frequency' => 3,
            ],
            [
                'name' => 'MR D.I.Y.',
                'items' => [
                    ['category' => 'Groceries & Household', 'desc' => 'Cleaning supplies', 'min' => 5, 'max' => 30],
                    ['category' => 'Groceries & Household', 'desc' => 'Storage containers', 'min' => 8, 'max' => 25],
                    ['category' => 'Office Supplies', 'desc' => 'Stationery set', 'min' => 3, 'max' => 20],
                ],
                'frequency' => 2,
            ],
            [
                'name' => 'Tenaga Nasional',
                'items' => [
                    ['category' => 'Utilities & Bills', 'desc' => 'Electricity bill - July', 'min' => 80, 'max' => 250],
                ],
                'frequency' => 1,
            ],
            [
                'name' => 'TM (Unifi)',
                'items' => [
                    ['category' => 'Utilities & Bills', 'desc' => 'Internet subscription', 'min' => 129, 'max' => 199],
                ],
                'frequency' => 1,
            ],
            [
                'name' => 'Netflix',
                'items' => [
                    ['category' => 'Subscriptions & Memberships', 'desc' => 'Monthly subscription', 'min' => 35, 'max' => 55],
                ],
                'frequency' => 1,
            ],
            [
                'name' => 'Spotify',
                'items' => [
                    ['category' => 'Subscriptions & Memberships', 'desc' => 'Premium Family plan', 'min' => 22, 'max' => 22],
                ],
                'frequency' => 1,
            ],
            [
                'name' => 'Watsons',
                'items' => [
                    ['category' => 'Healthcare & Medical', 'desc' => 'Vitamins & supplements', 'min' => 25, 'max' => 80],
                    ['category' => 'Healthcare & Medical', 'desc' => 'First aid supplies', 'min' => 10, 'max' => 35],
                ],
                'frequency' => 2,
            ],
            [
                'name' => 'GSC Cinemas',
                'items' => [
                    ['category' => 'Entertainment & Leisure', 'desc' => 'Movie tickets x2', 'min' => 30, 'max' => 60],
                    ['category' => 'Food & Dining', 'desc' => 'Popcorn & drinks combo', 'min' => 18, 'max' => 35],
                ],
                'frequency' => 2,
            ],
            [
                'name' => 'Harvey Norman',
                'items' => [
                    ['category' => 'Electronics & Gadgets', 'desc' => 'USB-C Hub adapter', 'min' => 45, 'max' => 120],
                    ['category' => 'Electronics & Gadgets', 'desc' => 'Wireless mouse', 'min' => 35, 'max' => 90],
                ],
                'frequency' => 1,
            ],
        ];

        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        // Only generate up to "today" so data looks realistic
        $daysInMonth = (int) $now->day;

        foreach ($merchants as $merchant) {
            for ($i = 0; $i < $merchant['frequency']; $i++) {
                // Pick a random date this month (up to today)
                $day = rand(1, $daysInMonth);
                $hour = rand(7, 22);
                $minute = rand(0, 59);
                $dateTime = $monthStart->copy()->addDays($day - 1)->setHour($hour)->setMinute($minute);

                // Decide which items to include (1 to all items)
                $itemCount = rand(1, count($merchant['items']));
                $selectedItems = collect($merchant['items'])->random($itemCount);
                if (! $selectedItems instanceof Collection) {
                    $selectedItems = collect([$selectedItems]);
                }

                // Calculate totals
                $itemsData = [];
                $subtotal = 0;
                foreach ($selectedItems as $item) {
                    $unitPrice = round(mt_rand($item['min'] * 100, $item['max'] * 100) / 100, 2);
                    $quantity = $item['category'] === 'Transportation & Fuel' ? 1.0 : round(mt_rand(1000, 3000) / 1000, 3);

                    // For subscriptions and bills, quantity is always 1
                    if (in_array($item['category'], ['Utilities & Bills', 'Subscriptions & Memberships'])) {
                        $quantity = 1.0;
                    }

                    $lineTotal = round($unitPrice * $quantity, 2);
                    $subtotal += $lineTotal;

                    $itemsData[] = [
                        'category' => $item['category'],
                        'description' => $item['desc'],
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $tax = round($subtotal * 0.06, 2);
                $total = $subtotal + $tax;
                $invoiceNum = 'INV-'.strtoupper(substr(md5((string) mt_rand()), 0, 8));

                $invoice = Invoice::create([
                    'merchant_name' => $merchant['name'],
                    'invoice_number' => $invoiceNum,
                    'receipt_hash' => hash('sha256', $invoiceNum.$dateTime->format('Y-m-d H:i:s').$total),
                    'date_time' => $dateTime,
                    'subtotal' => $subtotal,
                    'total_tax' => $tax,
                    'total_amount' => $total,
                    'currency' => 'MYR',
                    'source' => collect(['manual', 'whatsapp', 'google_drive'])->random(),
                    'status' => collect(['parsed', 'reviewed'])->random(),
                    'notes' => null,
                ]);

                foreach ($itemsData as $itemData) {
                    $label = $labels->get($itemData['category']);

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'label_id' => $label?->id,
                        'description' => $itemData['description'],
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'line_total' => $itemData['line_total'],
                    ]);
                }
            }
        }

        $this->command->info('✅ Sample data seeded: ~32 invoices for '.$now->format('F Y'));
    }
}
