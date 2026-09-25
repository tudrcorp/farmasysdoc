<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libro de Compras {{ $month_label }} {{ $year }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 6mm 5mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 6.2pt;
            color: #111;
            margin: 0;
        }
        table { border-collapse: collapse; width: 100%; }
        .meta { width: 100%; margin-bottom: 4px; }
        .meta td { vertical-align: middle; padding: 1px 3px; }
        .company {
            font-size: 8pt;
            font-weight: 700;
            text-transform: uppercase;
        }
        .rif {
            text-align: right;
            font-size: 8pt;
            font-weight: 700;
        }
        .title-line {
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
        }
        .month-box {
            border: 1px solid #111;
            text-align: center;
            font-weight: 700;
            padding: 2px 8px;
            font-size: 8pt;
        }
        .book {
            width: 100%;
        }
        .book th,
        .book td {
            border: 1px solid #111;
            padding: 2px 3px;
            vertical-align: top;
        }
        .book th {
            background: #f3f3f3;
            font-size: 5.4pt;
            font-weight: 700;
            text-align: center;
            line-height: 1.15;
        }
        .num { text-align: right; white-space: nowrap; }
        .ctr { text-align: center; white-space: nowrap; }
        .retention-row td { background: #fff8eb; }
        .totals {
            width: 100%;
            margin-top: 8px;
        }
        .totals td {
            border: 1px solid #111;
            padding: 3px 5px;
            font-size: 6.4pt;
        }
        .totals .lbl { font-weight: 700; }
        .totals .num { text-align: right; font-weight: 700; }
        .foot {
            margin-top: 6px;
            font-size: 5.5pt;
            color: #444;
        }
    </style>
</head>
<body>
    <table class="meta">
        <tr>
            <td class="company">NOMBRE DE LA PERSONA NATURAL Y/O RAZÓN SOCIAL: {{ $company_name }}</td>
            <td class="rif">RIF {{ $company_rif }}</td>
        </tr>
        <tr>
            <td class="title-line">LIBRO DE COMPRAS CORRESPONDIENTES AL MES {{ $period_title }}</td>
            <td style="text-align: right;">
                <span class="month-box">{{ $month_label }}</span>
                &nbsp; AÑO: <strong>{{ $year }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="font-size: 5.8pt;">DETALLES FACTURAS, TICKETS, DOCUMENTOS EQUIVALENTES — TOTAL COMPRAS — TASA G — COMPRAS PROPIAS (CONTRIBUYENTES)</td>
        </tr>
    </table>

    <table class="book">
        <thead>
            <tr>
                <th style="width: 3%;">Nº op.</th>
                <th style="width: 5.5%;">Emisión facturas, ND y NC</th>
                <th style="width: 8%;">Tipo de documento</th>
                <th style="width: 7%;">Factura o documento equivalente</th>
                <th style="width: 6%;">Nº de control</th>
                <th style="width: 3%;">Serie</th>
                <th style="width: 12%;">Nombre / razón social</th>
                <th style="width: 7%;">RIF / cédula</th>
                <th style="width: 4%;">Tipo contrib.</th>
                <th style="width: 6.5%;">Total gravadas incl. IVA y exentas</th>
                <th style="width: 5.5%;">Exentas / exoneradas</th>
                <th style="width: 4.5%;">Monto exportación</th>
                <th style="width: 5.5%;">Base imponible</th>
                <th style="width: 4.5%;">IVA</th>
                <th style="width: 4.5%;">Base reducida</th>
                <th style="width: 4%;">IVA reducida</th>
                <th style="width: 3.5%;">Alícuota</th>
                <th style="width: 5.5%;">Fecha comprobante retención</th>
                <th style="width: 6%;">Nº comprobante retención</th>
                <th style="width: 5.5%;">Monto retención</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr @class(['retention-row' => $row['is_retention']])>
                    <td class="ctr">{{ $row['operation_number'] }}</td>
                    <td class="ctr">{{ $row['invoice_date'] }}</td>
                    <td>{{ $row['document_type'] }}</td>
                    <td>{{ $row['document_number'] }}</td>
                    <td class="ctr">{{ $row['control_number'] }}</td>
                    <td class="ctr">{{ $row['serie'] }}</td>
                    <td>{{ $row['supplier_name'] }}</td>
                    <td class="ctr">{{ $row['supplier_rif'] }}</td>
                    <td class="ctr">{{ $row['taxpayer_type'] !== '' ? $row['taxpayer_type'] : '—' }}</td>
                    <td class="num">{{ $row['is_retention'] ? '—' : number_format((float) $row['total_with_vat'], 2, ',', '.') }}</td>
                    <td class="num">{{ $row['is_retention'] ? '0,00' : ($row['exempt'] === null ? '—' : number_format((float) $row['exempt'], 2, ',', '.')) }}</td>
                    <td class="num">{{ $row['is_retention'] || $row['export'] === null ? '—' : number_format((float) $row['export'], 2, ',', '.') }}</td>
                    <td class="num">{{ $row['is_retention'] ? '0,00' : number_format((float) $row['taxable_base'], 2, ',', '.') }}</td>
                    <td class="num">{{ $row['is_retention'] ? '—' : number_format((float) $row['tax_caused'], 2, ',', '.') }}</td>
                    <td class="num">{{ $row['is_retention'] || $row['taxable_base_reduced'] === null ? '—' : number_format((float) $row['taxable_base_reduced'], 2, ',', '.') }}</td>
                    <td class="num">{{ $row['is_retention'] || $row['tax_reduced'] === null ? '—' : number_format((float) $row['tax_reduced'], 2, ',', '.') }}</td>
                    <td class="ctr">{{ $row['vat_rate_percent'] !== null ? number_format((float) $row['vat_rate_percent'], 0, ',', '.').'%' : '—' }}</td>
                    <td class="ctr">{{ $row['retention_issued_at'] ?: '—' }}</td>
                    <td class="ctr">{{ $row['retention_voucher_number'] ?: '—' }}</td>
                    <td class="num">{{ $row['is_retention'] ? number_format((float) $row['retention_amount'], 2, ',', '.') : '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="20" class="ctr">No hay registros en este periodo.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="lbl">Total Compras no Gravadas o/y sin derecho a Crédito</td>
            <td>Item 40</td>
            <td class="num">{{ number_format($totals['exempt'], 2, ',', '.') }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="lbl">Total Compras Internas gravadas por Alícuota General 16%</td>
            <td>Item 42</td>
            <td class="num">{{ number_format($totals['taxable_base_16'], 2, ',', '.') }}</td>
            <td>Item 43</td>
            <td class="num">{{ number_format($totals['tax_caused_16'], 2, ',', '.') }}</td>
            <td>Item 66</td>
            <td class="num">{{ number_format($totals['retention'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Total Importaciones Gravadas por Alícuota Reducida</td>
            <td>Item 333</td>
            <td class="num">0,00</td>
            <td>Item 43</td>
            <td class="num">0,00</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="lbl">Total Compras Internas gravadas por Alícuota General 8%</td>
            <td>Item 42</td>
            <td class="num">0,00</td>
            <td>Item 43</td>
            <td class="num">0,00</td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="lbl">Total Compras Internas gravadas por Alícuota General 9%</td>
            <td>Item 442</td>
            <td></td>
            <td>Item 452</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="lbl">Total Compras Internas gravadas por Alícuota General más Adicional</td>
            <td>Item 443</td>
            <td></td>
            <td>Item 453</td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <tr>
            <td class="lbl">Total Compras y Créditos Fiscales para efectos de determinación:</td>
            <td>Item 46</td>
            <td class="num">{{ number_format($totals['total_with_vat'], 2, ',', '.') }}</td>
            <td></td>
            <td class="num">{{ number_format($totals['tax_caused_16'], 2, ',', '.') }}</td>
            <td></td>
            <td class="num">{{ number_format($totals['retention'], 2, ',', '.') }}</td>
        </tr>
    </table>

    <div class="foot">Generado el {{ $generated_at }} por {{ $generated_by }}.</div>
</body>
</html>
