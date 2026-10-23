@include('filament.widgets.today-summary.partials.accounts', [
    'title' => 'Cuentas por pagar',
    'pendingLabel' => 'Saldo por pagar',
    'documentSingular' => 'factura',
    'documentPlural' => 'facturas',
    'settledLabel' => 'Pagado en el período',
    'emptyOverdueLabel' => 'No hay facturas de proveedores vencidas.',
    'tone' => 'payable',
])
