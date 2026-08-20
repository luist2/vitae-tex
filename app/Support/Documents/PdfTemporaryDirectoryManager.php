<?php

namespace App\Support\Documents;

use DateTimeInterface;
use FilesystemIterator;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class PdfTemporaryDirectoryManager
{
    private const DIRECTORY_PREFIX = 'vitaetex-pdf-';

    private const DIRECTORY_PATTERN = '/\Avitaetex-pdf-[0-9a-f]{32}\z/';

    public function create(): string
    {
        $root = $this->temporaryRoot();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $directory = $root.'/'.self::DIRECTORY_PREFIX.bin2hex(random_bytes(16));

            if (@mkdir($directory, 0700)) {
                return $directory;
            }
        }

        throw new RuntimeException;
    }

    public function delete(string $directory): void
    {
        $root = $this->temporaryRoot();

        if (! $this->isManagedPath($directory, $root) || is_link($directory) || ! is_dir($directory)) {
            throw new RuntimeException;
        }

        File::deleteDirectory($directory);
        clearstatcache(true, $directory);

        if (file_exists($directory) || is_link($directory)) {
            throw new RuntimeException;
        }
    }

    public function pruneOlderThan(DateTimeInterface $cutoff): int
    {
        $root = $this->temporaryRoot();
        $removed = 0;
        $items = new FilesystemIterator($root, FilesystemIterator::SKIP_DOTS);

        foreach ($items as $item) {
            if (
                $item->isLink()
                || ! $item->isDir()
                || ! $this->hasManagedName($item->getFilename())
                || $item->getMTime() >= $cutoff->getTimestamp()
            ) {
                continue;
            }

            $this->delete($item->getPathname());
            $removed++;
        }

        return $removed;
    }

    private function temporaryRoot(): string
    {
        $configuredRoot = config('cv.pdf.temporary_root');

        if (! is_string($configuredRoot) || trim($configuredRoot) === '') {
            throw new RuntimeException;
        }

        $root = realpath($configuredRoot);

        if ($root === false || ! is_dir($root) || ! is_writable($root)) {
            throw new RuntimeException;
        }

        $publicRoot = realpath(public_path());

        if ($publicRoot !== false && ($root === $publicRoot || str_starts_with($root, $publicRoot.DIRECTORY_SEPARATOR))) {
            throw new RuntimeException;
        }

        $normalizedRoot = rtrim($root, DIRECTORY_SEPARATOR);

        if ($normalizedRoot === '') {
            throw new RuntimeException;
        }

        return $normalizedRoot;
    }

    private function isManagedPath(string $directory, string $root): bool
    {
        return dirname($directory) === $root && $this->hasManagedName(basename($directory));
    }

    private function hasManagedName(string $name): bool
    {
        return preg_match(self::DIRECTORY_PATTERN, $name) === 1;
    }
}
