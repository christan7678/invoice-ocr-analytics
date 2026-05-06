<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyProfile
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()->company) {
            return redirect()
                ->route('company.create')
                ->with('status', 'Create your company profile before using the invoice workspace.');
        }

        return $next($request);
    }
}
