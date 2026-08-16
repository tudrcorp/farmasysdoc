<button
    type="button"
    class="ep-menu-trigger"
    @click="toggle()"
    :class="{ 'is-open': menuOpen }"
    :aria-expanded="menuOpen.toString()"
    :aria-label="menuOpen ? 'Cerrar menú' : 'Abrir menú'"
    aria-controls="ep-portal-menu"
>
    <span class="ep-menu-trigger-icon" :class="{ 'is-open': menuOpen }" aria-hidden="true">
        <span></span>
        <span></span>
        <span></span>
    </span>
</button>
