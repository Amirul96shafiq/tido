<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $colors = [
            'transportation-fuel' => '#FFAF24',
            'groceries-household' => '#FFDCA1',
            'electronics-gadgets' => '#E09210',
            'utilities-bills' => '#FFD07D',
            'healthcare-medical' => '#B87307',
            'entertainment-leisure' => '#FFC154',
            'office-supplies' => '#8F5404',
            'subscriptions-memberships' => '#FFE7C2',
        ];

        foreach ($colors as $slug => $color) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update(['color' => $color]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $colors = [
            'transportation-fuel' => '#f97316',
            'groceries-household' => '#84cc16',
            'electronics-gadgets' => '#3b82f6',
            'utilities-bills' => '#eab308',
            'healthcare-medical' => '#ec4899',
            'entertainment-leisure' => '#8b5cf6',
            'office-supplies' => '#6b7280',
            'subscriptions-memberships' => '#14b8a6',
        ];

        foreach ($colors as $slug => $color) {
            DB::table('categories')
                ->where('slug', $slug)
                ->update(['color' => $color]);
        }
    }
};
