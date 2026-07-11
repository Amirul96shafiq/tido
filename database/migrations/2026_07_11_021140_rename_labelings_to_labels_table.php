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
        if (! Schema::hasTable('labelings') || Schema::hasTable('labels')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['labeling_id']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['labeling_id']);
        });

        Schema::rename('labelings', 'labels');

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('labeling_id', 'label_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('labeling_id', 'label_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('label_id')->references('id')->on('labels')->nullOnDelete();
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->foreign('label_id')->references('id')->on('labels')->nullOnDelete();
        });

        if (Schema::hasTable('activity_log')) {
            DB::table('activity_log')
                ->where('subject_type', 'App\\Models\\Labeling')
                ->update(['subject_type' => 'App\\Models\\Label']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('labels') || Schema::hasTable('labelings')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['label_id']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['label_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('label_id', 'labeling_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('label_id', 'labeling_id');
        });

        Schema::rename('labels', 'labelings');

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('labeling_id')->references('id')->on('labelings')->nullOnDelete();
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->foreign('labeling_id')->references('id')->on('labelings')->nullOnDelete();
        });

        if (Schema::hasTable('activity_log')) {
            DB::table('activity_log')
                ->where('subject_type', 'App\\Models\\Label')
                ->update(['subject_type' => 'App\\Models\\Labeling']);
        }
    }
};
