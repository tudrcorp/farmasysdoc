@php
    use App\Enums\EmployeeRequestType;

    $copy = match ($type) {
        EmployeeRequestType::Vacation => ['lead' => 'Vacaciones', 'text' => 'Te sugerimos el período según tu fecha de ingreso. Puedes cambiar las fechas y pedirlas antes de tiempo; RR. HH. decide.', 'step' => 'Vacaciones', 'active' => 'request-vacaciones'],
        EmployeeRequestType::Leave => ['lead' => 'Permiso', 'text' => 'Indica los días que vas a faltar y el motivo. Si tienes un soporte, adjúntalo.', 'step' => 'Permiso', 'active' => 'request-permiso'],
        default => ['lead' => 'Reposo', 'text' => 'Carga el reposo médico con sus soportes (PDF o fotos). Puedes adjuntar varios archivos.', 'step' => 'Reposo', 'active' => 'request-reposo'],
    };
@endphp

<div class="ep-app ep-app--flow" x-data="employeePortalMenu">
    @include('employee-portal.partials.page-header', ['backHref' => route('employee-portal.requests')])

    <div class="ep-screen" wire:key="absence-request-{{ $type->value }}">
        @include('employee-portal.partials.page-nav', ['backHref' => route('employee-portal.requests'), 'step' => $copy['step']])

        <div class="ep-flow-intro">
            <h1 class="ep-lead">{{ $copy['lead'] }}</h1>
            <p class="ep-text">{{ $copy['text'] }}</p>
        </div>

        @if ($blocked)
            <div class="ep-card-btn ep-glass" style="cursor: default;">
                <span class="ep-card-copy">
                    <strong>Falta tu fecha de ingreso</strong>
                    <span>Sin ella no podemos calcular tus vacaciones. Pídele a RR. HH. que la cargue en tu ficha.</span>
                </span>
                <span class="ep-pill ep-pill--warn">Pendiente</span>
            </div>
        @else
            @if ($plan)
                <div class="ep-vacation-plan ep-glass">
                    <div>
                        <p class="ep-vacation-plan__label">Tu período sugerido</p>
                        <p class="ep-vacation-plan__month">{{ $plan['month_label'] }}</p>
                        <p class="ep-vacation-plan__meta">
                            {{ $plan['suggested_starts_on']->format('d/m/Y') }} al {{ $plan['suggested_ends_on']->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="ep-vacation-plan__days">
                        <strong>{{ $plan['entitled_days'] }}</strong>
                        <span>días hábiles</span>
                    </div>
                    <p class="ep-vacation-plan__foot">
                        @if ($plan['right_is_born'])
                            Tu derecho de este año nació el {{ $plan['right_starts_on']->format('d/m/Y') }} ({{ $plan['service_year'] }}.º año de servicio).
                        @else
                            Tu derecho nace el {{ $plan['right_starts_on']->format('d/m/Y') }}, al cumplir {{ $plan['service_year'] }} {{ $plan['service_year'] === 1 ? 'año' : 'años' }} en la empresa.
                        @endif
                        Los días hábiles se cuentan de lunes a viernes.
                    </p>
                </div>
            @endif

            <form class="ep-form" wire:submit="submit">
                <div class="ep-field-group">
                    <p class="ep-field-label">{{ $type === EmployeeRequestType::Vacation ? 'Fechas de tus vacaciones' : 'Desde y hasta' }}</p>
                    @include('employee-portal.partials.date-range-picker', [
                        'config' => $pickerConfig,
                        'presets' => $presets,
                        'label' => $type === EmployeeRequestType::Vacation ? 'Vacaciones' : 'Período',
                    ])
                </div>
                @error('startsOn') <p class="ep-error">{{ $message }}</p> @enderror
                @error('endsOn') <p class="ep-error">{{ $message }}</p> @enderror

                @if ($type !== EmployeeRequestType::Vacation)
                    <label class="ep-field">
                        <span>{{ $type === EmployeeRequestType::Leave ? 'Motivo' : 'Comentario (opcional)' }}</span>
                        <textarea rows="3" maxlength="1000" wire:model="reason" placeholder="{{ $type === EmployeeRequestType::Leave ? 'Cuéntanos brevemente el motivo' : 'Diagnóstico o indicaciones, si quieres' }}"></textarea>
                    </label>
                    @error('reason') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-field-group">
                        <p class="ep-field-label">{{ $type === EmployeeRequestType::MedicalRest ? 'Soportes del reposo' : 'Soporte (opcional)' }}</p>
                        <label class="ep-drop ep-glass">
                            <input
                                type="file"
                                multiple
                                accept=".pdf,image/jpeg,image/png,image/webp"
                                wire:model="newFiles"
                            >
                            <span wire:loading.remove wire:target="newFiles">
                                <strong>Toca para adjuntar</strong>
                                PDF o fotos, hasta 5 MB cada uno y máximo {{ \App\Services\Hr\EmployeeRequestSubmitter::MAX_ATTACHMENTS }} archivos.
                            </span>
                            <span wire:loading wire:target="newFiles"><strong>Subiendo…</strong></span>
                        </label>

                        @if ($files !== [])
                            <ul class="ep-file-list">
                                @foreach ($files as $index => $file)
                                    <li wire:key="file-{{ $index }}-{{ $file->getFilename() }}">
                                        <span>{{ \Illuminate\Support\Str::limit($file->getClientOriginalName(), 34) }}</span>
                                        <button type="button" wire:click="removeFile({{ $index }})" aria-label="Quitar archivo">Quitar</button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    @error('files') <p class="ep-error">{{ $message }}</p> @enderror
                    @error('files.*') <p class="ep-error">{{ $message }}</p> @enderror
                    @error('newFiles.*') <p class="ep-error">{{ $message }}</p> @enderror
                @endif

                <div class="ep-actions">
                    <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled" wire:target="submit,newFiles">
                        <span wire:loading.remove wire:target="submit">Enviar solicitud</span>
                        <span wire:loading wire:target="submit">Enviando…</span>
                    </button>
                </div>
            </form>
        @endif
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => $copy['active']])
</div>
