<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Services\Hr\EmployeePortalAccess;
use Illuminate\Http\RedirectResponse;

class EmployeePortalLogoutController extends Controller
{
    public function __invoke(EmployeePortalAccess $portalAccess): RedirectResponse
    {
        $portalAccess->forget();

        return redirect()->route('employee-portal.login');
    }
}
