<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('handle', 40)->nullable()->unique()->after('name');
            $table->string('bio', 320)->nullable()->after('email');
            $table->string('location', 120)->nullable()->after('bio');
            $table->string('website_url', 2048)->nullable()->after('location');
            $table->string('profile_visibility', 20)->default('members')->after('website_url');
            $table->boolean('is_discoverable')->default(true)->after('profile_visibility');
        });

        DB::table('users')
            ->select(['id', 'name'])
            ->orderBy('id')
            ->each(function (object $user): void {
                $base = Str::slug((string) $user->name) ?: 'member';
                $base = Str::limit($base, 30, '');

                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['handle' => $base.'-'.$user->id]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['handle']);
            $table->dropColumn([
                'handle',
                'bio',
                'location',
                'website_url',
                'profile_visibility',
                'is_discoverable',
            ]);
        });
    }
};
