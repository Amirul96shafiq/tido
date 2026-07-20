<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('payment_method_id')
                ->nullable()
                ->after('currency')
                ->constrained('payment_methods')
                ->nullOnDelete();
        });

        $this->seedSystemPaymentMethods();
        $this->backfillInvoicePaymentMethodIds();

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['payment_method']);
            $table->dropColumn('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('currency')->index();
        });

        $methods = DB::table('payment_methods')->pluck('slug', 'id');

        foreach (DB::table('invoices')->whereNotNull('payment_method_id')->get() as $invoice) {
            $slug = $methods[$invoice->payment_method_id] ?? null;

            if ($slug === null) {
                continue;
            }

            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update(['payment_method' => $slug]);
        }

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }

    private function seedSystemPaymentMethods(): void
    {
        $now = now();

        $methods = [
            [
                'slug' => 'mastercard',
                'name' => 'Mastercard',
                'aliases' => ['master', 'master_card', 'card', 'mc'],
                'icon' => 'heroicon-o-credit-card',
                'color' => '#FFA524',
            ],
            [
                'slug' => 'visa',
                'name' => 'Visa',
                'aliases' => [],
                'icon' => 'heroicon-o-credit-card',
                'color' => '#FFD07D',
            ],
            [
                'slug' => 'mykasih',
                'name' => 'MYKASIH',
                'aliases' => ['my_kasih'],
                'icon' => 'heroicon-o-identification',
                'color' => '#FFE2A3',
            ],
            [
                'slug' => 'cash',
                'name' => 'Cash',
                'aliases' => [],
                'icon' => 'heroicon-o-banknotes',
                'color' => '#FFA524',
            ],
            [
                'slug' => 'pay_with_qr',
                'name' => 'Pay with QR',
                'aliases' => ['qr', 'qr_pay', 'qr_payment', 'duitnow_qr', 'duitnow'],
                'icon' => 'heroicon-o-qr-code',
                'color' => '#FFD07D',
            ],
            [
                'slug' => 'touchngo',
                'name' => "Touch 'n Go",
                'aliases' => ['touch_n_go', 'touchngo_ewallet', 'tng', 't_ngo', 'tngo'],
                'icon' => 'heroicon-o-device-phone-mobile',
                'color' => '#FFE2A3',
            ],
            [
                'slug' => 'other',
                'name' => 'Other',
                'aliases' => ['debit', 'credit', 'debit_card', 'credit_card', 'mydebit', 'my_debit', 'card_payment'],
                'icon' => 'heroicon-o-ellipsis-horizontal',
                'color' => '#9CA3AF',
            ],
        ];

        foreach ($methods as $method) {
            $existing = DB::table('payment_methods')->where('slug', $method['slug'])->first();

            if ($existing !== null) {
                continue;
            }

            DB::table('payment_methods')->insert([
                'name' => $method['name'],
                'slug' => $method['slug'],
                'aliases' => json_encode($method['aliases']),
                'icon' => $method['icon'],
                'color' => $method['color'],
                'is_system' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function backfillInvoicePaymentMethodIds(): void
    {
        $slugToId = DB::table('payment_methods')->pluck('id', 'slug');

        foreach (
            DB::table('invoices')
                ->whereNotNull('payment_method')
                ->where('payment_method', '!=', '')
                ->get() as $invoice
        ) {
            $id = $slugToId[$invoice->payment_method] ?? null;

            if ($id === null) {
                continue;
            }

            DB::table('invoices')
                ->where('id', $invoice->id)
                ->update(['payment_method_id' => $id]);
        }
    }
};
