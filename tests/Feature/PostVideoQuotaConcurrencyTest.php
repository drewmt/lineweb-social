<?php

namespace Tests\Feature;

use App\Media\VideoUpload;
use App\Models\Post;
use App\Models\Space;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PostVideoQuotaConcurrencyTest extends TestCase
{
    public function test_two_writers_cannot_reserve_the_last_space_quota_bytes(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('This contention test requires an isolated MariaDB database.');
        }

        config([
            'media.video.enabled' => true,
            'media.video.space_quota_bytes' => 1024 * 1024,
            'queue.default' => 'database',
        ]);

        $author = User::factory()->create();
        $space = Space::factory()->for($author, 'owner')->create();
        $first = Post::factory()->for($space)->for($author, 'author')->create(['published_at' => null]);
        $second = Post::factory()->for($space)->for($author, 'author')->create(['published_at' => null]);
        $source = tempnam(sys_get_temp_dir(), 'lineweb-video-quota-');
        $this->assertIsString($source);
        file_put_contents($source, str_repeat('v', 1024 * 1024));

        $child = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['media.video.enabled' => true, 'media.video.space_quota_bytes' => 1024 * 1024]);
$author = App\Models\User::query()->findOrFail((int) $argv[1]);
$draft = App\Models\Post::query()->findOrFail((int) $argv[2]);
$file = new Illuminate\Http\UploadedFile($argv[3], 'second.mp4', 'video/mp4', null, true);
try {
    app(App\Media\VideoUpload::class)->attach($author, $draft, $file, 'Second concurrent clip');
    echo 'UNEXPECTED_SUCCESS';
    exit(3);
} catch (Illuminate\Validation\ValidationException) {
    echo 'QUOTA_REJECTED';
    exit(0);
}
PHP;
        $process = new Process([
            PHP_BINARY,
            '-r',
            $child,
            (string) $author->getKey(),
            (string) $second->getKey(),
            $source,
        ], base_path());
        $process->setTimeout(15);

        DB::beginTransaction();

        try {
            app(VideoUpload::class)->attach(
                $author,
                $first,
                new UploadedFile($source, 'first.mp4', 'video/mp4', null, true),
                'First concurrent clip',
            );
            $process->start();
            usleep(500_000);
            $this->assertTrue($process->isRunning(), 'The second upload did not wait for the Space row lock.');
            DB::commit();

            $process->wait();
            $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
            $this->assertSame('QUOTA_REJECTED', $process->getOutput());
            $this->assertDatabaseCount('post_videos', 1);
            $this->assertNull($second->fresh()?->video);
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($process->isRunning()) {
                $process->stop();
            }

            unlink($source);
        }
    }
}
