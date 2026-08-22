<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

final class EnforceCvEditorPayloadLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $maximumBytes = (int) config('cv.editor.maximum_payload_bytes');

        if ($maximumBytes < 1) {
            throw new LogicException('The CV editor payload limit must be a positive integer.');
        }

        $declaredBytes = $request->server('CONTENT_LENGTH');

        if ((is_int($declaredBytes) || is_string($declaredBytes))
            && ctype_digit((string) $declaredBytes)
            && (int) $declaredBytes > $maximumBytes) {
            throw new PostTooLargeException;
        }

        if (strlen($request->getContent()) > $maximumBytes) {
            throw new PostTooLargeException;
        }

        return $next($request);
    }
}
