<?php

namespace App\Http\Middleware;

use App\Services\Hr\EmployeePortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeePortalGuest
{
    public function __construct(private EmployeePortalAccess $portalAccess) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->portalAccess->employee() !== null) {
            return redirect()->route('employee-portal.home');
        }

        return $next($request);
    }
}
