<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Gate;


class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $authorized = false;

        foreach ($roles as $role) {
            if (Gate::allows($role)) {
                $authorized = true;
                break;
            }
        }

        if(!$authorized) {
            abort(401);
        }

        return $next($request);
    }
}
