<?php

namespace App\Support\Documents;

use RuntimeException;

final class PdfCompilationException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No fue posible generar el PDF. Inténtalo nuevamente.');
    }
}
