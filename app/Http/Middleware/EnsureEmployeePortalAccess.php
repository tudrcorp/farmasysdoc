<?php

namespace App\Http\Middleware;

use App\Services\Hr\EmployeePortalAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeePortalAccess
{
    public function __construct(private EmployeePortalAccess $portalAccess) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->portalAccess->employee() === null) {
            return redirect()->route('employee-portal.login');
        }

        $this->portalAccess->touch();

        return $next($request);
    }
}
