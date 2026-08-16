<div class="ep-app ep-app--flow">
    <div class="ep-screen ep-auth" wire:key="login-{{ $recoveryStep ?: ($needsPassword ? 'password' : 'id') }}">
        <div class="ep-brand">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal</span>
        </div>

        <div class="ep-auth-body">
            @if ($recoveryStep === 'channel')
                <button type="button" class="ep-ghost" wire:click="backToPasswordStep" style="align-self: flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Atrás
                </button>
                <h1 class="ep-lead">Olvidé mi clave</h1>
                <p class="ep-text">Te enviaremos un código de 6 dígitos. Elige el teléfono o el correo que ya tienes en tu expediente.</p>
                @error('otpChannel') <p class="ep-error">{{ $message }}</p> @enderror

                <div class="ep-stack">
                    <button
                        type="button"
                        class="ep-card-btn ep-glass"
                        wire:click="sendRecoveryOtpTo('phone')"
                        wire:loading.attr="disabled"
                        @disabled(! $canSendPhone)
                    >
                        <span class="ep-card-copy">
                            <strong>Teléfono</strong>
                            <span>{{ $canSendPhone ? $recoveryEmployee?->maskedPortalPhone() : 'No hay un teléfono registrado.' }}</span>
                        </span>
                    </button>
                    <button
                        type="button"
                        class="ep-card-btn ep-glass"
                        wire:click="sendRecoveryOtpTo('email')"
                        wire:loading.attr="disabled"
                        @disabled(! $canSendEmail)
                    >
                        <span class="ep-card-copy">
                            <strong>Correo electrónico</strong>
                            <span>{{ $canSendEmail ? $recoveryEmployee?->maskedPortalEmail() : 'No hay un correo registrado.' }}</span>
                        </span>
                    </button>
                </div>
                @if (! $canSendPhone && ! $canSendEmail)
                    <p class="ep-text">No hay teléfono ni correo en tu expediente. Comunícate con Recursos Humanos.</p>
                @endif
            @elseif ($recoveryStep === 'otp')
                <button type="button" class="ep-ghost" wire:click="backToRecoveryChannel" style="align-self: flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Atrás
                </button>
                <h1 class="ep-lead">Escribe el código</h1>
                <p class="ep-text">
                    Enviamos un código de 6 dígitos
                    @if ($otpChannel === 'email')
                        a {{ $recoveryEmployee?->maskedPortalEmail() }}.
                    @else
                        a {{ $recoveryEmployee?->maskedPortalPhone() }}.
                    @endif
                    Caduca en 10 minutos.
                </p>

                <form class="ep-form" wire:submit="verifyRecoveryOtp">
                    <label class="ep-field">
                        <span>Código OTP</span>
                        <input
                            class="ep-otp-input"
                            type="text"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="6"
                            wire:model="otpCode"
                            placeholder="000000"
                        >
                    </label>
                    @error('otpCode') <p class="ep-error">{{ $message }}</p> @enderror
                    @error('otpChannel') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions">
                        <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled" wire:target="verifyRecoveryOtp">
                            <span wire:loading.remove wire:target="verifyRecoveryOtp">Validar código</span>
                            <span wire:loading wire:target="verifyRecoveryOtp">Validando…</span>
                        </button>
                        <button type="button" class="ep-btn ep-btn--secondary" wire:click="sendRecoveryOtp" wire:loading.attr="disabled" wire:target="sendRecoveryOtp">
                            Reenviar código
                        </button>
                    </div>
                </form>
            @elseif ($recoveryStep === 'password')
                <button type="button" class="ep-ghost" wire:click="backToRecoveryOtp" style="align-self: flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Atrás
                </button>
                <h1 class="ep-lead">Nueva clave</h1>
                <p class="ep-text">El código es válido. Escribe la clave que usarás para entrar al portal.</p>

                <form class="ep-form" wire:submit="saveRecoveredPassword">
                    <label class="ep-field">
                        <span>Nueva clave</span>
                        <input type="password" autocomplete="new-password" wire:model="newPassword" placeholder="Mínimo 4 caracteres">
                    </label>
                    @error('newPassword') <p class="ep-error">{{ $message }}</p> @enderror
                    @error('password') <p class="ep-error">{{ $message }}</p> @enderror

                    <label class="ep-field">
                        <span>Repite la clave</span>
                        <input type="password" autocomplete="new-password" wire:model="newPasswordConfirmation">
                    </label>
                    @error('newPasswordConfirmation') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions">
                        <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Guardar clave</span>
                            <span wire:loading>Guardando…</span>
                        </button>
                    </div>
                </form>
            @elseif ($recoveryStep === 'done')
                <div class="ep-success" style="padding: 0;">
                    <div>
                        <div class="ep-check" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="size-10">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <h1 class="ep-lead">Clave restaurada</h1>
                        <p class="ep-text">Tu clave se restauró con éxito. Ya puedes entrar al portal con ella.</p>
                        <div class="ep-actions">
                            <button type="button" class="ep-btn ep-btn--primary" wire:click="backToPasswordStep">Entrar con mi nueva clave</button>
                        </div>
                    </div>
                </div>
            @elseif (! $needsPassword)
                <h1 class="ep-lead">Hola, entra a tu portal</h1>
                <p class="ep-text">Usa tu cédula o tu número de teléfono. Si ya creaste una clave, te la pediremos en el siguiente paso.</p>

                <form class="ep-form" wire:submit="continue">
                    <label class="ep-field">
                        <span>Cédula o teléfono</span>
                        <input
                            type="text"
                            inputmode="tel"
                            autocomplete="username"
                            wire:model="identifier"
                            placeholder="V-12345678 o 0412…"
                        >
                    </label>
                    @error('identifier') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions">
                        <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Continuar</span>
                            <span wire:loading>Revisando…</span>
                        </button>
                    </div>
                </form>
            @else
                <button type="button" class="ep-ghost" wire:click="backToIdentifier" style="align-self: flex-start;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Atrás
                </button>
                <h1 class="ep-lead">Escribe tu clave</h1>
                <p class="ep-text">Esta cuenta está protegida. Entra con la clave que configuraste.</p>

                <form class="ep-form" wire:submit="authenticate">
                    <label class="ep-field">
                        <span>Clave</span>
                        <input
                            type="password"
                            autocomplete="current-password"
                            wire:model="password"
                            placeholder="Tu clave"
                        >
                    </label>
                    @error('password') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions">
                        <button type="submit" class="ep-btn ep-btn--primary" wire:loading.attr="disabled">
                            <span wire:loading.remove>Entrar</span>
                            <span wire:loading>Entrando…</span>
                        </button>
                        <button type="button" class="ep-ghost" wire:click="startPasswordRecovery">
                            ¿Olvidé mi clave?
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
