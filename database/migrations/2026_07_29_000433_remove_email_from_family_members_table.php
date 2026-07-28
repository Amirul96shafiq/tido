<?php

declare(strict_types=1);

use App\Enums\HouseholdRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')
            ->whereNotNull('family_member_id')
            ->where('household_role', HouseholdRole::FamilyMember->value)
            ->get(['id', 'family_member_id']);

        foreach ($users as $user) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'email' => 'family+'.$user->family_member_id.'@tido.local',
                ]);
        }

        Schema::table('family_members', function (Blueprint $table): void {
            $table->dropIndex(['email']);
            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        Schema::table('family_members', function (Blueprint $table): void {
            $table->string('email')->nullable()->after('phone');
            $table->index('email');
        });
    }
};
