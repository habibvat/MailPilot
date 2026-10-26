<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * MailPilot n'a pas d'authentification : on refuse toute requête qui ne vient pas de la machine locale.
 */
class EnsureLocalRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort(403, 'MailPilot n\'est accessible que depuis cette machine.');
        }

        return $next($request);
    }
}
