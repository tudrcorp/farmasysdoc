<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Hr\EmployeePortalAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmployeePortalEntryController extends Controller
{
    public function __invoke(Request $request, Employee $employee, EmployeePortalAccess $portalAccess): RedirectResponse|Response
    {
        if (! $request->hasValidSignature()) {
            return response()->view('employee-portal.expired', [], 403);
        }

        if (! $employee->is_active) {
            return response()->view('employee-portal.unavailable', [], 403);
        }

        $portalAccess->start($employee);

        return redirect()->route('employee-portal.home');
    }
}
