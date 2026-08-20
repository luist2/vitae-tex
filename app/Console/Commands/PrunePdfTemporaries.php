<?php

namespace App\Console\Commands;

use App\Support\Documents\PdfTemporaryDirectoryManager;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

final class PrunePdfTemporaries extends Command
{
    protected $signature = 'cv:prune-pdf-temporaries';

    protected $description = 'Elimina directorios temporales PDF antiguos abandonados por procesos interrumpidos';

    public function handle(PdfTemporaryDirectoryManager $temporaryDirectories): int
    {
        try {
            $maximumAge = config('cv.pdf.temporary_max_age_minutes');
            $compilationTimeout = config('cv.pdf.timeout_seconds');

            if (
                ! is_int($maximumAge)
                || $maximumAge < 1
                || ! is_int($compilationTimeout)
                || $compilationTimeout < 1
                || ($maximumAge * 60) <= $compilationTimeout
            ) {
                throw new RuntimeException;
            }

            $removed = $temporaryDirectories->pruneOlderThan(now()->subMinutes($maximumAge));
        } catch (Throwable) {
            $this->error('No fue posible limpiar los temporales PDF.');

            return self::FAILURE;
        }

        $this->info("Temporales PDF antiguos eliminados: {$removed}.");

        return self::SUCCESS;
    }
}
