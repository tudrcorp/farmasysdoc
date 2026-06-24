<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\SaleStatus;
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
use App\Support\Purchases\LotExpirationMonthYear;
use App\Support\Purchases\PurchasePaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class SystemReportsCsvExporter
{
    public function stream(User $user, string $slug, Request $request): StreamedResponse
    {
        [$from, $to] = $this->parseDateRange($request);

        return match ($slug) {
            'ventas' => $this->streamVentas($user, $from, $to),
            'ventas-global-sucursal' => $this->streamVentasGlobalSucursal($user, $from, $to),
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
            'inventario' => $this->streamInventario(
                $user,
                (string) $request->query('moneda', 'ambas'),
                (string) $request->query('vista', 'detalle'),
            ),
            'inventario-vencimientos' => $this->streamInventarioVencimientos($user, (string) $request->query('filtro', 'vencidos_y_por_vencer')),
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
        $q = Sale::query()->with(['branch:id,name', 'client:id,name'])
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

    private function streamVentasGlobalSucursal(User $user, Carbon $from, Carbon $to): StreamedResponse
    {
        $baseQuery = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereNotNull('branch_id')
            ->whereBetween('sold_at', [$from, $to]);
        BranchAuthScope::applyToSalesQuery($baseQuery);

        $customerCountExpression = $this->salesCustomerCountExpression();
        $globalAgg = (clone $baseQuery)
            ->selectRaw("COUNT(*) as ventas, {$customerCountExpression} as clientes, SUM(CAST(total AS DECIMAL(14,2))) as total_documento, SUM(CAST(payment_usd AS DECIMAL(14,2))) as cobro_usd, SUM(CAST(payment_ves AS DECIMAL(14,2))) as cobro_bs")
            ->first();

        $branchAgg = (clone $baseQuery)
            ->selectRaw("branch_id, COUNT(*) as ventas, {$customerCountExpression} as clientes, SUM(CAST(total AS DECIMAL(14,2))) as total_documento, SUM(CAST(payment_usd AS DECIMAL(14,2))) as cobro_usd, SUM(CAST(payment_ves AS DECIMAL(14,2))) as cobro_bs")
            ->groupBy('branch_id')
            ->orderBy('branch_id')
            ->get();

        $branches = Branch::query()->pluck('name', 'id');

        $globalCobroUsd = round((float) ($globalAgg->cobro_usd ?? 0), 2);
        $globalCobroBs = round((float) ($globalAgg->cobro_bs ?? 0), 2);
        $globalCobroBsEnUsd = round($this->sumVesConvertedToUsd(clone $baseQuery), 2);
        $globalTotalGeneral = round($globalCobroUsd + $globalCobroBsEnUsd, 2);
        $globalVentas = (int) ($globalAgg->ventas ?? 0);
        $globalClientes = (int) ($globalAgg->clientes ?? 0);
        $globalTicket = $globalClientes > 0 ? round($globalTotalGeneral / $globalClientes, 2) : 0.0;

        $headers = [
            'nivel',
            'branch_id',
            'sucursal',
            'cantidad_ventas',
            'clientes_unicos',
            'total_documento_usd',
            'cobro_usd',
            'cobro_bs',
            'cobro_bs_en_usd',
            'total_general_usd',
            'ticket_promedio_usd',
            'desde',
            'hasta',
        ];

        $data = [[
            'GLOBAL',
            '',
            'TODAS LAS SUCURSALES',
            $globalVentas,
            $globalClientes,
            round((float) ($globalAgg->total_documento ?? 0), 2),
            $globalCobroUsd,
            $globalCobroBs,
            $globalCobroBsEnUsd,
            $globalTotalGeneral,
            $globalTicket,
            $from->toDateString(),
            $to->toDateString(),
        ]];

        foreach ($branchAgg as $row) {
            $branchQuery = (clone $baseQuery)->where('branch_id', $row->branch_id);
            $cobroUsd = round((float) $row->cobro_usd, 2);
            $cobroBs = round((float) $row->cobro_bs, 2);
            $cobroBsEnUsd = round($this->sumVesConvertedToUsd(clone $branchQuery), 2);
            $totalGeneral = round($cobroUsd + $cobroBsEnUsd, 2);
            $clientes = (int) $row->clientes;
            $ticket = $clientes > 0 ? round($totalGeneral / $clientes, 2) : 0.0;

            $data[] = [
                'SUCURSAL',
                $row->branch_id,
                $branches[$row->branch_id] ?? '',
                (int) $row->ventas,
                $clientes,
                round((float) $row->total_documento, 2),
                $cobroUsd,
                $cobroBs,
                $cobroBsEnUsd,
                $totalGeneral,
                $ticket,
                $from->toDateString(),
                $to->toDateString(),
            ];
        }

        return $this->csvResponse('reporte-ventas-global-sucursal-'.now()->format('YmdHis').'.csv', $headers, $data);
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

    private function streamInventario(User $user, string $moneda, string $vista): StreamedResponse
    {
        $includeBs = $moneda !== 'usd';
        $bcvRate = $includeBs
            ? app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now())
            : null;

        $q = Inventory::query()->with(['branch:id,name', 'product:id,name,sku']);
        BranchAuthScope::apply($q);
        $rows = $q->orderBy('branch_id')->orderBy('product_id')->limit(100_000)->get();

        if ($vista === 'resumen_sucursal') {
            return $this->streamInventarioResumenSucursal($rows, $includeBs, $bcvRate);
        }

        $headers = [
            'sucursal',
            'sku',
            'producto',
            'cantidad',
            'cantidad_disponible',
            'costo_unitario_usd',
            'valor_costo_usd',
            'costo_con_iva_unitario_usd',
            'valor_costo_con_iva_usd',
            'precio_venta_sin_iva_unitario_usd',
            'valor_venta_sin_iva_usd',
            'precio_venta_con_iva_unitario_usd',
            'valor_venta_con_iva_usd',
        ];

        if ($includeBs && $bcvRate !== null) {
            $headers[] = 'tasa_bcv_bs_por_usd';
            $headers[] = 'valor_costo_bs';
            $headers[] = 'valor_venta_con_iva_bs';
        }

        $data = [];
        foreach ($rows as $inv) {
            $qty = (float) $inv->quantity;
            $available = (float) $inv->available_quantity;
            $costPrice = (float) $inv->cost_price;
            $costPlusVat = (float) $inv->cost_plus_vat;
            $finalWithoutVat = (float) $inv->final_price_without_vat;
            $finalWithVat = (float) $inv->final_price_with_vat;

            $valorCosto = round($qty * $costPrice, 2);
            $valorCostoConIva = round($qty * $costPlusVat, 2);
            $valorVentaSinIva = round($qty * $finalWithoutVat, 2);
            $valorVentaConIva = round($qty * $finalWithVat, 2);

            $line = [
                $inv->branch?->name,
                $inv->product?->sku,
                $inv->product?->name,
                $this->formatDecimal($qty, 3),
                $this->formatDecimal($available, 3),
                $this->formatDecimal($costPrice),
                $this->formatDecimal($valorCosto),
                $this->formatDecimal($costPlusVat),
                $this->formatDecimal($valorCostoConIva),
                $this->formatDecimal($finalWithoutVat),
                $this->formatDecimal($valorVentaSinIva),
                $this->formatDecimal($finalWithVat),
                $this->formatDecimal($valorVentaConIva),
            ];

            if ($includeBs && $bcvRate !== null) {
                $line[] = $this->formatDecimal($bcvRate);
                $line[] = $this->formatDecimal($valorCosto * $bcvRate);
                $line[] = $this->formatDecimal($valorVentaConIva * $bcvRate);
            }

            $data[] = $line;
        }

        $suffix = $includeBs ? 'usd-bs' : 'usd';

        return $this->csvResponse('reporte-valoracion-inventario-'.$suffix.'-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    /**
     * @param  Collection<int, Inventory>  $rows
     */
    private function streamInventarioResumenSucursal($rows, bool $includeBs, ?float $bcvRate): StreamedResponse
    {
        $headers = [
            'sucursal',
            'productos_distintos',
            'cantidad_total',
            'valor_costo_usd',
            'valor_costo_con_iva_usd',
            'valor_venta_sin_iva_usd',
            'valor_venta_con_iva_usd',
        ];

        if ($includeBs && $bcvRate !== null) {
            $headers[] = 'tasa_bcv_bs_por_usd';
            $headers[] = 'valor_costo_bs';
            $headers[] = 'valor_venta_con_iva_bs';
        }

        $grouped = [];
        foreach ($rows as $inv) {
            $branchName = (string) ($inv->branch?->name ?? 'Sin sucursal');
            $qty = (float) $inv->quantity;

            if (! isset($grouped[$branchName])) {
                $grouped[$branchName] = [
                    'productos' => 0,
                    'cantidad' => 0.0,
                    'valor_costo' => 0.0,
                    'valor_costo_iva' => 0.0,
                    'valor_venta_sin_iva' => 0.0,
                    'valor_venta_con_iva' => 0.0,
                ];
            }

            $grouped[$branchName]['productos']++;
            $grouped[$branchName]['cantidad'] += $qty;
            $grouped[$branchName]['valor_costo'] += $qty * (float) $inv->cost_price;
            $grouped[$branchName]['valor_costo_iva'] += $qty * (float) $inv->cost_plus_vat;
            $grouped[$branchName]['valor_venta_sin_iva'] += $qty * (float) $inv->final_price_without_vat;
            $grouped[$branchName]['valor_venta_con_iva'] += $qty * (float) $inv->final_price_with_vat;
        }

        ksort($grouped);

        $data = [];
        foreach ($grouped as $branchName => $totals) {
            $line = [
                $branchName,
                $totals['productos'],
                $this->formatDecimal($totals['cantidad'], 3),
                $this->formatDecimal($totals['valor_costo']),
                $this->formatDecimal($totals['valor_costo_iva']),
                $this->formatDecimal($totals['valor_venta_sin_iva']),
                $this->formatDecimal($totals['valor_venta_con_iva']),
            ];

            if ($includeBs && $bcvRate !== null) {
                $line[] = $this->formatDecimal($bcvRate);
                $line[] = $this->formatDecimal($totals['valor_costo'] * $bcvRate);
                $line[] = $this->formatDecimal($totals['valor_venta_con_iva'] * $bcvRate);
            }

            $data[] = $line;
        }

        $suffix = $includeBs ? 'resumen-usd-bs' : 'resumen-usd';

        return $this->csvResponse('reporte-valoracion-inventario-'.$suffix.'-'.now()->format('YmdHis').'.csv', $headers, $data);
    }

    private function streamInventarioVencimientos(User $user, string $filtro): StreamedResponse
    {
        $warningDays = (int) config('inventory.lot_near_expiry_days.warning', 60);
        $orderExpr = LotExpirationMonthYear::mysqlOrderByExpression('pl.expiration_month_year');

        $q = DB::table('inventory_lot_balances as ilb')
            ->join('product_lots as pl', 'pl.id', '=', 'ilb.product_lot_id')
            ->join('products as p', 'p.id', '=', 'ilb.product_id')
            ->join('branches as b', 'b.id', '=', 'ilb.branch_id')
            ->leftJoin('inventories as inv', function ($join): void {
                $join->on('inv.branch_id', '=', 'ilb.branch_id')
                    ->on('inv.product_id', '=', 'ilb.product_id');
            })
            ->where('p.requires_expiry_on_purchase', true)
            ->where('ilb.quantity_remaining', '>', 0)
            ->select([
                'ilb.id as balance_id',
                'b.name as sucursal',
                'ilb.branch_id',
                'ilb.product_id',
                'p.sku',
                'p.name as producto',
                'ilb.product_lot_id',
                'pl.expiration_month_year',
                'ilb.quantity_remaining',
                'pl.supplier_invoice_number',
                'inv.cost_price',
            ])
            ->orderBy('b.name')
            ->orderByRaw("{$orderExpr} ASC");

        if (! $user->isAdministrator() && ! $user->isDeliveryUser()) {
            $branchIds = $user->restrictedBranchIdsForQueries();
            if ($branchIds === []) {
                $q->whereRaw('1 = 0');
            } else {
                $q->whereIn('ilb.branch_id', $branchIds);
            }
        }

        $rows = $q->limit(100_000)->get();

        $headers = [
            'sucursal',
            'product_id',
            'sku',
            'producto',
            'product_lot_id',
            'vencimiento_mm_yyyy',
            'dias_hasta_vencer',
            'estado',
            'cantidad_lote',
            'factura_proveedor',
            'costo_unitario_usd',
            'valor_costo_lote_usd',
            'umbral_por_vencer_dias',
        ];

        $data = [];
        foreach ($rows as $row) {
            $days = LotExpirationMonthYear::daysUntilExpiry((string) $row->expiration_month_year);
            if ($days === null) {
                continue;
            }

            $estado = match (true) {
                $days < 0 => 'vencido',
                $days <= (int) config('inventory.lot_near_expiry_days.critical', 30) => 'por_vencer_critico',
                $days <= $warningDays => 'por_vencer',
                default => 'vigente',
            };

            if ($filtro === 'vencidos' && $days >= 0) {
                continue;
            }
            if ($filtro === 'por_vencer' && ($days < 0 || $days > $warningDays)) {
                continue;
            }
            if ($filtro === 'vencidos_y_por_vencer' && $days > $warningDays) {
                continue;
            }

            $qty = (float) $row->quantity_remaining;
            $cost = (float) ($row->cost_price ?? 0);

            $data[] = [
                $row->sucursal,
                $row->product_id,
                $row->sku,
                $row->producto,
                $row->product_lot_id,
                $row->expiration_month_year,
                $days,
                $estado,
                $this->formatDecimal($qty, 3),
                $row->supplier_invoice_number,
                $this->formatDecimal($cost),
                $this->formatDecimal($qty * $cost),
                $warningDays,
            ];
        }

        return $this->csvResponse('reporte-inventario-vencimientos-'.$filtro.'-'.now()->format('YmdHis').'.csv', $headers, $data);
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

    private function salesCustomerCountExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "COUNT(DISTINCT CASE WHEN client_id IS NOT NULL THEN 'c' || client_id ELSE 's' || id END)",
            'pgsql' => "COUNT(DISTINCT CASE WHEN client_id IS NOT NULL THEN ('c' || client_id::text) ELSE ('s' || id::text) END)",
            default => "COUNT(DISTINCT CASE WHEN client_id IS NOT NULL THEN CONCAT('c', client_id) ELSE CONCAT('s', id) END)",
        };
    }

    /**
     * @param  Builder<Sale>  $query
     */
    private function sumVesConvertedToUsd(Builder $query): float
    {
        $fallbackRate = $this->fallbackBcvRate();
        $rateExpression = $this->bcvRateSqlExpression($fallbackRate);
        $vesCast = match (DB::connection()->getDriverName()) {
            'sqlite' => 'CAST(payment_ves AS REAL)',
            default => 'CAST(payment_ves AS DECIMAL(14,2))',
        };

        $value = $query
            ->selectRaw(
                "SUM(CASE WHEN {$vesCast} > 0 THEN {$vesCast} / ({$rateExpression}) ELSE 0 END) as ves_converted_usd",
            )
            ->value('ves_converted_usd');

        return (float) ($value ?? 0);
    }

    private function bcvRateSqlExpression(float $fallbackRate): string
    {
        $fallback = number_format($fallbackRate, 6, '.', '');

        return match (DB::connection()->getDriverName()) {
            'sqlite' => "COALESCE(NULLIF(CAST(bcv_ves_per_usd AS REAL), 0), {$fallback})",
            default => "COALESCE(NULLIF(CAST(bcv_ves_per_usd AS DECIMAL(14,6)), 0), {$fallback})",
        };
    }

    private function fallbackBcvRate(): float
    {
        $fallback = config('fiscal.fallback_ves_usd_rate');

        return is_numeric($fallback) && (float) $fallback > 0 ? (float) $fallback : 1.0;
    }

    private function formatDecimal(float $value, int $decimals = 2): string
    {
        return number_format($value, $decimals, '.', '');
    }
}
