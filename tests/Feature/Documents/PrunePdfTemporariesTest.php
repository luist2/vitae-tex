<?php

namespace Tests\Feature\Documents;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PrunePdfTemporariesTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir().'/vitaetex-prune-tests-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryRoot, 0700, true);

        config([
            'cv.pdf.temporary_root' => $this->temporaryRoot,
            'cv.pdf.temporary_max_age_minutes' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_it_only_removes_old_managed_directories(): void
    {
        $oldDirectory = $this->createManagedDirectory('a', now()->subHours(2)->timestamp);
        $recentDirectory = $this->createManagedDirectory('b', now()->subMinutes(30)->timestamp);
        $unmanagedDirectory = $this->temporaryRoot.'/vitaetex-pdf-not-managed';
        $managedNameFile = $this->temporaryRoot.'/vitaetex-pdf-'.str_repeat('c', 32);
        $linkTarget = $this->temporaryRoot.'/link-target';
        $managedLink = $this->temporaryRoot.'/vitaetex-pdf-'.str_repeat('d', 32);

        mkdir($unmanagedDirectory, 0700);
        touch($unmanagedDirectory, now()->subHours(2)->timestamp);
        File::put($managedNameFile, 'no eliminar');
        touch($managedNameFile, now()->subHours(2)->timestamp);
        mkdir($linkTarget, 0700);
        touch($linkTarget, now()->subHours(2)->timestamp);
        symlink($linkTarget, $managedLink);

        $this->artisan('cv:prune-pdf-temporaries')
            ->expectsOutput('Temporales PDF antiguos eliminados: 1.')
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($oldDirectory);
        $this->assertDirectoryExists($recentDirectory);
        $this->assertDirectoryExists($unmanagedDirectory);
        $this->assertFileExists($managedNameFile);
        $this->assertTrue(is_link($managedLink));
        $this->assertDirectoryExists($linkTarget);
    }

    public function test_it_fails_safely_without_deleting_when_the_age_is_invalid(): void
    {
        $oldDirectory = $this->createManagedDirectory('e', now()->subHours(2)->timestamp);
        config(['cv.pdf.temporary_max_age_minutes' => 0]);

        $this->artisan('cv:prune-pdf-temporaries')
            ->expectsOutput('No fue posible limpiar los temporales PDF.')
            ->assertFailed();

        $this->assertDirectoryExists($oldDirectory);
    }

    public function test_it_refuses_to_clean_below_the_public_directory(): void
    {
        config(['cv.pdf.temporary_root' => public_path()]);

        $this->artisan('cv:prune-pdf-temporaries')
            ->expectsOutput('No fue posible limpiar los temporales PDF.')
            ->assertFailed();
    }

    public function test_it_refuses_to_scan_the_filesystem_root(): void
    {
        config(['cv.pdf.temporary_root' => DIRECTORY_SEPARATOR]);

        $this->artisan('cv:prune-pdf-temporaries')
            ->expectsOutput('No fue posible limpiar los temporales PDF.')
            ->assertFailed();
    }

    public function test_the_maximum_age_must_exceed_the_compilation_timeout(): void
    {
        $oldDirectory = $this->createManagedDirectory('f', now()->subHours(2)->timestamp);
        config([
            'cv.pdf.temporary_max_age_minutes' => 1,
            'cv.pdf.timeout_seconds' => 60,
        ]);

        $this->artisan('cv:prune-pdf-temporaries')
            ->expectsOutput('No fue posible limpiar los temporales PDF.')
            ->assertFailed();

        $this->assertDirectoryExists($oldDirectory);
    }

    public function test_old_pdf_temporaries_are_scheduled_for_hourly_cleanup(): void
    {
        $cleanup = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains($event->command, 'cv:prune-pdf-temporaries'));

        $this->assertNotNull($cleanup);
        $this->assertSame('0 * * * *', $cleanup->expression);
    }

    private function createManagedDirectory(string $character, int $modifiedAt): string
    {
        $directory = $this->temporaryRoot.'/vitaetex-pdf-'.str_repeat($character, 32);

        mkdir($directory, 0700);
        File::put($directory.'/document.tex', 'contenido temporal');
        touch($directory, $modifiedAt);

        return $directory;
    }
}
