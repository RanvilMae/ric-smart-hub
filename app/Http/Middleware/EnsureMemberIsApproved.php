<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Members whose registration is still pending (or was rejected) are sent
 * to the registration status page. Admins always pass.
 */
class EnsureMemberIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->isApproved()) {
            return redirect()->route('registration.status');
        }

        return $next($request);
    }
}