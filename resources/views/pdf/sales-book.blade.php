@php
    use App\Support\Fiscal\SalesBookLayout;
    $lastPage = count($pages) - 1;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Libro de Ventas {{ $header['month_name'] }} {{ $header['year'] }} — {{ $header['branch_name'] }}</title>
    <style>
        * { box-sizing: border-box; }
        @page { margin: 6mm 5mm 9mm 5mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 5.6pt; color: #111; margin: 0; }
        table { border-collapse: collapse; width: 100%; }
        .meta td { padding: 1px 3px; vertical-align: middle; }
        .company { font-size: 7.5pt; font-weight: 700; text-transform: uppercase; }
        .rif { text-align: right; font-size: 7.5pt; font-weight: 700; white-space: nowrap; }
        .title-line { font-size: 7pt; font-weight: 700; text-transform: uppercase; }
        .status { font-size: 6.5pt; font-style: italic; color: #7f1d1d; }
        .book th, .book td { border: 0.6px solid #111; padding: 1.5px 2px; vertical-align: middle; }
        .book th { background: #f3f3f3; font-size: 4.8pt; font-weight: 700; text-align: center; line-height: 1.1; }
        .book td { height: 13px; white-space: nowrap; overflow: hidden; }
        .num { text-align: right; }
        .ctr { text-align: center; }
        .name { max-width: 130px; overflow: hidden; }
        .carry td { font-weight: 700; background: #f7f7f7; }
        .total td { font-weight: 700; background: #e9e9e9; }
        .page-break { page-break-after: always; }
        .summary { width: 70%; margin-top: 10px; }
        .summary th, .summary td { border: 0.6px solid #111; padding: 2px 4px; font-size: 6.5pt; }
        .summary th { background: #f3f3f3; }
        .draft { position: fixed; top: 38%; left: 18%; font-size: 70pt; color: rgba(185, 28, 28, 0.12); transform: rotate(-18deg); font-weight: 700; }
        .footer { position: fixed; bottom: -6mm; left: 0; right: 0; font-size: 5.5pt; color: #444; }
    </style>
</head>
<body>
@if ($isDraft)
    <div class="draft">BORRADOR</div>
@endif
<div class="footer">{{ $statusLine }} · Generado {{ $book['generated_at'] }} por {{ $book['generated_by'] }}</div>

@foreach ($pages as $pageIndex => $page)
    <table class="meta">
        <tr>
            <td class="company">Nombre o razón social: {{ $header['company_name'] }} &nbsp; SUC: {{ $header['branch_name'] }} — {{ $header['branch_address'] }}</td>
            <td class="rif">RIF: {{ $header['company_rif'] }}</td>
        </tr>
        <tr>
            <td class="title-line">Libro de ventas correspondiente al mes de: {{ $header['month_name'] }} &nbsp; año: {{ $header['year'] }} &nbsp; ({{ $header['half_label'] }}) &nbsp; · &nbsp; {{ $header['range_label'] }}</td>
            <td class="rif">Página {{ $pageIndex + 1 }} de {{ count($pages) }}</td>
        </tr>
        <tr><td class="status" colspan="2">{{ $statusLine }}</td></tr>
    </table>

    <table class="book">
        <thead>
            <tr>
                @php $col = 0; @endphp
                @foreach ($groups as [$from, $to, $text])
                    @if ($from > $col)
                        <th colspan="{{ $from - $col }}" style="background:#fff;border:none;"></th>
                    @endif
                    <th colspan="{{ $to - $from + 1 }}">{{ $text }}</th>
                    @php $col = $to + 1; @endphp
                @endforeach
                @if ($col < count($columns))
                    <th colspan="{{ count($columns) - $col }}" style="background:#fff;border:none;"></th>
                @endif
            </tr>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @if ($pageIndex > 0)
                <tr class="carry">
                    @foreach ($columns as $index => $column)
                        @if ($column['money'] ?? false)
                            <td class="num">{{ SalesBookLayout::money($page['brought_forward'][$column['key']]) }}</td>
                        @else
                            <td class="{{ $index === 6 ? '' : 'ctr' }}">{{ $index === 6 ? 'VIENEN…' : '' }}</td>
                        @endif
                    @endforeach
                </tr>
            @endif

            @foreach ($page['rows'] as $row)
                <tr>
                    @foreach ($columns as $column)
                        @php $value = SalesBookLayout::value($row, $column['key'], $header); @endphp
                        @if ($column['money'] ?? false)
                            <td class="num">{{ SalesBookLayout::money($value) }}</td>
                        @elseif ($column['key'] === 'customer_name')
                            <td class="name">{{ \Illuminate\Support\Str::limit((string) $value, 42, '…') }}</td>
                        @else
                            <td class="ctr">{{ $value }}</td>
                        @endif
                    @endforeach
                </tr>
            @endforeach

            <tr class="{{ $pageIndex === $lastPage ? 'total' : 'carry' }}">
                @foreach ($columns as $index => $column)
                    @if ($column['money'] ?? false)
                        <td class="num">{{ SalesBookLayout::money($page['carried_forward'][$column['key']]) }}</td>
                    @else
                        <td>{{ $index === 6 ? ($pageIndex === $lastPage ? 'TOTALES (BOLÍVARES)' : 'VAN…') : '' }}</td>
                    @endif
                @endforeach
            </tr>
        </tbody>
    </table>

    @if ($pageIndex === $lastPage)
        <table class="summary">
            <tr>
                <th colspan="7" style="text-align:left;">Resumen de ventas y débitos fiscales — {{ $header['company_name'] }} · RIF {{ $header['company_rif'] }} · {{ $header['month_name'] }} {{ $header['year'] }} ({{ $header['half_label'] }})</th>
            </tr>
            <tr>
                <th style="text-align:left;">Débitos fiscales</th>
                <th>Ítem</th><th>Base imponible</th>
                <th>Ítem</th><th>Débito fiscal</th>
                <th>Ítem</th><th>IVA retenido por el comprador</th>
            </tr>
            @foreach (SalesBookLayout::summaryLines() as [$label, $baseItem, $baseKey, $taxItem, $taxKey, $retItem, $retKey])
                <tr>
                    <td>{{ $label }}</td>
                    <td class="ctr">{{ $baseItem }}</td>
                    <td class="num">{{ $baseKey !== null ? SalesBookLayout::money($book['summary'][$baseKey]) : '' }}</td>
                    <td class="ctr">{{ $taxItem }}</td>
                    <td class="num">{{ $taxKey !== null ? SalesBookLayout::money($book['summary'][$taxKey]) : '' }}</td>
                    <td class="ctr">{{ $retItem }}</td>
                    <td class="num">{{ $retKey !== null ? SalesBookLayout::money($book['summary'][$retKey]) : '' }}</td>
                </tr>
            @endforeach
        </table>
    @else
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
