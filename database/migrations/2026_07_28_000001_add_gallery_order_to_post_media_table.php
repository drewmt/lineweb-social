<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('post_media', function (Blueprint $table): void {
            $table->dropUnique(['post_id']);
            $table->unsignedTinyInteger('position')->default(0)->after('post_id');
            $table->unique(['post_id', 'position']);
        });
    }

    public function down(): void
    {
        DB::table('post_media')
            ->where('position', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($mediaItems): void {
                foreach ($mediaItems as $media) {
                    try {
                        Storage::disk($media->disk)->delete($media->path);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }

                DB::table('post_media')
                    ->whereIn('id', $mediaItems->pluck('id'))
                    ->delete();
            });

        Schema::table('post_media', function (Blueprint $table): void {
            $table->dropUnique(['post_id', 'position']);
            $table->dropColumn('position');
            $table->unique('post_id');
        });
    }
};
