<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de retención IVA {{ $voucher_number }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 8mm 8mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 7pt;
            color: #111;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .top-meta {
            width: 100%;
            margin-bottom: 4px;
        }
        .top-meta td { vertical-align: top; }
        .voucher-box {
            border: 1px solid #111;
            padding: 4px 8px;
            text-align: center;
            min-width: 160px;
        }
        .voucher-box .label {
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .voucher-box .value {
            font-size: 11pt;
            font-weight: bold;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .doc-title {
            text-align: center;
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 12px 0 4px;
        }
        .legal {
            text-align: center;
            font-size: 5.5pt;
            line-height: 1.25;
            margin: 14px 12% 6px;
        }
        .period {
            width: auto;
            margin: 0 auto 8px;
        }
        .period td {
            border: 1px solid #111;
            padding: 3px 10px;
            text-align: center;
            font-weight: bold;
        }
        .period .lbl {
            font-size: 6pt;
            display: block;
            font-weight: normal;
        }
        .parties {
            width: 100%;
            margin-bottom: 6px;
        }
        .parties > tbody > tr > td {
            width: 50%;
            vertical-align: top;
            padding: 0 3px;
        }
        .box {
            border: 1px solid #111;
            min-height: 58px;
        }
        .box-head {
            background: #f3f3f3;
            border-bottom: 1px solid #111;
            font-size: 5.5pt;
            font-weight: bold;
            text-align: center;
            padding: 2px 4px;
            text-transform: uppercase;
        }
        .box-body { padding: 4px 6px; }
        .box-body .name {
            font-size: 8pt;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .box-body .line {
            margin-bottom: 2px;
            font-size: 6.5pt;
        }
        .box-body .muted {
            font-size: 5.5pt;
            color: #333;
            text-transform: uppercase;
        }
        .ops {
            width: 100%;
            margin-top: 4px;
        }
        .ops th, .ops td {
            border: 1px solid #111;
            padding: 2px 3px;
            vertical-align: middle;
        }
        .ops th {
            background: #f3f3f3;
            font-size: 5pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            line-height: 1.15;
        }
        .ops td {
            font-size: 6.5pt;
            text-align: center;
        }
        .ops td.num { text-align: right; white-space: nowrap; }
        .ops td.left { text-align: left; }
        .ops tfoot td {
            font-weight: bold;
            background: #fafafa;
        }
        .saldo-wrap {
            width: 100%;
            margin-top: 6px;
        }
        .saldo-wrap td { vertical-align: middle; }
        .saldo-box {
            border: 1px solid #111;
            padding: 4px 8px;
            text-align: right;
            font-weight: bold;
            font-size: 8pt;
            min-width: 220px;
        }
        .footer-note {
            font-size: 5.5pt;
            margin-top: 8px;
            text-align: left;
        }
        .signs {
            width: 100%;
            margin-top: 48px;
        }
        .signs > tbody > tr > td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 20px;
        }
        .sign-block {
            width: 100%;
        }
        .sign-block td {
            text-align: center;
            vertical-align: top;
            padding: 0;
        }
        .sign-line {
            border-top: 1px solid #111;
            margin: 44px 24px 4px;
            height: 0;
            line-height: 0;
            font-size: 0;
        }
        .sign-label {
            font-size: 6pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.3;
            min-height: 10px;
        }
        .sign-date {
            font-size: 6pt;
            margin-top: 4px;
            line-height: 1.3;
            min-height: 10px;
        }
    </style>
</head>
<body>
    @php
        $fmt = static fn (?float $n): string => number_format((float) $n, 2, ',', '.');
    @endphp

    <table class="top-meta">
        <tr>
            <td style="width: 70%;"></td>
            <td style="width: 30%; text-align: right;">
                <div class="voucher-box">
                    <div class="label">Número de comprobante</div>
                    <div class="value">{{ $voucher_number }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="doc-title">Comprobante de retención del impuesto al valor agregado</div>
    <div class="legal">
        Providencia Administrativa N° SNAT/2025/000054 de fecha 14 de agosto de 2025, publicada en Gaceta Oficial
        N° 43.211 de fecha 18 de agosto de 2025. Según lo establecido en el Artículo 11 de la Providencia Administrativa
        N° SNAT/2015/0049 de fecha 10/08/2015 que establece las Normas Generales de Retención de IVA.
    </div>

    <table class="period">
        <tr>
            <td>
                <span class="lbl">Año</span>
                {{ $tax_period_year }}
            </td>
            <td>
                <span class="lbl">Mes</span>
                {{ $tax_period_month }}
            </td>
        </tr>
    </table>

    <table class="parties">
        <tr>
            <td>
                <div class="box">
                    <div class="box-head">Nombre y apellido o razón social del agente de retención</div>
                    <div class="box-body">
                        <div class="name">{{ $retention_agent_name }}</div>
                        <div class="line"><span class="muted">RIF agente de retención:</span> {{ $retention_agent_rif }}</div>
                        <div class="line"><span class="muted">Dirección fiscal:</span> {{ $retention_agent_address }}</div>
                        <div class="line"><span class="muted">Fecha de emisión:</span> {{ $issue_date ?: '— / — / —' }}</div>
                    </div>
                </div>
            </td>
            <td>
                <div class="box">
                    <div class="box-head">Nombre y apellido o razón social del proveedor</div>
                    <div class="box-body">
                        <div class="name">{{ $supplier_name }}</div>
                        <div class="line"><span class="muted">RIF del proveedor:</span> {{ $supplier_rif }}</div>
                        <div class="line"><span class="muted">Dirección fiscal:</span> {{ $supplier_address ?: '—' }}</div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table class="ops">
        <thead>
            <tr>
                <th style="width: 4%;">Nº op.</th>
                <th style="width: 7%;">Fecha factura</th>
                <th style="width: 10%;">Nº factura / ND</th>
                <th style="width: 10%;">Nº control</th>
                <th style="width: 5%;">Clase op.</th>
                <th style="width: 8%;">Nº control afectada</th>
                <th style="width: 10%;">Monto total factura / ND</th>
                <th style="width: 8%;">Compras sin derecho a crédito IVA</th>
                <th style="width: 9%;">Base imponible</th>
                <th style="width: 6%;">Alícuota %</th>
                <th style="width: 9%;">Impuesto causado</th>
                <th style="width: 10%;">Impuesto retenido</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($books as $book)
                <tr>
                    <td>{{ $book->operation_number }}</td>
                    <td>{{ $book->invoice_date?->format('d/m/Y') }}</td>
                    <td class="left">{{ $book->invoice_number }}</td>
                    <td class="left">{{ $book->invoice_control_number ?: '—' }}</td>
                    <td>{{ $book->operation_class }}</td>
                    <td>{{ $book->affected_control_number ?: '' }}</td>
                    <td class="num">{{ $fmt((float) $book->invoice_total_ves) }}</td>
                    <td class="num">{{ $book->purchases_without_vat_credit !== null ? $fmt((float) $book->purchases_without_vat_credit) : '' }}</td>
                    <td class="num">{{ $fmt((float) $book->taxable_base_ves) }}</td>
                    <td>{{ number_format((float) $book->vat_rate_percent, 0) }}%</td>
                    <td class="num">{{ $fmt((float) $book->tax_caused_ves) }}</td>
                    <td class="num">{{ $fmt((float) $book->tax_retained_ves) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" class="left">Totales</td>
                <td class="num">{{ $fmt($total_invoice_ves) }}</td>
                <td class="num">{{ $total_without_vat_credit > 0 ? $fmt($total_without_vat_credit) : '' }}</td>
                <td class="num">{{ $fmt($total_taxable_base_ves) }}</td>
                <td></td>
                <td class="num">{{ $fmt($total_tax_caused_ves) }}</td>
                <td class="num">{{ $fmt($total_tax_retained_ves) }}</td>
            </tr>
        </tfoot>
    </table>

    <table class="saldo-wrap">
        <tr>
            <td></td>
            <td style="width: 280px;">
                <div class="saldo-box">
                    Saldo a pagar: {{ $fmt($balance_to_pay_ves) }}
                </div>
            </td>
        </tr>
    </table>

    <div class="footer-note">
        Según lo establecido en el Artículo 11 de la Providencia Administrativa N° SNAT/2015/0049 de fecha 10/08/2015
        que establece las Normas Generales de Retención de IVA.
    </div>

    <table class="signs">
        <tr>
            <td>
                <table class="sign-block">
                    <tr>
                        <td><div class="sign-line">&nbsp;</div></td>
                    </tr>
                    <tr>
                        <td><div class="sign-label">Firma y sello del agente de retención</div></td>
                    </tr>
                    <tr>
                        <td><div class="sign-date">&nbsp;</div></td>
                    </tr>
                </table>
            </td>
            <td>
                <table class="sign-block">
                    <tr>
                        <td><div class="sign-line">&nbsp;</div></td>
                    </tr>
                    <tr>
                        <td><div class="sign-label">Firma del agente retenido</div></td>
                    </tr>
                    <tr>
                        <td><div class="sign-date">Fecha de entrega ____ / ____ / ________</div></td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
