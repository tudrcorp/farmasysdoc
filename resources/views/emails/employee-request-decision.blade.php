@php
    $approved = $employeeRequest->status === \App\Enums\EmployeeRequestStatus::Approved;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $headline }}</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; line-height: 1.5; color: #111827; margin: 0; padding: 24px; background: #f9fafb;">
    <div style="max-width: 520px; margin: 0 auto; background: #ffffff; border: 1px solid #e5e7eb; border-radius: 12px; overflow: hidden;">
        <div style="background: #0f172a; padding: 20px 24px; text-align: center;">
            <img
                src="{{ asset('images/logos/farmadoc-ligth.png') }}"
                alt="Farmadoc"
                width="160"
                style="max-width: 160px; height: auto; display: inline-block;"
            >
        </div>
        <div style="padding: 24px;">
            <p style="display: inline-block; margin: 0 0 12px; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; background: {{ $approved ? '#d1fae5' : '#fee2e2' }}; color: {{ $approved ? '#065f46' : '#991b1b' }};">
                {{ $employeeRequest->status->label() }}
            </p>
            <h1 style="font-size: 18px; margin: 0 0 8px;">{{ $headline }}</h1>
            <p style="margin: 0 0 16px; color: #4b5563;">Hola, {{ $employeeRequest->employee?->first_name }}.</p>

            <p style="margin: 0 0 8px;"><strong>Solicitud:</strong> {{ $employeeRequest->type->label() }}</p>
            @if ($employeeRequest->periodLabel())
                <p style="margin: 0 0 8px;"><strong>Fechas:</strong> {{ $employeeRequest->periodLabel() }}</p>
            @endif
            @if ($employeeRequest->salary_basis)
                <p style="margin: 0 0 8px;"><strong>Tipo de constancia:</strong> {{ $employeeRequest->salary_basis->label() }}</p>
            @endif
            @if (filled($employeeRequest->admin_note))
                <p style="margin: 0 0 8px;"><strong>Nota de RR. HH.:</strong> {{ $employeeRequest->admin_note }}</p>
            @endif

            @if ($nextStep)
                <p style="margin: 16px 0 0;">{{ $nextStep }}</p>
            @endif

            <p style="margin: 20px 0 0; text-align: center;">
                <a href="{{ $portalUrl }}" style="display: inline-block; background: #0e7490; color: #ffffff; text-decoration: none; font-weight: 700; padding: 10px 20px; border-radius: 10px;">
                    Ver en el portal
                </a>
            </p>
        </div>
    </div>
</body>
</html>
