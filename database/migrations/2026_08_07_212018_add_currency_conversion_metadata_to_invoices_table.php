<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('original_currency', 3)
                ->nullable()
                ->after('currency');
            $table->decimal('original_total_amount', 12, 2)
                ->nullable()
                ->after('original_currency');
            $table->string('currency_conversion_status', 30)
                ->default('not_required')
                ->after('original_total_amount')
                ->index();
            $table->decimal('currency_conversion_rate', 20, 10)
                ->nullable()
                ->after('currency_conversion_status');
            $table->date('currency_conversion_date')
                ->nullable()
                ->after('currency_conversion_rate');
            $table->string('currency_conversion_provider', 50)
                ->nullable()
                ->after('currency_conversion_date');
            $table->timestamp('currency_conversion_fetched_at')
                ->nullable()
                ->after('currency_conversion_provider');
            $table->index(['currency', 'currency_conversion_status']);
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropIndex(['currency', 'currency_conversion_status']);
            $table->dropIndex(['currency_conversion_status']);
            $table->dropColumn([
                'original_currency',
                'original_total_amount',
                'currency_conversion_status',
                'currency_conversion_rate',
                'currency_conversion_date',
                'currency_conversion_provider',
                'currency_conversion_fetched_at',
            ]);
        });
    }
};
