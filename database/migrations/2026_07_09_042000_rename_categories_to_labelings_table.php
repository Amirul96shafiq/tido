<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
        });

        Schema::rename('categories', 'labelings');

        Schema::table('labelings', function (Blueprint $table) {
            $table->string('type')->default('finance');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            Schema::table('labelings', function (Blueprint $table) {
                $table->dropUnique('categories_slug_unique');
            });
        } else {
            Schema::table('labelings', function (Blueprint $table) {
                $table->dropUnique(['slug']);
            });
        }

        Schema::table('labelings', function (Blueprint $table) {
            $table->unique(['type', 'slug']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('category_id', 'labeling_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('category_id', 'labeling_id');
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('labeling_id')->references('id')->on('labelings')->nullOnDelete();
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->foreign('labeling_id')->references('id')->on('labelings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('labelings') || Schema::hasTable('categories')) {
            return;
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropForeign(['labeling_id']);
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->dropForeign(['labeling_id']);
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->renameColumn('labeling_id', 'category_id');
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->renameColumn('labeling_id', 'category_id');
        });

        Schema::table('labelings', function (Blueprint $table) {
            $table->dropUnique(['type', 'slug']);
            $table->dropColumn('type');
        });

        $driver = Schema::getConnection()->getDriverName();

        Schema::rename('labelings', 'categories');

        if ($driver === 'sqlite') {
            Schema::table('categories', function (Blueprint $table) {
                $table->unique(['slug'], 'categories_slug_unique');
            });
        } else {
            Schema::table('categories', function (Blueprint $table) {
                $table->unique(['slug']);
            });
        }

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });

        Schema::table('budgets', function (Blueprint $table) {
            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }
};
