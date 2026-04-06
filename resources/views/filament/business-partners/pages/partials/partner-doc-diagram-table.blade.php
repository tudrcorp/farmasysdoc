{{-- Tabla ejemplo: gradiente y resaltados adaptados a tema vía variables CSS. --}}
<div class="fi-bp-doc-figure" role="img" aria-label="Esquema de la tabla de pedidos con zonas interactivas">
    <svg viewBox="0 0 720 280" class="fi-bp-doc-figure__svg" xmlns="http://www.w3.org/2000/svg">
        <defs>
            <linearGradient id="fiBpDocTableGlassGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="var(--fi-bp-doc-glass-a)" stop-opacity="1" />
                <stop offset="100%" stop-color="var(--fi-bp-doc-glass-b)" stop-opacity="1" />
            </linearGradient>
            <filter id="fiBpDocTableSoftGlow" x="-25%" y="-25%" width="150%" height="150%">
                <feGaussianBlur stdDeviation="2.5" result="b" />
                <feMerge>
                    <feMergeNode in="b" />
                    <feMergeNode in="SourceGraphic" />
                </feMerge>
            </filter>
        </defs>
        <rect
            x="8"
            y="8"
            width="704"
            height="264"
            rx="18"
            fill="url(#fiBpDocTableGlassGradient)"
            stroke="var(--fi-bp-doc-frame-border)"
            stroke-width="1"
        />
        <text x="24" y="36" fill="currentColor" class="fi-bp-doc-figure__title" font-size="13" font-weight="600">Listado de pedidos (ejemplo)</text>
        <g font-size="11" fill="currentColor" opacity="0.68">
            <text x="24" y="62">Nº pedido</text>
            <text x="200" y="62">Cliente</text>
            <text x="380" y="62">Ítems</text>
            <text x="470" y="62">Estado</text>
            <text x="600" y="62">Acciones</text>
        </g>
        <line x1="16" y1="72" x2="704" y2="72" stroke="var(--fi-bp-doc-line)" stroke-width="1" />
        <g>
            <rect
                x="20"
                y="88"
                width="110"
                height="36"
                rx="10"
                fill="var(--fi-bp-doc-badge-fill)"
                stroke="var(--fi-bp-doc-badge-stroke)"
                stroke-width="1.5"
                filter="url(#fiBpDocTableSoftGlow)"
            />
            <text x="75" y="110" text-anchor="middle" fill="currentColor" font-size="12" font-weight="600">FD-1024</text>
            <rect x="196" y="88" width="170" height="36" rx="8" fill="var(--fi-bp-doc-cell)" stroke="var(--fi-bp-doc-cell-border)" stroke-width="1" />
            <text x="206" y="110" fill="currentColor" font-size="11">Farmacia ejemplo</text>
            <rect
                x="372"
                y="88"
                width="72"
                height="36"
                rx="8"
                fill="var(--fi-bp-doc-teal-mid)"
                stroke="var(--fi-bp-doc-teal-stroke)"
                stroke-width="1.5"
                filter="url(#fiBpDocTableSoftGlow)"
            />
            <text x="408" y="110" text-anchor="middle" fill="currentColor" font-size="12" font-weight="600">12</text>
            <rect x="460" y="88" width="88" height="28" rx="6" fill="var(--fi-bp-doc-amber-fill)" stroke="var(--fi-bp-doc-amber-stroke)" stroke-width="1" />
            <text x="504" y="106" text-anchor="middle" fill="currentColor" font-size="10" font-weight="600">En proceso</text>
            <rect x="568" y="88" width="132" height="36" rx="8" fill="var(--fi-bp-doc-cell)" stroke="var(--fi-bp-doc-cell-border)" stroke-width="1" />
            <text x="634" y="110" text-anchor="middle" fill="currentColor" font-size="11">⋯</text>
        </g>
        <path d="M 75 132 Q 75 168 120 188" fill="none" stroke="var(--fi-bp-doc-arrow)" stroke-width="1.5" stroke-dasharray="4 3" />
        <rect x="16" y="196" width="200" height="68" rx="12" fill="var(--fi-bp-doc-callout-fill)" stroke="var(--fi-bp-doc-callout-stroke)" stroke-width="1" />
        <text x="28" y="218" fill="currentColor" font-size="10" font-weight="600">1 · Nº pedido</text>
        <text x="28" y="236" fill="currentColor" font-size="9" opacity="0.88">Abre panel con repartidor o</text>
        <text x="28" y="250" fill="currentColor" font-size="9" opacity="0.88">resumen si ya finalizó.</text>
        <path d="M 408 132 Q 408 160 360 188" fill="none" stroke="var(--fi-bp-doc-arrow)" stroke-width="1.5" stroke-dasharray="4 3" />
        <rect x="232" y="196" width="200" height="68" rx="12" fill="var(--fi-bp-doc-callout-fill)" stroke="var(--fi-bp-doc-callout-stroke)" stroke-width="1" />
        <text x="244" y="218" fill="currentColor" font-size="10" font-weight="600">2 · Ítems (número)</text>
        <text x="244" y="236" fill="currentColor" font-size="9" opacity="0.88">Lista de productos y</text>
        <text x="244" y="250" fill="currentColor" font-size="9" opacity="0.88">cantidades del pedido.</text>
        <path d="M 634 132 Q 634 168 560 188" fill="none" stroke="var(--fi-bp-doc-arrow)" stroke-width="1.5" stroke-dasharray="4 3" />
        <rect x="448" y="196" width="256" height="68" rx="12" fill="var(--fi-bp-doc-callout-fill)" stroke="var(--fi-bp-doc-callout-stroke)" stroke-width="1" />
        <text x="460" y="218" fill="currentColor" font-size="10" font-weight="600">3 · Menú Acciones</text>
        <text x="460" y="236" fill="currentColor" font-size="9" opacity="0.88">Calificar, comprobante, ver,</text>
        <text x="460" y="250" fill="currentColor" font-size="9" opacity="0.88">editar (según corresponda).</text>
    </svg>
</div>
