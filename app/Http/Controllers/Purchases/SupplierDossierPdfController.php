<?php

namespace App\Http\Controllers\Purchases;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Purchases\SupplierDossierPdfFactory;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SupplierDossierPdfController extends Controller
{
    public function __invoke(Request $request, Supplier $supplier, SupplierDossierPdfFactory $factory): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->isAdministrator() && ! $user->canAccessFarmaadminMenuKey('suppliers')) {
            abort(403, 'No tienes permiso para Proveedores.');
        }

        return $factory->download($supplier, $user);
    }
}
