<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurrings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->string('type', 40);
            $table->foreignId('label_id')->nullable()->constrained('labels')->nullOnDelete();
            $table->foreignId('family_member_id')->nullable()->constrained('family_members')->nullOnDelete();
            $table->boolean('is_shared')->default(false);
            $table->decimal('expected_amount', 12, 2)->nullable();
            $table->decimal('goal_target_amount', 12, 2)->nullable();
            $table->string('frequency', 20)->default('repeating');
            $table->unsignedTinyInteger('interval_months')->nullable();
            $table->unsignedTinyInteger('anchor_day')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->unsignedInteger('instalment_total')->nullable();
            $table->unsignedInteger('instalment_remaining')->nullable();
            $table->json('merchant_aliases')->nullable();
            $table->boolean('notify_filament')->default(true);
            $table->boolean('notify_whatsapp')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('edited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'next_due_on']);
            $table->index(['family_member_id', 'is_shared']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurrings');
    }
};
