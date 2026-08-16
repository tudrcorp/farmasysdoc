<div class="ep-app ep-app--flow" x-data="employeePortalMenu">
    <header class="ep-topbar ep-glass ep-desktop-only">
        <div class="ep-brand ep-brand--bar">
            <img src="{{ asset('images/logos/favicon.png') }}" alt="Farmadoc">
            <span>Portal del empleado</span>
        </div>
        <div class="ep-topbar-actions">
            @include('employee-portal.partials.menu-button')
            <a href="{{ route('employee-portal.home') }}" class="ep-btn ep-btn--secondary ep-btn--compact" wire:navigate>Volver</a>
        </div>
    </header>

    @if ($step === 'view')
        <div class="ep-screen" wire:key="file-view">
            <div class="ep-nav">
                <a href="{{ route('employee-portal.home') }}" class="ep-ghost" wire:navigate>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Inicio
                </a>
                <p class="ep-nav-title">Expediente</p>
                <div class="ep-header-actions">
                    @include('employee-portal.partials.menu-button')
                </div>
            </div>
            <h1 class="ep-lead">Tu firma y tu huella</h1>
            <p class="ep-text">Estas imágenes ya están en tu expediente y se usan en tus recibos.</p>
            <div class="ep-review-grid">
                <div class="ep-review-card ep-glass">
                    <p>Firma</p>
                    @if ($signaturePreviewUrl)
                        <img src="{{ $signaturePreviewUrl }}" alt="Firma">
                    @endif
                </div>
                <div class="ep-review-card ep-glass">
                    <p>Huella</p>
                    @if ($fingerprintPreviewUrl)
                        <img src="{{ $fingerprintPreviewUrl }}" alt="Huella">
                    @endif
                </div>
            </div>
            <div class="ep-actions ep-actions--row">
                <button type="button" class="ep-btn ep-btn--secondary" wire:click="requestFileChange">
                    Cambiar firma o huella
                </button>
            </div>
        </div>

        @if ($showChangeNotice)
            <div class="ep-sheet-backdrop" wire:click="closeChangeNotice" wire:key="file-change-backdrop"></div>
            <div class="ep-sheet ep-glass" wire:key="file-change-sheet" role="dialog" aria-modal="true" aria-labelledby="ep-file-change-title">
                <div class="ep-sheet-handle"></div>
                <h2 id="ep-file-change-title" class="ep-lead" style="font-size: 1.45rem;">Cambio de expediente</h2>
                <p class="ep-text">Para cambiar tu firma o tu huella debes comunicarte con Recursos Humanos y explicar el motivo del cambio. Ellos se encargan de actualizar tu expediente.</p>
                <div class="ep-actions">
                    <button type="button" class="ep-btn ep-btn--primary" wire:click="closeChangeNotice">Entendido</button>
                </div>
            </div>
        @endif
    @elseif ($step === 'intro')
        <div class="ep-screen" wire:key="intro">
            <div class="ep-nav">
                <a href="{{ route('employee-portal.home') }}" class="ep-ghost" wire:navigate>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Inicio
                </a>
                <p class="ep-step">Paso 1 de 4</p>
                <div class="ep-header-actions">
                    @include('employee-portal.partials.menu-button')
                </div>
            </div>
            <div class="ep-flow-intro">
                <h1 class="ep-lead">Términos y condiciones</h1>
                <p class="ep-text">Lee y acepta los términos. Sin esa autorización no se puede registrar tu firma ni tu huella.</p>

                <section class="ep-terms ep-glass" aria-labelledby="ep-terms-title">
                    <h2 id="ep-terms-title" class="ep-terms-title">{{ $fileTermsTitle }}</h2>
                    <div class="ep-terms-scroll" tabindex="0">
                        @foreach ($fileTermsParagraphs as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                    </div>
                    <label class="ep-consent">
                        <input type="checkbox" wire:model.live="acceptedFileTerms">
                        <span>{{ $fileTermsAcceptanceLabel }}</span>
                    </label>
                    @if ($employee->hasAcceptedFileTerms())
                        <p class="ep-terms-note">Aceptado el {{ $employee->file_terms_accepted_at?->format('d/m/Y H:i') }}.</p>
                    @endif
                    @error('acceptedFileTerms') <p class="ep-error">{{ $message }}</p> @enderror
                </section>

                <div class="ep-actions ep-actions--row">
                    <button
                        type="button"
                        class="ep-btn ep-btn--primary"
                        wire:click="startEnrollment"
                        @disabled(! $acceptedFileTerms)
                    >
                        Aceptar y continuar
                    </button>
                </div>
            </div>
        </div>
    @elseif ($step === 'signature')
        <div class="ep-screen" wire:key="signature" x-data="employeeSignaturePad">
            <div class="ep-nav">
                <button type="button" class="ep-ghost" wire:click="goTo('intro')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Atrás
                </button>
                <div>
                    <p class="ep-nav-title">Firma</p>
                    <p class="ep-step">Paso 2 de 4</p>
                </div>
            </div>
            <p class="ep-text ep-desktop-only">Firma con el mouse o el trackpad. Si ya la tienes escaneada, súbela a la derecha.</p>
            <p class="ep-text ep-mobile-only">Firma con el dedo dentro del recuadro. Si no te gusta, bórrala y vuelve a intentar.</p>

            <div class="ep-workbench">
                <div>
                    <div class="ep-pad ep-glass" wire:ignore>
                        <canvas
                            x-ref="pad"
                            @pointerdown.prevent="start($event)"
                            @pointermove.prevent="move($event)"
                            @pointerup.prevent="end()"
                            @pointerleave.prevent="end()"
                        ></canvas>
                        <p class="ep-pad-hint" x-show="!hasInk">Firme aquí</p>
                        <div class="ep-pad-clear">
                            <button type="button" class="ep-icon-btn" @click="clear()" aria-label="Borrar firma">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    @error('image') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions ep-actions--row">
                        <button
                            type="button"
                            class="ep-btn ep-btn--primary"
                            :disabled="!hasInk"
                            @click="submit()"
                            wire:loading.attr="disabled"
                            wire:target="saveSignatureStroke"
                        >
                            <span wire:loading.remove wire:target="saveSignatureStroke">Continuar</span>
                            <span wire:loading wire:target="saveSignatureStroke">Guardando…</span>
                        </button>
                        @if ($signaturePreviewUrl)
                            <button type="button" class="ep-btn ep-btn--secondary" wire:click="keepExistingSignature">
                                Usar la firma que ya tengo
                            </button>
                        @endif
                    </div>
                </div>

                <aside class="ep-upload-panel ep-glass">
                    <p class="ep-upload-title">Cargar imagen</p>
                    <p class="ep-upload-hint">PNG, JPG o WEBP. En el computador esta es la forma más rápida.</p>
                    <label class="ep-drop">
                        <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="signatureUpload">
                        <span>Elegir archivo</span>
                    </label>
                    @error('signatureUpload') <p class="ep-error">{{ $message }}</p> @enderror
                    @if ($signatureUpload)
                        <img src="{{ $signatureUpload->temporaryUrl() }}" alt="Vista previa de la firma" class="ep-preview">
                        <button type="button" class="ep-btn ep-btn--secondary" wire:click="saveSignatureUpload" wire:loading.attr="disabled">
                            Usar esta imagen
                        </button>
                    @endif
                </aside>
            </div>
        </div>
    @elseif ($step === 'fingerprint')
        <div class="ep-screen" wire:key="fingerprint" x-data="employeeFingerprintCamera">
            <div class="ep-nav">
                <button type="button" class="ep-ghost" wire:click="goTo('signature')">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.2" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                    </svg>
                    Atrás
                </button>
                <div>
                    <p class="ep-nav-title">Huella</p>
                    <p class="ep-step">Paso 3 de 4</p>
                </div>
            </div>
            <p class="ep-text ep-desktop-only">En el computador lo más simple es subir una foto o un escaneo. También puedes usar la cámara web.</p>
            <p class="ep-text ep-mobile-only">Acerca tu pulgar a la cámara hasta que llene el óvalo.</p>

            <div class="ep-workbench">
                <div>
                    <div class="ep-viewfinder ep-glass" wire:ignore>
                        <video x-ref="video" x-show="!preview" autoplay playsinline muted></video>
                        <img x-show="preview" :src="preview" alt="Vista previa de la huella">
                        <div class="ep-oval" x-show="!preview"></div>
                        <div class="ep-view-actions">
                            <button type="button" class="ep-icon-btn" x-show="!preview" @click="flip()" aria-label="Cambiar cámara">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182" />
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="ep-error" x-show="error" x-text="error"></p>
                    @error('image') <p class="ep-error">{{ $message }}</p> @enderror

                    <div class="ep-actions ep-actions--row">
                        <button type="button" class="ep-btn ep-btn--primary" x-show="!preview" @click="capture()">
                            Capturar
                        </button>
                        <button
                            type="button"
                            class="ep-btn ep-btn--primary"
                            x-show="preview"
                            @click="use()"
                            wire:loading.attr="disabled"
                            wire:target="saveFingerprintCapture"
                        >
                            <span wire:loading.remove wire:target="saveFingerprintCapture">Usar esta huella</span>
                            <span wire:loading wire:target="saveFingerprintCapture">Guardando…</span>
                        </button>
                        <button type="button" class="ep-btn ep-btn--secondary" x-show="preview" @click="retake()">Repetir</button>
                        @if ($fingerprintPreviewUrl)
                            <button type="button" class="ep-btn ep-btn--secondary" wire:click="keepExistingFingerprint">
                                Usar la huella que ya tengo
                            </button>
                        @endif
                    </div>
                </div>

                <aside class="ep-upload-panel ep-glass">
                    <p class="ep-upload-title">Cargar foto o escaneo</p>
                    <p class="ep-upload-hint">En el computador esta es la forma más cómoda. PNG, JPG o WEBP.</p>
                    <label class="ep-drop">
                        <input type="file" accept="image/png,image/jpeg,image/webp" wire:model="fingerprintUpload">
                        <span>Elegir archivo</span>
                    </label>
                    @error('fingerprintUpload') <p class="ep-error">{{ $message }}</p> @enderror
                    @if ($fingerprintUpload)
                        <img src="{{ $fingerprintUpload->temporaryUrl() }}" alt="Vista previa de la huella" class="ep-preview">
                        <button type="button" class="ep-btn ep-btn--secondary" wire:click="saveFingerprintUpload" wire:loading.attr="disabled">
                            Usar esta imagen
                        </button>
                    @endif
                </aside>
            </div>
        </div>
    @else
        <div class="ep-screen" wire:key="done">
            <div class="ep-success">
                <div>
                    <div class="ep-check" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor" class="size-10">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                    </div>
                    <h1 class="ep-lead">Firma y huella cargadas</h1>
                    <p class="ep-text">Tu firma y tu huella se cargaron con éxito. Ya quedaron en tu expediente y se usarán en tus recibos.</p>
                    <div class="ep-actions">
                        <a href="{{ route('employee-portal.home') }}" class="ep-btn ep-btn--primary" wire:navigate>Ir al inicio</a>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('employee-portal.partials.menu-sheet', ['active' => 'file'])
</div>
