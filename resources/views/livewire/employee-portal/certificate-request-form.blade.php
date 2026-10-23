<div class="ep-app ep-app--flow" x-data="employeePortalMenu">
    @include('employee-portal.partials.page-header', ['backHref' => route('employee-portal.requests')])

    <div class="ep-screen" wire:key="certificate-request">
        @include('employee-portal.partials.page-nav', ['backHref' => route('employee-portal.requests'), 'step' => 'Constancia'])

        <div class="ep-flow-intro">
            <h1 class="ep-lead">Constancia de trabajo</h1>
            <p class="ep-text">Elige qué sueldo debe aparecer. RR. HH. la aprueba y la descargas desde "Mis solicitudes".</p>
        </div>

        @if (! $hasEmploymentData)
            <div class="ep-card-btn ep-glass" style="cursor: default;">
                <span class="ep-card-copy">
                    <strong>Faltan datos en tu ficha</strong>
                    <span>Aún no tenemos tu fecha de ingreso o tu cargo. Pídele a RR. HH. que los cargue para poder emitir tu constancia.</span>
                </span>
                <span class="ep-pill ep-pill--warn">Pendiente</span>
            </div>
        @else
            <dl class="ep-info-list ep-glass">
                <div class="ep-info-row"><dt>Cargo</dt><dd>{{ $employee->job_title }}</dd></div>
                <div class="ep-info-row"><dt>Fecha de ingreso</dt><dd>{{ $employee->hired_at->format('d/m/Y') }}</dd></div>
                <div class="ep-info-row"><dt>Tiempo en la empresa</dt><dd>{{ $employee->serviceDurationLabel() }}</dd></div>
                <div class="ep-info-row"><dt>Sucursal</dt><dd>{{ $employee->branch?->name ?? '—' }}</dd></div>
            </dl>

            @if ($hasPending)
                <p class="ep-toast ep-toast--warn">Ya tienes una constancia por aprobar. Te avisamos apenas esté lista.</p>
            @endif

            <form class="ep-form" wire:submit="submit">
                <p class="ep-section-label">Sueldo en la constancia</p>
                <div class="ep-choice-grid" role="radiogroup" aria-label="Sueldo en la constancia">
                    @foreach ($options as $option)
                        <label @class(['ep-choice', 'ep-glass', 'is-disabled' => ! $option['available']])>
                            <input type="radio" name="salaryBasis" value="{{ $option['value'] }}" wire:model="salaryBasis" @disabled(! $option['available'])>
                            <span class="ep-choice__body">
                                <strong>{{ $option['label'] }}</strong>
                                <span>{{ $option['available'] ? $option['description'] : 'No tienes este sueldo cargado.' }}</span>
                                <em>{{ $option['amount'] }} mensual</em>
                            </span>
                            <span class="ep-choice__check" aria-hidden="true"></span>
                        </label>
                    @endforeach
                </div>
                @error('salaryBasis') <p class="ep-error">{{ $message }}</p> @enderror

                <div class="ep-actions">
                    <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled" wire:target="submit" @disabled($hasPending)>
                        <span wire:loading.remove wire:target="submit">Solicitar constancia</span>
                        <span wire:loading wire:target="submit">Enviando…</span>
                    </button>
                </div>
            </form>
        @endif
    </div>

    @include('employee-portal.partials.menu-sheet', ['active' => 'request-certificate'])
</div>
