<?php

namespace Tests\Feature\Documents;

use App\Support\Documents\PdfCompilationException;
use App\Support\Documents\TectonicCompiler;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Exception\ProcessTimedOutException as SymfonyProcessTimedOutException;
use Symfony\Component\Process\Process as SymfonyProcess;
use Tests\TestCase;

class TectonicCompilerTest extends TestCase
{
    private string $temporaryRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryRoot = sys_get_temp_dir().'/vitaetex-compiler-tests-'.bin2hex(random_bytes(8));
        mkdir($this->temporaryRoot, 0700, true);

        config([
            'cv.pdf.tectonic_binary' => '/usr/local/bin/tectonic',
            'cv.pdf.temporary_root' => $this->temporaryRoot,
            'cv.pdf.timeout_seconds' => 30,
            'cv.pdf.idle_timeout_seconds' => 15,
            'cv.pdf.minimum_bytes' => 1024,
            'cv.pdf.maximum_bytes' => 4096,
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryRoot);

        parent::tearDown();
    }

    public function test_it_compiles_with_a_non_shell_command_and_returns_valid_pdf_bytes(): void
    {
        $source = '\\documentclass{article}\\begin{document}Persistido\\end{document}';
        $expectedPdf = $this->validPdf();

        Process::fake(function (PendingProcess $process) use ($source, $expectedPdf) {
            $this->assertIsArray($process->command);
            $this->assertSame([
                '/usr/local/bin/tectonic',
                '-X',
                'compile',
                '--untrusted',
                '--only-cached',
                '--outdir',
                $process->path,
                $process->path.'/document.tex',
            ], $process->command);
            $this->assertSame(30, $process->timeout);
            $this->assertSame(15, $process->idleTimeout);
            $this->assertSame([
                'TECTONIC_ONLY_CACHED' => '1',
                'TECTONIC_UNTRUSTED_MODE' => '1',
            ], $process->environment);
            $this->assertSame(0700, fileperms($process->path) & 0777);
            $this->assertSame(0600, fileperms($process->path.'/document.tex') & 0777);
            $this->assertSame($source, File::get($process->path.'/document.tex'));

            File::put($process->path.'/document.pdf', $expectedPdf);

            return Process::result();
        });

        $this->assertSame($expectedPdf, $this->compiler()->compile($source));
        Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command));
        $this->assertTemporaryRootIsEmpty();
    }

    public function test_a_failed_process_produces_a_safe_error_and_cleans_sensitive_files(): void
    {
        $source = 'contenido privado que no debe aparecer';
        $workingDirectory = null;

        Process::fake(function (PendingProcess $process) use (&$workingDirectory, $source) {
            $workingDirectory = $process->path;

            return Process::result('', $source.' '.$process->path, 1);
        });

        try {
            $this->compiler()->compile($source);
            $this->fail('La compilación fallida debía lanzar una excepción.');
        } catch (PdfCompilationException $exception) {
            $this->assertSame('No fue posible generar el PDF. Inténtalo nuevamente.', $exception->getMessage());
            $this->assertStringNotContainsString($source, $exception->getMessage());
            $this->assertStringNotContainsString((string) $workingDirectory, $exception->getMessage());
        }

        $this->assertIsString($workingDirectory);
        $this->assertDirectoryDoesNotExist($workingDirectory);
        $this->assertTemporaryRootIsEmpty();
    }

    public function test_a_timeout_produces_a_safe_error_and_cleans_the_working_directory(): void
    {
        $workingDirectory = null;

        Process::fake(function (PendingProcess $process) use (&$workingDirectory) {
            $workingDirectory = $process->path;
            $symfonyProcess = new SymfonyProcess($process->command);
            $symfonyProcess->setTimeout($process->timeout);

            return new SymfonyProcessTimedOutException(
                $symfonyProcess,
                SymfonyProcessTimedOutException::TYPE_GENERAL,
            );
        });

        $this->expectException(PdfCompilationException::class);
        $this->expectExceptionMessage('No fue posible generar el PDF. Inténtalo nuevamente.');

        try {
            $this->compiler()->compile('fuente privada');
        } finally {
            $this->assertIsString($workingDirectory);
            $this->assertDirectoryDoesNotExist($workingDirectory);
            $this->assertTemporaryRootIsEmpty();
        }
    }

    #[DataProvider('invalidPdfProvider')]
    public function test_it_rejects_invalid_pdf_outputs_and_cleans_them(string $case): void
    {
        Process::fake(function (PendingProcess $process) use ($case) {
            $contents = match ($case) {
                'missing' => null,
                'too small' => '%PDF-1.7',
                'invalid signature' => 'NOT-A-PDF'.str_repeat('x', 2048),
                'too large' => '%PDF-1.7'.str_repeat('x', 4096),
            };

            if (is_string($contents)) {
                File::put($process->path.'/document.pdf', $contents);
            }

            return Process::result();
        });

        try {
            $this->compiler()->compile('fuente privada');
            $this->fail("El resultado {$case} debía rechazarse.");
        } catch (PdfCompilationException $exception) {
            $this->assertSame('No fue posible generar el PDF. Inténtalo nuevamente.', $exception->getMessage());
        }

        $this->assertTemporaryRootIsEmpty();
    }

    /** @return array<string, array{string}> */
    public static function invalidPdfProvider(): array
    {
        return [
            'missing output' => ['missing'],
            'undersized output' => ['too small'],
            'invalid signature' => ['invalid signature'],
            'oversized output' => ['too large'],
        ];
    }

    public function test_it_refuses_to_write_documents_below_the_public_directory(): void
    {
        config(['cv.pdf.temporary_root' => public_path()]);
        Process::fake()->preventStrayProcesses();

        $this->expectException(PdfCompilationException::class);

        try {
            $this->compiler()->compile('fuente privada');
        } finally {
            Process::assertNothingRan();
        }
    }

    private function compiler(): TectonicCompiler
    {
        return app(TectonicCompiler::class);
    }

    private function validPdf(): string
    {
        return '%PDF-1.7'.str_repeat('x', 2048);
    }

    private function assertTemporaryRootIsEmpty(): void
    {
        $this->assertSame([], File::directories($this->temporaryRoot));
        $this->assertSame([], File::files($this->temporaryRoot));
    }
}
