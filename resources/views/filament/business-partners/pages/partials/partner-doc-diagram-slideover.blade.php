{{-- Panel lateral: marco “dispositivo” y slideover coherentes en claro/oscuro. --}}
<div class="fi-bp-doc-figure" role="img" aria-label="Esquema de panel lateral deslizante">
    <svg viewBox="0 0 560 204" class="fi-bp-doc-figure__svg" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <marker id="fiBpDocSlideArrowHead" markerWidth="7" markerHeight="7" refX="6" refY="3.5" orient="auto">
                <path d="M0,0 L7,3.5 L0,7 Z" fill="var(--fi-bp-doc-arrow)" />
            </marker>
        </defs>
        <rect x="10" y="16" width="300" height="168" rx="20" fill="var(--fi-bp-doc-phone-bg)" stroke="var(--fi-bp-doc-phone-border)" stroke-width="1" />
        <text x="26" y="40" fill="currentColor" font-size="10" opacity="0.55">Vista tabla</text>
        <rect x="22" y="52" width="88" height="22" rx="8" fill="var(--fi-bp-doc-badge-fill)" stroke="var(--fi-bp-doc-badge-stroke)" stroke-width="1" />
        <text x="66" y="67" text-anchor="middle" fill="currentColor" font-size="9" font-weight="600">Nº pedido</text>
        <rect x="200" y="52" width="36" height="22" rx="7" fill="var(--fi-bp-doc-teal-mid)" stroke="var(--fi-bp-doc-teal-stroke)" stroke-width="1" />
        <text x="218" y="67" text-anchor="middle" fill="currentColor" font-size="9" font-weight="600">8</text>
        <path
            d="M 66 86 Q 200 120 330 96"
            fill="none"
            stroke="var(--fi-bp-doc-arrow)"
            stroke-width="1.4"
            stroke-dasharray="5 4"
            marker-end="url(#fiBpDocSlideArrowHead)"
        />
        <rect
            class="fi-bp-doc-svg__panel-shadow"
            x="330"
            y="24"
            width="218"
            height="160"
            rx="16"
            fill="var(--fi-bp-doc-panel-bg)"
            stroke="var(--fi-bp-doc-panel-border)"
            stroke-width="1"
        />
        <text x="346" y="48" fill="currentColor" font-size="11" font-weight="600">Ventana lateral</text>
        <text x="346" y="66" fill="currentColor" font-size="8" opacity="0.68">Misma idea que en el teléfono:</text>
        <text x="346" y="80" fill="currentColor" font-size="8" opacity="0.68">desliza desde la derecha</text>
        <rect x="346" y="92" width="186" height="50" rx="10" fill="var(--fi-bp-doc-panel-inner)" stroke="var(--fi-bp-doc-cell-border)" stroke-width="1" />
        <text x="358" y="110" fill="currentColor" font-size="8" opacity="0.62">Contenido: repartidor, productos</text>
        <text x="358" y="124" fill="currentColor" font-size="8" opacity="0.62">o comprobante de pago.</text>
        <rect x="346" y="150" width="72" height="22" rx="10" fill="var(--fi-bp-doc-btn-fill)" stroke="var(--fi-bp-doc-btn-stroke)" stroke-width="1" />
        <text x="382" y="165" text-anchor="middle" fill="currentColor" font-size="8" font-weight="600">Listo</text>
        <text x="10" y="198" fill="currentColor" font-size="8" opacity="0.48">No es una página nueva: es un panel encima del listado.</text>
    </svg>
</div>
