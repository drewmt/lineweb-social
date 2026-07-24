<?php

use App\Enums\PlatformRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('platform_role', 24)
                ->default(PlatformRole::Member->value)
                ->index()
                ->after('is_discoverable');
            $table->timestamp('suspended_at')->nullable()->index()->after('platform_role');
            $table->string('suspension_reason', 500)->nullable()->after('suspended_at');
            $table->foreignId('suspended_by')
                ->nullable()
                ->after('suspension_reason')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropIndex(['suspended_at']);
            $table->dropIndex(['platform_role']);
            $table->dropColumn([
                'platform_role',
                'suspended_at',
                'suspension_reason',
            ]);
        });
    }
};
