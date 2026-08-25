/**
 * Farmadoc App (PWA `/app`).
 *
 * Alpine viaja dentro de Livewire, así que todo se registra en `alpine:init`.
 * Cubre: tema, avisos, deslizar para eliminar, teclado, búsquedas recientes,
 * instalación de la PWA y registro del service worker.
 */

const THEME_KEY = 'fd-shop-theme';
const RECENTS_KEY = 'fd-shop-recent';

const persistThemeCookie = (theme) => {
    document.cookie = `${THEME_KEY}=${theme}; Path=/; Max-Age=31536000; SameSite=Lax`
        + (window.location.protocol === 'https:' ? '; Secure' : '');
};

const storedTheme = () => {
    try {
        const saved = localStorage.getItem(THEME_KEY);

        if (saved === 'dark' || saved === 'light') {
            return saved;
        }
    } catch {
        /* sin almacenamiento */
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
};

const applyTheme = (theme) => {
    const next = theme === 'dark' ? 'dark' : 'light';
    const root = document.documentElement;

    root.setAttribute('data-shop-theme', next);
    root.classList.toggle('dark', next === 'dark');
    root.style.colorScheme = next;

    const meta = document.querySelector('meta[data-shop-theme-color]');
    if (meta) {
        meta.setAttribute('content', next === 'dark' ? '#061215' : '#f2f9f9');
    }

    try {
        localStorage.setItem(THEME_KEY, next);
    } catch {
        /* almacenamiento no disponible */
    }

    persistThemeCookie(next);

    document.dispatchEvent(new CustomEvent('shop-theme-changed', { detail: { dark: next === 'dark' } }));
};

const syncTheme = () => {
    applyTheme(storedTheme());

    const store = window.Alpine?.store('shop');

    if (store) {
        store.dark = storedTheme() === 'dark';
    }
};

window.shopTheme = {
    isDark: () => storedTheme() === 'dark',
    toggle: () => applyTheme(window.shopTheme.isDark() ? 'light' : 'dark'),
    sync: syncTheme,
};

const haptic = (pattern = 8) => {
    try {
        navigator.vibrate?.(pattern);
    } catch {
        /* sin soporte de vibración */
    }
};

const isIos = () => /iphone|ipad|ipod/i.test(navigator.userAgent);
const isStandalone = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;

const syncKeyboardInset = () => {
    const viewport = window.visualViewport;

    if (! viewport) {
        return;
    }

    const inset = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);
    const rounded = Math.round(inset);

    document.documentElement.style.setProperty('--sh-keyboard', `${rounded}px`);
    document.body.classList.toggle('is-keyboard', rounded > 80);
};

document.addEventListener('focusin', (event) => {
    const target = event.target;

    if (! (target instanceof HTMLElement) || ! target.matches('input, textarea, select')) {
        return;
    }

    if (! target.closest('.sh-checkout')) {
        return;
    }

    window.setTimeout(() => {
        target.scrollIntoView({ block: 'center', inline: 'nearest' });
    }, 280);
});

document.addEventListener('alpine:init', () => {
    const alpine = window.Alpine;

    alpine.store('shop', {
        cartCount: 0,
        toast: null,
        toastTimer: null,
        dark: storedTheme() === 'dark',
        canInstall: false,
        installPrompt: null,
        showIosInstall: isIos() && ! isStandalone(),

        init() {
            applyTheme(storedTheme());
            this.dark = storedTheme() === 'dark';

            document.addEventListener('shop-theme-changed', (event) => {
                this.dark = Boolean(event.detail?.dark);
            });
        },

        toggleTheme() {
            window.shopTheme.toggle();
            this.dark = window.shopTheme.isDark();
            haptic();
        },

        notify(message) {
            if (! message) {
                return;
            }

            this.toast = message;
            haptic();

            window.clearTimeout(this.toastTimer);
            this.toastTimer = window.setTimeout(() => {
                this.toast = null;
            }, 2400);
        },

        async install() {
            if (! this.installPrompt) {
                return;
            }

            this.installPrompt.prompt();
            await this.installPrompt.userChoice;
            this.installPrompt = null;
            this.canInstall = false;
        },
    });

    alpine.data('shopShell', (initialCount = 0) => ({
        menuOpen: false,
        scrolled: false,
        dragging: false,
        dragY: 0,
        startY: 0,

        init() {
            this.$store.shop.cartCount = initialCount;

            this._onScroll = () => {
                this.scrolled = window.scrollY > 6;
            };
            window.addEventListener('scroll', this._onScroll, { passive: true });
            this._onScroll();

            this._onNavigating = () => this.close();
            document.addEventListener('livewire:navigating', this._onNavigating);
        },

        destroy() {
            window.removeEventListener('scroll', this._onScroll);
            document.removeEventListener('livewire:navigating', this._onNavigating);
            this.unlockScroll();
        },

        onCartUpdated(event) {
            this.$store.shop.cartCount = Number(event.detail?.count ?? 0);
        },

        onToast(event) {
            this.$store.shop.notify(event.detail?.message);
        },

        open() {
            if (this.menuOpen) {
                return;
            }

            this.menuOpen = true;
            this.lockScroll();
            haptic();
            this.$nextTick(() => this.$refs.menuSheet?.focus({ preventScroll: true }));
        },

        close() {
            if (! this.menuOpen) {
                return;
            }

            this.menuOpen = false;
            this.dragging = false;
            this.dragY = 0;
            this.unlockScroll();
        },

        lockScroll() {
            document.body.classList.add('is-locked');
        },

        unlockScroll() {
            document.body.classList.remove('is-locked');
        },

        sheetStyle() {
            if (! this.dragging && this.dragY === 0) {
                return {};
            }

            return {
                transform: `translate(-50%, ${this.dragY}px)`,
                opacity: String(Math.max(0.35, 1 - this.dragY / 420)),
            };
        },

        onDragStart(event) {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            this.dragging = true;
            this.startY = event.clientY;
            this.dragY = 0;
            event.currentTarget.setPointerCapture?.(event.pointerId);
        },

        onDragMove(event) {
            if (! this.dragging) {
                return;
            }

            this.dragY = Math.max(0, event.clientY - this.startY);
        },

        onDragEnd() {
            if (! this.dragging) {
                return;
            }

            const shouldClose = this.dragY > 76;
            this.dragging = false;
            this.dragY = 0;

            if (shouldClose) {
                this.close();
            }
        },
    }));

    alpine.data('shopSearchRecents', () => ({
        recents: [],

        get showRecents() {
            return ! this.$wire.term;
        },

        init() {
            this.recents = this.read();

            this.$nextTick(() => {
                if (! this.$wire.term) {
                    this.$refs.query?.focus({ preventScroll: true });
                }
            });
        },

        read() {
            try {
                const stored = JSON.parse(localStorage.getItem(RECENTS_KEY) || '[]');

                return Array.isArray(stored) ? stored.filter((item) => typeof item === 'string').slice(0, 8) : [];
            } catch {
                return [];
            }
        },

        remember(term) {
            const value = String(term || '').trim();

            if (value.length < 2) {
                return;
            }

            this.recents = [value, ...this.recents.filter((item) => item !== value)].slice(0, 8);

            try {
                localStorage.setItem(RECENTS_KEY, JSON.stringify(this.recents));
            } catch {
                /* sin almacenamiento */
            }
        },

        apply(term) {
            this.$wire.set('term', term);
            this.remember(term);
        },

        forgetAll() {
            this.recents = [];

            try {
                localStorage.removeItem(RECENTS_KEY);
            } catch {
                /* sin almacenamiento */
            }
        },

        clearInput() {
            this.$refs.query?.focus({ preventScroll: true });
        },
    }));

    alpine.data('shopSwipe', (productId) => ({
        offset: 0,
        startX: 0,
        startY: 0,
        swiping: false,
        locked: false,
        maxOffset: 90,

        style() {
            return this.offset === 0 ? {} : { transform: `translateX(${-this.offset}px)` };
        },

        start(event) {
            this.startX = event.clientX;
            this.startY = event.clientY;
            this.swiping = false;
            this.locked = false;
        },

        move(event) {
            if (this.locked) {
                return;
            }

            const deltaX = this.startX - event.clientX;
            const deltaY = Math.abs(this.startY - event.clientY);

            if (! this.swiping) {
                if (deltaY > 12 && deltaY > Math.abs(deltaX)) {
                    this.locked = true;

                    return;
                }

                if (Math.abs(deltaX) < 8) {
                    return;
                }

                this.swiping = true;
                event.currentTarget.setPointerCapture?.(event.pointerId);
            }

            this.offset = Math.max(0, Math.min(this.maxOffset + 34, deltaX));
        },

        end() {
            if (! this.swiping) {
                this.offset = 0;

                return;
            }

            this.swiping = false;

            if (this.offset > this.maxOffset) {
                haptic([10, 40, 10]);
                this.offset = 0;
                this.$wire.call('removeFromCart', productId);

                return;
            }

            this.offset = this.offset > 34 ? this.maxOffset : 0;
        },

        reset() {
            this.offset = 0;
        },
    }));
});

const resetHorizontalOverflow = () => {
    window.scrollTo(0, window.scrollY || 0);
    document.documentElement.scrollLeft = 0;
    document.body.scrollLeft = 0;

    document.querySelectorAll('.sh-shell, .sh-auth, .sh-auth-host, .sh-gate').forEach((el) => {
        el.scrollLeft = 0;
    });
};

['livewire:navigated', 'alpine:navigated'].forEach((name) => {
    document.addEventListener(name, () => {
        syncTheme();
        resetHorizontalOverflow();
    });
});

window.addEventListener('pageshow', resetHorizontalOverflow);
window.addEventListener('popstate', resetHorizontalOverflow);

if ('scrollRestoration' in history) {
    history.scrollRestoration = 'manual';
}

resetHorizontalOverflow();

document.addEventListener('click', (event) => {
    if (event.target.closest('.sh-tab, .sh-add, .sh-stepper button')) {
        haptic();
    }
});

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();

    const store = window.Alpine?.store('shop');

    if (store) {
        store.installPrompt = event;
        store.canInstall = true;
    }
});

if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', syncKeyboardInset, { passive: true });
    window.visualViewport.addEventListener('scroll', syncKeyboardInset, { passive: true });
    syncKeyboardInset();
}

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/shop-sw.js?v=9', { scope: '/app' }).catch(() => {
            /* sin service worker la app sigue funcionando */
        });
    });
}
