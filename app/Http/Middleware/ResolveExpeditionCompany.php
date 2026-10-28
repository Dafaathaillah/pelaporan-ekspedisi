<?php

namespace App\Http\Middleware;

use App\Services\ExpeditionCompanyResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveExpeditionCompany
{
    public function __construct(private ExpeditionCompanyResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $this->resolver->resolve($request->user());
        }

        return $next($request);
    }
}
