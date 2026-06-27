<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('merchant_name');
            $table->string('invoice_number')->nullable();
            $table->string('receipt_hash')->unique();
            $table->timestamp('date_time');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->string('currency', 3)->default('MYR');
            $table->string('source', 20)->default('manual'); // manual, whatsapp, google_drive
            $table->string('status', 30)->default('pending'); // pending, parsed, reviewed, requires_manual_review, failed
            $table->string('google_drive_file_id')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('image_path')->nullable();
            $table->json('raw_ai_response')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
