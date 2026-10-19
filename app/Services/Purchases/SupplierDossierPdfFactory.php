<?php

namespace App\Services\Purchases;

use App\Models\Supplier;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF de la ficha del proveedor (mismo alcance por sucursal que el slide-over).
 */
final class SupplierDossierPdfFactory
{
    public function __construct(
        private readonly SupplierDossierBuilder $builder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function viewData(Supplier $supplier, ?User $actor = null): array
    {
        $payload = $this->builder->build($supplier, $actor);

        $logoPath = public_path('images/logos/farmadoc-ligth.png');
        $payload['pdf_logo_data_uri'] = is_readable($logoPath)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logoPath))
            : null;
        $payload['generated_at'] = now()->format('d/m/Y H:i');
        $payload['generated_by'] = $actor instanceof User
            ? (string) ($actor->email ?? $actor->name ?? 'usuario_'.$actor->getKey())
            : 'sistema';

        return $payload;
    }

    public function download(Supplier $supplier, ?User $actor = null): Response
    {
        return Pdf::loadView('pdf.supplier-dossier', $this->viewData($supplier, $actor))
            ->setPaper('a4', 'landscape')
            ->download($this->filename($supplier));
    }

    public function filename(Supplier $supplier): string
    {
        $slug = Str::slug($supplier->displayName()) ?: (string) $supplier->getKey();

        return 'ficha-proveedor-'.$slug.'.pdf';
    }
}
