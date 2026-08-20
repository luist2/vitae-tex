<?php

namespace App\Support\Documents;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Throwable;

final class TectonicCompiler
{
    private const SOURCE_FILENAME = 'document.tex';

    private const PDF_FILENAME = 'document.pdf';

    public function compile(string $source): string
    {
        $directory = null;

        try {
            $directory = $this->createTemporaryDirectory();
            $sourcePath = $directory.'/'.self::SOURCE_FILENAME;
            $pdfPath = $directory.'/'.self::PDF_FILENAME;

            if (File::put($sourcePath, $source, true) !== strlen($source) || ! @chmod($sourcePath, 0600)) {
                throw new PdfCompilationException;
            }

            $result = Process::path($directory)
                ->timeout($this->positiveIntegerConfig('timeout_seconds'))
                ->idleTimeout($this->positiveIntegerConfig('idle_timeout_seconds'))
                ->env([
                    'TECTONIC_ONLY_CACHED' => '1',
                    'TECTONIC_UNTRUSTED_MODE' => '1',
                ])
                ->run([
                    $this->stringConfig('tectonic_binary'),
                    '-X',
                    'compile',
                    '--untrusted',
                    '--only-cached',
                    '--outdir',
                    $directory,
                    $sourcePath,
                ]);

            if ($result->failed()) {
                throw new PdfCompilationException;
            }

            return $this->readValidPdf($pdfPath);
        } catch (PdfCompilationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PdfCompilationException;
        } finally {
            if (is_string($directory)) {
                try {
                    File::deleteDirectory($directory);
                    clearstatcache(true, $directory);
                } catch (Throwable) {
                    throw new PdfCompilationException;
                }

                if (is_dir($directory)) {
                    throw new PdfCompilationException;
                }
            }
        }
    }

    private function createTemporaryDirectory(): string
    {
        $root = $this->temporaryRoot();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $directory = $root.'/vitaetex-pdf-'.bin2hex(random_bytes(16));

            if (@mkdir($directory, 0700)) {
                return $directory;
            }
        }

        throw new PdfCompilationException;
    }

    private function temporaryRoot(): string
    {
        $configuredRoot = $this->stringConfig('temporary_root');
        $root = realpath($configuredRoot);

        if ($root === false || ! is_dir($root) || ! is_writable($root)) {
            throw new PdfCompilationException;
        }

        $publicRoot = realpath(public_path());

        if ($publicRoot !== false && ($root === $publicRoot || str_starts_with($root, $publicRoot.DIRECTORY_SEPARATOR))) {
            throw new PdfCompilationException;
        }

        return rtrim($root, DIRECTORY_SEPARATOR);
    }

    private function readValidPdf(string $pdfPath): string
    {
        if (! is_file($pdfPath) || is_link($pdfPath) || ! is_readable($pdfPath)) {
            throw new PdfCompilationException;
        }

        $size = filesize($pdfPath);
        $minimumBytes = $this->positiveIntegerConfig('minimum_bytes');
        $maximumBytes = $this->positiveIntegerConfig('maximum_bytes');

        if (! is_int($size) || $maximumBytes < $minimumBytes || $size < $minimumBytes || $size > $maximumBytes) {
            throw new PdfCompilationException;
        }

        $pdf = File::get($pdfPath);

        if (strlen($pdf) !== $size || ! str_starts_with($pdf, '%PDF-')) {
            throw new PdfCompilationException;
        }

        return $pdf;
    }

    private function stringConfig(string $key): string
    {
        $value = config("cv.pdf.{$key}");

        if (! is_string($value) || trim($value) === '') {
            throw new PdfCompilationException;
        }

        return $value;
    }

    private function positiveIntegerConfig(string $key): int
    {
        $value = config("cv.pdf.{$key}");

        if (! is_int($value) || $value < 1) {
            throw new PdfCompilationException;
        }

        return $value;
    }
}
