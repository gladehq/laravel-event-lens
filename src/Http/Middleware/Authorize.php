<?php

declare(strict_types=1);

namespace GladeHQ\LaravelEventLens\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class Authorize
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Gate::allows('viewEventLens', [$request->user()])) {
            return $next($request);
        }

        abort(403, 'Unauthorized to access Event Lens.');
    }
}
