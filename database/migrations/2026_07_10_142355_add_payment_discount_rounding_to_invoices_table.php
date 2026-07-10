<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('currency')->index();
            $table->decimal('discount_total', 12, 2)->default(0)->after('total_tax');
            $table->decimal('rounding_amount', 12, 2)->default(0)->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex(['payment_method']);
            $table->dropColumn(['payment_method', 'discount_total', 'rounding_amount']);
        });
    }
};
