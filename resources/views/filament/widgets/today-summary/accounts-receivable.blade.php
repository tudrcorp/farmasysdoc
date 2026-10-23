@include('filament.widgets.today-summary.partials.accounts', [
    'title' => 'Cuentas por cobrar',
    'pendingLabel' => 'Saldo por cobrar',
    'documentSingular' => 'cuenta',
    'documentPlural' => 'cuentas',
    'settledLabel' => 'Cobrado en el período',
    'emptyOverdueLabel' => 'No hay cuentas por cobrar vencidas.',
    'tone' => 'receivable',
])
