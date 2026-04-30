<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderService;
use App\Models\PartnerCompany;
use App\Models\Product;
use App\Models\ProductTransfer;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use App\Support\Filament\BranchAuthScope;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Purchases\PurchasePaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SystemReportsCsvExporter
{
    public function stream(User $user, string $slug, Request $request): StreamedResponse
    {
        [$from, $to] = $this->parseDateRange($request);

        return match ($slug) {
            'ventas' => $this->streamVentas($user, $from, $to),
            'ventas-por-usuario' => $this->streamVentasPorUsuario($user, $from, $to),
            'ventas-por-sucursal' => $this->streamVentasPorSucursal($user, $from, $to),
            'companias-aliadas' => $this->streamPartnerCompanies(),
            'ordenes-servicio' => $this->streamOrderServices($user, $from, $to),
            'pedidos' => $this->streamPedidos($user, $from, $to),
            'traslados-por-usuario' => $this->streamTrasladosPorUsuario($user, $from, $to),
            'traslados-por-sucursal' => $this->streamTrasladosPorSucursal($user, $from, $to),
            'traslados-costos' => $this->streamTrasladosCostos($user, $from, $to),
            'clientes' => $this->streamClientes(),
            'catalogo-productos' => $this->streamCatalogoProductos(),
            'top-clientes-sucursal' => $this->streamTopClientesPorSucursal($user, $from, $to, (int) $request->query('top', 10)),
            'productos-mas-vendidos' => $this->streamProductosMasVendidos($user, $from, $to, (string) $request->query('agrupar', 'sucursal')),
            'inventario' => $this->streamInventario($user, (string) $request->query('moneda', 'ambas')),
            'tasas-bcv' => $this->streamTasasBcv($from, $to),
            'ingresos-aliados' => $this->streamIngresosAliados($user, $from, $to),
            'compras' => $this->streamCompras($user, (string) $request->query('filtro', 'todas')),
            default => abort(404),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function parseDateRange(Request $request): array
    {
        $to = Carbon::parse($request->query('hasta', now()->toDateString()))->endOfDay();
        $from = Carbon::parse($request->query('desde', $to->copy()->subDays(90)->toDateString()))->startOfDay();
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @param  list<list<string|int|float|null>>  $rows
     */
    private function csvResponse(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(static fn ($v): string => $v === null ? '' : (string) $v, $row), ';');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function streamVentas(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = Sale::query()->with(['branch:id,name', 'client:id,legal_name,trade_name'])
            ->whereBetween('sold_at', [$from, $to]);
        BranchAuthScope::applyToSalesQuery($q);
        $sales = $q->orderByDesc('sold_at')->get();

        $headers = ['id', 'sale_number', 'sucursal', 'cliente', 'total', 'payment_usd', 'payment_ves', 'bcv_ves_per_usd', 'sold_at', 'created_by'];
        $rows = [];
        foreach ($sales as $s) {
            $rows[] = [
                $s->id,
                $s->sale_number,
                $s->branch?->name,
                $s->client?->name,
                $s->total,
                $s->payment_usd,
                $s->payment_ves,
                $s->bcv_ves_per_usd,
                $s->sold_at?->toDateTimeString(),
                $s->created_by,
            ];
        }

        return $this->csvResponse('reporte-ventas-'.now()->format('YmdHis').'.csv', $headers, $rows);
    }

    private function streamVentasPorUsuario(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = Sale::query()->whereBetween('sold_at', [$from, $to]);
        BranchAuthScope::applyToSalesQuery($q);
        $agg = $q->selectRaw('created_by, COUNT(*) as ventas, SUM(total) as total_usd, SUM(payment_ves) as total_bs')
            ->groupBy('created_by')
            ->orderByDesc('total_usd')
            ->get();

        $headers = ['usuario_email_o_nombre', 'cantidad_ventas', 'suma_total_documento', 'suma_pago_bs'];
        $rows = [];
        foreach ($agg as $r) {
            $rows[] = [$r->created_by, $r->ventas, $r->total_usd, $r->total_bs];
        }

        return $this->csvResponse('reporte-ventas-por-usuario-'.now()->format('YmdHis').'.csv', $headers, $rows);
    }

    private function streamVentasPorSucursal(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = Sale::query()->whereBetween('sold_at', [$from, $to]);
        BranchAuthScope::applyToSalesQuery($q);
        $agg = $q->selectRaw('branch_id, COUNT(*) as ventas, SUM(total) as total_usd, SUM(payment_ves) as total_bs')
            ->groupBy('branch_id')
            ->orderByDesc('total_usd')
            ->get();
        $branches = Branch::query()->pluck('name', 'id');

        $headers = ['branch_id', 'sucursal', 'cantidad_ventas', 'suma_total', 'suma_pago_bs'];
        $rows = [];
        foreach ($agg as $r) {
            $rows[] = [$r->branch_id, $branches[$r->branch_id] ?? '', $r->ventas, $r->total_usd, $r->total_bs];
        }

        return $this->csvResponse('reporte-ventas-por-sucursal-'.now()->format('YmdHis').'.csv', $headers, $rows);
    }

    private function streamPartnerCompanies(): StreamedResponse
    {
        $rows = PartnerCompany::query()->orderBy('legal_name')->get();
        $headers = ['id', 'code', 'legal_name', 'trade_name', 'tax_id', 'is_active'];
        $data = [];
        foreach ($rows as $p) {
            $data[] = [$p->id, $p->code, $p->legal_name, $p->trade_name, $p->tax_id, $p->is_active ? '1' : '0'];
        }

        return $this->csvResponse('reporte-companias-aliadas-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamOrderServices(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = OrderService::query()->with(['branch:id,name', 'client:id,name'])
            ->where(function ($w) use ($from, $to): void {
                $w->whereBetween('ordered_at', [$from, $to])
                    ->orWhereBetween('created_at', [$from, $to]);
            });
        BranchAuthScope::apply($q);
        $list = $q->orderByDesc('id')->limit(50_000)->get();

        $headers = ['id', 'service_order_number', 'sucursal', 'cliente', 'status', 'total', 'ordered_at'];
        $data = [];
        foreach ($list as $o) {
            $data[] = [
                $o->id,
                $o->service_order_number,
                $o->branch?->name,
                $o->client?->name,
                $o->status,
                $o->total,
                $o->ordered_at,
            ];
        }

        return $this->csvResponse('reporte-ordenes-servicio-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamPedidos(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = Order::query()->with(['branch:id,name', 'partnerCompany:id,trade_name'])
            ->whereBetween('created_at', [$from, $to]);
        BranchAuthScope::applyToOrdersTableQuery($q);
        $list = $q->orderByDesc('id')->limit(50_000)->get();

        $headers = ['id', 'order_number', 'sucursal', 'aliado', 'status', 'total', 'created_at'];
        $data = [];
        foreach ($list as $o) {
            $data[] = [
                $o->id,
                $o->order_number,
                $o->branch?->name,
                $o->partnerCompany?->trade_name,
                $o->status instanceof OrderStatus ? $o->status->value : (string) $o->status,
                $o->total,
                $o->created_at?->toDateTimeString(),
            ];
        }

        return $this->csvResponse('reporte-pedidos-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function scopeTransfers(User $user): Builder
    {
        $q = ProductTransfer::query();
        if ($user->isAdministrator() || $user->isDeliveryUser()) {
            return $q;
        }
        $ids = $user->restrictedBranchIdsForQueries();
        if ($ids === []) {
            return $q->whereRaw('1 = 0');
        }

        return $q->where(function ($w) use ($ids): void {
            $w->whereIn('from_branch_id', $ids)->orWhereIn('to_branch_id', $ids);
        });
    }

    private function streamTrasladosPorUsuario(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = $this->scopeTransfers($user)->whereBetween('created_at', [$from, $to]);
        $agg = $q->clone()->selectRaw('created_by, COUNT(*) as n, SUM(COALESCE(total_transfer_cost,0)) as costo')
            ->groupBy('created_by')
            ->orderByDesc('n')
            ->get();

        $headers = ['creado_por', 'traslados', 'costo_total_traslados'];
        $data = [];
        foreach ($agg as $r) {
            $data[] = [$r->created_by, $r->n, $r->costo];
        }

        return $this->csvResponse('reporte-traslados-por-usuario-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamTrasladosPorSucursal(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = $this->scopeTransfers($user)->whereBetween('created_at', [$from, $to]);
        $fromAgg = $q->clone()->selectRaw('from_branch_id as branch_id, COUNT(*) as salidas')
            ->groupBy('from_branch_id');
        $toAgg = $this->scopeTransfers($user)->whereBetween('created_at', [$from, $to])
            ->selectRaw('to_branch_id as branch_id, COUNT(*) as entradas')
            ->groupBy('to_branch_id');

        $merged = collect();
        foreach ($fromAgg->get() as $r) {
            $merged->put($r->branch_id, ['salidas' => (int) $r->salidas, 'entradas' => 0]);
        }
        foreach ($toAgg->get() as $r) {
            $bid = (int) $r->branch_id;
            $cur = $merged->get($bid, ['salidas' => 0, 'entradas' => 0]);
            $cur['entradas'] = (int) $r->entradas;
            $merged->put($bid, $cur);
        }
        $names = Branch::query()->pluck('name', 'id');

        $headers = ['branch_id', 'sucursal', 'traslados_salida', 'traslados_entrada'];
        $data = [];
        foreach ($merged as $bid => $v) {
            $data[] = [$bid, $names[$bid] ?? '', $v['salidas'], $v['entradas']];
        }

        return $this->csvResponse('reporte-traslados-por-sucursal-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamTrasladosCostos(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = $this->scopeTransfers($user)->whereBetween('created_at', [$from, $to]);
        $list = $q->orderByDesc('id')->get(['id', 'code', 'from_branch_id', 'to_branch_id', 'total_transfer_cost', 'status', 'created_at', 'created_by']);

        $headers = ['id', 'code', 'from_branch_id', 'to_branch_id', 'total_transfer_cost', 'status', 'created_at', 'created_by'];
        $data = [];
        foreach ($list as $t) {
            $data[] = [
                $t->id,
                $t->code,
                $t->from_branch_id,
                $t->to_branch_id,
                $t->total_transfer_cost,
                $t->status instanceof \BackedEnum ? $t->status->value : (string) $t->status,
                $t->created_at?->toDateTimeString(),
                $t->created_by,
            ];
        }

        return $this->csvResponse('reporte-traslados-costos-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamClientes(): StreamedResponse
    {
        $rows = Client::query()->orderBy('name')->limit(100_000)->get();
        $headers = ['id', 'name', 'document_type', 'document_number', 'email', 'phone', 'created_at'];
        $data = [];
        foreach ($rows as $c) {
            $data[] = [
                $c->id,
                $c->name,
                $c->document_type,
                $c->document_number,
                $c->email,
                $c->phone,
                $c->created_at?->toDateTimeString(),
            ];
        }

        return $this->csvResponse('reporte-clientes-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamCatalogoProductos(): StreamedResponse
    {
        $rows = Product::query()
            ->with(['supplier:id', 'productCategory:id,name'])
            ->orderBy('name')
            ->limit(100_000)
            ->get();

        $headers = [
            'id',
            'supplier_id',
            'barcode',
            'sku',
            'name',
            'slug',
            'description',
            'image',
            'categoria',
            'brand',
            'presentation',
            'unit_of_measure',
            'unit_content',
            'net_content_label',
            'sale_price',
            'cost_price',
            'discount_percent',
            'applies_vat',
            'active_ingredient',
            'concentration',
            'presentation_type',
            'requires_prescription',
            'is_controlled_substance',
            'health_registration_number',
            'ingredients',
            'allergens',
            'nutritional_information',
            'manufacturer',
            'model',
            'warranty_months',
            'medical_device_class',
            'requires_calibration',
            'storage_conditions',
            'requires_expiry_on_purchase',
            'expiration_date',
            'is_active',
            'created_by',
            'updated_by',
            'created_at',
            'updated_at',
        ];

        $data = [];
        foreach ($rows as $p) {
            $activeIngredient = $p->active_ingredient;
            if (is_array($activeIngredient)) {
                $activeIngredientCsv = json_encode($activeIngredient, JSON_UNESCAPED_UNICODE) ?: '';
            } else {
                $activeIngredientCsv = $activeIngredient !== null ? (string) $activeIngredient : '';
            }

            $data[] = [
                $p->id,
                $p->supplier_id,
                $p->barcode,
                $p->sku,
                $p->name,
                $p->slug,
                $p->description,
                $p->image,
                $p->productCategory?->name,
                $p->brand,
                $p->presentation,
                $p->unit_of_measure,
                $p->unit_content,
                $p->net_content_label,
                $p->sale_price,
                $p->cost_price,
                $p->discount_percent,
                $p->applies_vat ? '1' : '0',
                $activeIngredientCsv,
                $p->concentration,
                $p->presentation_type,
                $p->requires_prescription ? '1' : '0',
                $p->is_controlled_substance ? '1' : '0',
                $p->health_registration_number,
                $p->ingredients,
                $p->allergens,
                $p->nutritional_information,
                $p->manufacturer,
                $p->model,
                $p->warranty_months,
                $p->medical_device_class,
                $p->requires_calibration ? '1' : '0',
                $p->storage_conditions,
                $p->requires_expiry_on_purchase ? '1' : '0',
                $p->expiration_date?->toDateString(),
                $p->is_active ? '1' : '0',
                $p->created_by,
                $p->updated_by,
                $p->created_at?->toDateTimeString(),
                $p->updated_at?->toDateTimeString(),
            ];
        }

        return $this->csvResponse('reporte-catalogo-productos-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamTopClientesPorSucursal(User $user, Carbon $from, Carbon $to, int $top): StreamedResponse
    {
        $top = in_array($top, [5, 10, 20], true) ? $top : 10;
        $q = Sale::query()
            ->selectRaw('branch_id, client_id, SUM(total) as total_comprado')
            ->whereBetween('sold_at', [$from, $to])
            ->whereNotNull('client_id')
            ->groupBy('branch_id', 'client_id');
        BranchAuthScope::applyToSalesQuery($q);
        $raw = $q->get();

        $branches = Branch::query()->pluck('name', 'id');
        $clients = Client::query()->pluck('name', 'id');

        $headers = ['sucursal', 'cliente_id', 'cliente', 'total_comprado_usd', 'rank_en_sucursal'];
        $data = [];
        foreach ($raw->groupBy('branch_id') as $branchId => $group) {
            $sorted = $group->sortByDesc('total_comprado')->values();
            $i = 1;
            foreach ($sorted->take($top) as $row) {
                $data[] = [
                    $branches[$row->branch_id] ?? $row->branch_id,
                    $row->client_id,
                    $clients[$row->client_id] ?? '',
                    $row->total_comprado,
                    $i,
                ];
                $i++;
            }
        }

        return $this->csvResponse('reporte-top-clientes-sucursal-'.$top.'-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamProductosMasVendidos(User $user, Carbon $from, Carbon $to, string $agrupar): StreamedResponse
    {
        $saleIds = Sale::query()->whereBetween('sold_at', [$from, $to]);
        BranchAuthScope::applyToSalesQuery($saleIds);
        $ids = $saleIds->pluck('id');

        $base = SaleItem::query()->whereIn('sale_id', $ids);

        if ($agrupar === 'categoria') {
            $agg = $base->clone()
                ->join('products', 'products.id', '=', 'sale_items.product_id')
                ->join('product_categories', 'product_categories.id', '=', 'products.product_category_id')
                ->selectRaw('product_categories.name as grupo, sale_items.product_id, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as total')
                ->groupBy('grupo', 'sale_items.product_id')
                ->orderByDesc('qty')
                ->limit(500)
                ->get();
            $headers = ['categoria', 'product_id', 'cantidad', 'total_lineas'];
            $data = [];
            foreach ($agg as $r) {
                $data[] = [$r->grupo, $r->product_id, $r->qty, $r->total];
            }

            return $this->csvResponse('reporte-productos-vendidos-categoria-'.now()->format('YmdHis').'.csv', $headers, $data);
        }

        $agg = $base->clone()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->selectRaw('sales.branch_id, sale_items.product_id, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total) as total')
            ->groupBy('sales.branch_id', 'sale_items.product_id')
            ->orderByDesc('qty')
            ->limit(2000)
            ->get();
        $branches = Branch::query()->pluck('name', 'id');
        $products = Product::query()->pluck('name', 'id');

        $headers = ['sucursal', 'product_id', 'producto', 'cantidad', 'total_lineas'];
        $data = [];
        foreach ($agg as $r) {
            $data[] = [
                $branches[$r->branch_id] ?? $r->branch_id,
                $r->product_id,
                $products[$r->product_id] ?? '',
                $r->qty,
                $r->total,
            ];
        }

        return $this->csvResponse('reporte-productos-vendidos-sucursal-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamInventario(User $user, string $moneda): StreamedResponse
    {
        $q = Inventory::query()->with(['branch:id,name', 'product:id,name,sku']);
        BranchAuthScope::apply($q);
        $rows = $q->orderBy('branch_id')->limit(100_000)->get();

        if ($moneda === 'usd') {
            $headers = ['sucursal', 'sku', 'producto', 'cantidad', 'cost_price', 'cost_plus_vat', 'final_price_without_vat'];
        } elseif ($moneda === 'ves') {
            $headers = ['sucursal', 'sku', 'producto', 'cantidad', 'final_price_with_vat', 'vat_final_price_amount'];
        } else {
            $headers = ['sucursal', 'sku', 'producto', 'cantidad', 'cost_price', 'cost_plus_vat', 'final_price_without_vat', 'vat_final_price_amount', 'final_price_with_vat'];
        }

        $data = [];
        foreach ($rows as $inv) {
            if ($moneda === 'usd') {
                $data[] = [
                    $inv->branch?->name,
                    $inv->product?->sku,
                    $inv->product?->name,
                    $inv->quantity,
                    $inv->cost_price,
                    $inv->cost_plus_vat,
                    $inv->final_price_without_vat,
                ];
            } elseif ($moneda === 'ves') {
                $data[] = [
                    $inv->branch?->name,
                    $inv->product?->sku,
                    $inv->product?->name,
                    $inv->quantity,
                    $inv->final_price_with_vat,
                    $inv->vat_final_price_amount,
                ];
            } else {
                $data[] = [
                    $inv->branch?->name,
                    $inv->product?->sku,
                    $inv->product?->name,
                    $inv->quantity,
                    $inv->cost_price,
                    $inv->cost_plus_vat,
                    $inv->final_price_without_vat,
                    $inv->vat_final_price_amount,
                    $inv->final_price_with_vat,
                ];
            }
        }

        return $this->csvResponse('reporte-inventario-'.$moneda.'-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamTasasBcv(Carbon $from, Carbon $to): StreamedResponse
    {
        $rows = app(VenezuelaOfficialUsdVesRateClient::class)->officialRatesBetween($from, $to);
        $headers = ['fecha', 'promedio_bs_por_usd'];
        $data = [];
        foreach ($rows as $r) {
            $data[] = [$r['fecha'], $r['promedio']];
        }

        return $this->csvResponse('reporte-tasas-bcv-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamIngresosAliados(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $q = Order::query()
            ->selectRaw('partner_company_id, SUM(total) as ingresos_pedidos, COUNT(*) as pedidos')
            ->where('status', OrderStatus::Completed)
            ->whereNotNull('partner_company_id')
            ->whereBetween('created_at', [$from, $to]);
        BranchAuthScope::applyToOrdersTableQuery($q);
        $agg = $q->groupBy('partner_company_id')->orderByDesc('ingresos_pedidos')->get();
        $partners = PartnerCompany::query()->pluck('trade_name', 'id');

        $headers = ['partner_company_id', 'compania_aliada', 'total_pedidos_completados', 'suma_total_pedidos'];
        $data = [];
        foreach ($agg as $r) {
            $data[] = [
                $r->partner_company_id,
                $partners[$r->partner_company_id] ?? '',
                $r->pedidos,
                $r->ingresos_pedidos,
            ];
        }

        return $this->csvResponse('reporte-ingresos-aliados-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamCompras(User $user, string $filtro): StreamedResponse
    {
        $q = Purchase::query()->with(['branch:id,name', 'supplier:id,legal_name,trade_name', 'accountsPayable:id,purchase_id,status']);
        BranchAuthScope::apply($q);

        if ($filtro === 'cxp_por_pagar') {
            $q->whereHas('accountsPayable', fn ($w) => $w->where('status', AccountsPayableStatus::POR_PAGAR));
        } elseif ($filtro === 'historico_pagado') {
            $q->where(function ($w): void {
                $w->where('payment_status', PurchasePaymentStatus::PAGADO_CONTADO)
                    ->orWhereHas('accountsPayable', fn ($ap) => $ap->where('status', AccountsPayableStatus::PAGADO));
            });
        }

        $list = $q->orderByDesc('id')->limit(50_000)->get();

        $headers = ['id', 'purchase_number', 'sucursal', 'proveedor', 'total', 'payment_status', 'cxp_status', 'supplier_invoice_date'];
        $data = [];
        foreach ($list as $p) {
            $data[] = [
                $p->id,
                $p->purchase_number,
                $p->branch?->name,
                $p->supplier?->trade_name ?: $p->supplier?->legal_name,
                $p->total,
                (string) $p->payment_status,
                $p->accountsPayable !== null ? (string) $p->accountsPayable->status : '',
                $p->supplier_invoice_date?->toDateString(),
            ];
        }

        return $this->csvResponse('reporte-compras-'.$filtro.'-'.now()->format('YmdHis').'.csv', $headers, $data);
    }
}
