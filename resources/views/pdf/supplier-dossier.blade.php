@php
    $money = static fn (float $value): string => number_format($value, 2, ',', '.');
    $retentionPercent = $supplier->seniat_retention_percent;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha del proveedor {{ $supplier->displayName() }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 10mm 10mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 8pt;
            color: #1a1a1a;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #0e5c5f;
            padding-bottom: 8px;
            margin-bottom: 10px;
        }
        .header table { width: 100%; border-collapse: collapse; }
        .header img { max-height: 54px; width: auto; }
        h1 {
            font-size: 14pt;
            color: #0e5c5f;
            margin: 0 0 2px 0;
        }
        .subtitle { font-size: 8pt; color: #444; }
        .meta { font-size: 7pt; color: #666; text-align: right; }
        .badge {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 3px;
            background: #e6f2f3;
            color: #0e5c5f;
            font-weight: bold;
            font-size: 7pt;
        }
        .badge--warn { background: #fef3c7; color: #92400e; }
        .kpis {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 -6px 10px -6px;
        }
        .kpis td {
            background: #f0fafb;
            border: 1px solid #0e5c5f;
            border-radius: 4px;
            padding: 6px 8px;
            width: 20%;
            vertical-align: top;
        }
        .kpis .label {
            font-size: 6.5pt;
            text-transform: uppercase;
            color: #0e5c5f;
            font-weight: bold;
        }
        .kpis .value { font-size: 11pt; font-weight: bold; margin-top: 2px; }
        .kpis .hint { font-size: 6.5pt; color: #555; margin-top: 1px; }
        table.info {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            font-size: 7.5pt;
        }
        table.info th, table.info td {
            border: 1px solid #ddd;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
        }
        table.info th { background: #f7f7f7; width: 14%; }
        h2 {
            font-size: 10pt;
            color: #0e5c5f;
            margin: 14px 0 6px 0;
            border-bottom: 1px solid #0e5c5f;
            padding-bottom: 3px;
        }
        h2 .count { font-size: 7.5pt; color: #666; font-weight: normal; }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            font-size: 7pt;
            margin-bottom: 6px;
        }
        table.lines th, table.lines td {
            border: 1px solid #ccc;
            padding: 3px 4px;
            vertical-align: top;
        }
        table.lines th { background: #f0fafb; font-weight: bold; text-align: left; }
        table.lines tfoot td { background: #f7f7f7; font-weight: bold; }
        tr.excluded td { color: #999; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #666; font-size: 6.5pt; }
        .empty {
            padding: 8px;
            text-align: center;
            color: #666;
            border: 1px dashed #ccc;
        }
        .footer {
            margin-top: 14px;
            font-size: 6.5pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="width: 70%;">
                    <h1>Ficha del proveedor</h1>
                    <div class="subtitle">
                        <strong>{{ $supplier->legal_name }}</strong>
                        @if (filled($supplier->trade_name) && $supplier->trade_name !== $supplier->legal_name)
                            · {{ $supplier->trade_name }}
                        @endif
                    </div>
                    <div class="subtitle" style="margin-top: 3px;">
                        <span class="badge">{{ $supplier->code ?: \App\Models\Supplier::formatCode($supplier->getKey()) }}</span>
                        <span class="badge">RIF {{ $supplier->tax_id ?: '—' }}</span>
                        @if ($retentionPercent !== null)
                            <span class="badge">Retención IVA {{ number_format((float) $retentionPercent, 2, ',', '.') }}%</span>
                        @else
                            <span class="badge badge--warn">Sin % de retención configurado</span>
                        @endif
                        <span class="badge {{ $supplier->is_active ? '' : 'badge--warn' }}">{{ $supplier->is_active ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                </td>
                <td class="meta" style="width: 30%;">
                    @if (! empty($pdf_logo_data_uri))
                        <img src="{{ $pdf_logo_data_uri }}" alt="Farmadoc"><br>
                    @endif
                    Todo el historial
                    @if ($is_branch_scoped)
                        · solo sucursales asignadas al usuario
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="kpis">
        <tr>
            <td>
                <div class="label">Total comprado</div>
                <div class="value">{{ $money($summary['purchased_usd']) }} USD</div>
                <div class="hint">Bs {{ $money($summary['purchased_ves']) }} · {{ $summary['purchases_count'] }} compra(s)</div>
            </td>
            <td>
                <div class="label">Total pagado</div>
                <div class="value">{{ $money($summary['paid_usd']) }} USD</div>
                <div class="hint">Bs {{ $money($summary['paid_ves']) }} · {{ $summary['paid_invoices_count'] }} factura(s)</div>
            </td>
            <td>
                <div class="label">Pendiente por pagar</div>
                <div class="value">{{ $money($summary['pending_usd']) }} USD</div>
                <div class="hint">Bs {{ $money($summary['pending_ves']) }} · {{ $summary['pending_invoices_count'] }} factura(s)</div>
            </td>
            <td>
                <div class="label">IVA retenido</div>
                <div class="value">Bs {{ $money($summary['retained_ves']) }}</div>
                <div class="hint">{{ $summary['retentions_count'] }} comprobante(s)</div>
            </td>
            <td>
                <div class="label">Actividad</div>
                <div class="value" style="font-size: 9pt;">Últ. compra {{ $summary['last_purchase_at'] ?? '—' }}</div>
                <div class="hint">Primera {{ $summary['first_purchase_at'] ?? '—' }} · Últ. pago {{ $summary['last_payment_at'] ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <h2>Datos generales</h2>
    <table class="info">
        <tr>
            <th>Correo</th>
            <td>{{ $supplier->email ?: '—' }}</td>
            <th>Teléfono</th>
            <td>{{ $supplier->phone ?: '—' }}</td>
            <th>Celular / WhatsApp</th>
            <td>{{ $supplier->mobile_phone ?: '—' }}</td>
        </tr>
        <tr>
            <th>Dirección</th>
            <td colspan="3">
                {{ collect([$supplier->address, $supplier->city, $supplier->state, $supplier->country])->filter()->implode(', ') ?: '—' }}
            </td>
            <th>Sitio web</th>
            <td>{{ $supplier->website ?: '—' }}</td>
        </tr>
        <tr>
            <th>Contacto comercial</th>
            <td>{{ $supplier->contact_name ?: '—' }}</td>
            <th>Correo contacto</th>
            <td>{{ $supplier->contact_email ?: '—' }}</td>
            <th>Teléfono contacto</th>
            <td>{{ $supplier->contact_phone ?: '—' }}</td>
        </tr>
        <tr>
            <th>Condiciones de pago</th>
            <td>{{ $supplier->payment_terms ?: '—' }}</td>
            <th>Cuentas bancarias</th>
            <td colspan="3">
                @forelse ($supplier->bankAccounts as $bankAccount)
                    <div>
                        {{ $bankAccount->displayLabel() }}
                        @if (filled($bankAccount->phone))
                            <span class="muted">· Pago móvil {{ $bankAccount->phone }}</span>
                        @endif
                    </div>
                @empty
                    —
                @endforelse
            </td>
        </tr>
    </table>

    <h2>Compras <span class="count">({{ count($purchases) }})</span></h2>
    @if ($purchases === [])
        <div class="empty">Sin compras registradas.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th>Nº orden</th>
                    <th>Factura</th>
                    <th>Sucursal</th>
                    <th>Fecha</th>
                    <th>Estado</th>
                    <th class="num">Total documento</th>
                    <th class="num">Total USD</th>
                    <th>Pago</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($purchases as $purchase)
                    <tr @class(['excluded' => $purchase['is_excluded']])>
                        <td>{{ $purchase['purchase_number'] }}</td>
                        <td>{{ $purchase['invoice_number'] ?? '—' }}</td>
                        <td>{{ $purchase['branch_name'] }}</td>
                        <td>{{ $purchase['date'] ?? '—' }}</td>
                        <td>{{ $purchase['status'] }}</td>
                        <td class="num">{{ $purchase['document_total'] }}</td>
                        <td class="num">{{ $purchase['total_usd'] !== null ? $money($purchase['total_usd']) : '—' }}</td>
                        <td>{{ $purchase['payment_status'] }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6">Total comprado (sin borradores, cancelaciones ni anulaciones)</td>
                    <td class="num">{{ $money($summary['purchased_usd']) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2>Facturas pagadas <span class="count">({{ count($payments) }} pago(s))</span></h2>
    @if ($payments === [])
        <div class="empty">Sin pagos registrados.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th>Fecha de pago</th>
                    <th>Factura</th>
                    <th>Sucursal</th>
                    <th>Método</th>
                    <th>Banco / cuenta</th>
                    <th>Referencia</th>
                    <th class="num">USD</th>
                    <th class="num">Bs</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payments as $payment)
                    <tr>
                        <td>{{ $payment['paid_at'] ?? '—' }}</td>
                        <td>{{ $payment['invoice_number'] ?? '—' }}</td>
                        <td>{{ $payment['branch_name'] }}</td>
                        <td>{{ $payment['payment_method'] }}</td>
                        <td>{{ $payment['payment_bank'] ?? '—' }}</td>
                        <td>{{ $payment['payment_reference'] ?? '—' }}</td>
                        <td class="num">{{ $money($payment['amount_usd']) }}</td>
                        <td class="num">{{ $money($payment['amount_ves']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="6">Total pagado</td>
                    <td class="num">{{ $money($summary['paid_usd']) }}</td>
                    <td class="num">{{ $money($summary['paid_ves']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <h2>Comprobantes de pago <span class="count">({{ count($payment_proofs) }})</span></h2>
    @if ($payment_proofs === [])
        <div class="empty">Sin comprobantes de pago adjuntos.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Sucursal</th>
                    <th>Fecha de pago</th>
                    <th>Referencia</th>
                    <th class="num">Total USD</th>
                    <th>Tipo de archivo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($payment_proofs as $proof)
                    <tr>
                        <td>{{ $proof['invoice_number'] ?? '—' }}</td>
                        <td>{{ $proof['branch_name'] }}</td>
                        <td>{{ $proof['paid_at'] ?? '—' }}</td>
                        <td>{{ $proof['payment_reference'] ?? '—' }}</td>
                        <td class="num">{{ $money($proof['amount_usd']) }}</td>
                        <td>{{ $proof['proof_is_pdf'] ? 'PDF' : 'Imagen' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Retenciones de IVA <span class="count">({{ count($retentions) }})</span></h2>
    @if ($retentions === [])
        <div class="empty">Sin retenciones registradas en el Libro de Compras.</div>
    @else
        <table class="lines">
            <thead>
                <tr>
                    <th>Comprobante</th>
                    <th>Emisión</th>
                    <th>Período</th>
                    <th>Factura</th>
                    <th>Fecha factura</th>
                    <th class="num">Base imponible Bs</th>
                    <th class="num">IVA causado Bs</th>
                    <th class="num">% ret.</th>
                    <th class="num">IVA retenido Bs</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($retentions as $retention)
                    <tr>
                        <td>{{ $retention['voucher_number'] ?? '—' }}</td>
                        <td>{{ $retention['issue_date'] ?? '—' }}</td>
                        <td>{{ $retention['tax_period'] ?? '—' }}</td>
                        <td>{{ $retention['invoice_number'] ?? '—' }}</td>
                        <td>{{ $retention['invoice_date'] ?? '—' }}</td>
                        <td class="num">{{ $money($retention['taxable_base_ves']) }}</td>
                        <td class="num">{{ $money($retention['tax_caused_ves']) }}</td>
                        <td class="num">{{ $retention['retention_percent'] !== null ? number_format($retention['retention_percent'], 0, ',', '.').'%' : '—' }}</td>
                        <td class="num">{{ $money($retention['tax_retained_ves']) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="8">Total retenido</td>
                    <td class="num">{{ $money($summary['retained_ves']) }}</td>
                </tr>
            </tfoot>
        </table>
    @endif

    <div class="footer">
        Generado el {{ $generated_at }} por {{ $generated_by }}.
        Documento de respaldo interno · Farmadoc®.
    </div>
</body>
</html>
