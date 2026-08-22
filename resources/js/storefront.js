const CART_KEY = 'farmadoc.storefront.cart.v1';
const THEME_KEY = 'welcome-theme';

const money = (value) => Number(value || 0).toLocaleString('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});

const moneyVes = (value) => `Bs. ${Number(value || 0).toLocaleString('es-VE', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
})}`;

const escapeHtml = (text) => String(text ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#039;');

const boot = () => {
    try {
        return JSON.parse(document.getElementById('storefront-boot')?.textContent || '{}');
    } catch {
        return {};
    }
};

const readCart = () => {
    try {
        const raw = JSON.parse(localStorage.getItem(CART_KEY) || '[]');

        return Array.isArray(raw) ? raw : [];
    } catch {
        return [];
    }
};

const writeCart = (items) => {
    localStorage.setItem(CART_KEY, JSON.stringify(items));
};

const config = boot();
let cart = readCart();

const usdVesRate = () => {
    const rate = Number(config.usdVesRate || 0);

    return Number.isFinite(rate) && rate > 0 ? rate : 0;
};

const els = {
    header: document.querySelector('[data-storefront-header]'),
    overlay: document.querySelector('[data-overlay]'),
    drawer: document.querySelector('[data-cart-drawer]'),
    cartList: document.querySelector('[data-cart-list]'),
    cartCount: document.querySelectorAll('[data-cart-count]'),
    cartTotal: document.querySelectorAll('[data-cart-total]'),
    cartTotalVes: document.querySelectorAll('[data-cart-total-ves]'),
    cartRate: document.querySelector('[data-cart-rate]'),
    cartItemsLabel: document.querySelector('[data-cart-items-label]'),
    checkoutBtn: document.querySelector('[data-checkout-pay]'),
    cartEmpty: document.querySelector('[data-cart-empty]'),
    searchInput: document.querySelector('[data-storefront-search]'),
    searchDropdown: document.querySelector('[data-search-dropdown]'),
    toast: document.querySelector('[data-toast]'),
    mobileNav: document.querySelector('[data-mobile-nav]'),
};

const toast = (message) => {
    if (! els.toast) {
        return;
    }

    els.toast.textContent = message;
    els.toast.classList.add('is-on');
    window.clearTimeout(toast._t);
    toast._t = window.setTimeout(() => els.toast.classList.remove('is-on'), 2400);
};

const setLayerOpen = (node, open) => {
    if (! node) {
        return;
    }

    node.classList.toggle('is-open', open);
    node.setAttribute('aria-hidden', open ? 'false' : 'true');
};

const setOverlay = (open) => {
    els.overlay?.classList.toggle('is-open', open);
    document.body.style.overflow = open ? 'hidden' : '';
};

const closeAllLayers = () => {
    setLayerOpen(els.drawer, false);
    setLayerOpen(els.mobileNav, false);
    document.querySelectorAll('[data-modal].is-open').forEach((node) => node.classList.remove('is-open'));
    setOverlay(false);
};

const cartQty = () => cart.reduce((sum, line) => sum + Number(line.qty || 0), 0);

const cartTotal = () => cart.reduce((sum, line) => sum + Number(line.effective_price || 0) * Number(line.qty || 0), 0);

const cartTotalVes = () => {
    const rate = usdVesRate();

    return rate > 0 ? cartTotal() * rate : 0;
};

const renderCart = () => {
    const count = cartQty();
    const totalUsd = cartTotal();
    const totalVes = cartTotalVes();
    const rate = usdVesRate();

    els.cartCount.forEach((node) => {
        node.textContent = String(count);
        node.classList.toggle('is-on', count > 0);
    });

    els.cartTotal.forEach((node) => {
        node.textContent = money(totalUsd);
    });

    els.cartTotalVes.forEach((node) => {
        node.textContent = rate > 0 ? moneyVes(totalVes) : '—';
    });

    if (els.cartItemsLabel) {
        els.cartItemsLabel.textContent = count === 1 ? '1 producto' : `${count} productos`;
    }

    if (els.cartRate) {
        els.cartRate.textContent = rate > 0
            ? `Tasa BCV del día · ${moneyVes(rate)} / USD`
            : 'Tasa BCV no disponible en este momento';
    }

    if (els.checkoutBtn) {
        els.checkoutBtn.disabled = cart.length === 0;
    }

    if (! els.cartList) {
        return;
    }

    if (cart.length === 0) {
        els.cartList.innerHTML = '';
        els.cartEmpty?.classList.remove('hidden');

        return;
    }

    els.cartEmpty?.classList.add('hidden');
    els.cartList.innerHTML = cart.map((line) => {
        const qty = Number(line.qty || 0);
        const unit = Number(line.effective_price || 0);
        const lineTotal = unit * qty;

        return `
        <article class="fd-cart-line" data-line-id="${line.id}">
            <img src="${escapeHtml(line.image_url)}" alt="">
            <div class="fd-cart-line__body">
                <strong>${escapeHtml(line.name)}</strong>
                <p class="fd-cart-line__meta">${escapeHtml(line.brand)} · ${money(unit)} c/u</p>
                <div class="fd-cart-line__tools">
                    <div class="fd-qty">
                        <button type="button" data-qty-delta="-1" aria-label="Quitar uno">−</button>
                        <span>${qty}</span>
                        <button type="button" data-qty-delta="1" aria-label="Agregar uno">+</button>
                    </div>
                    <span class="fd-cart-line__sum">${money(lineTotal)}</span>
                </div>
            </div>
            <button type="button" class="fd-cart-line__remove" data-remove-line aria-label="Quitar del carrito">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M9 7V5h6v2M8 7l1 13h6l1-13"/></svg>
            </button>
        </article>
    `;
    }).join('');
};

const pulseBadges = () => {
    els.cartCount.forEach((node) => {
        node.classList.remove('is-pulse');
        void node.offsetWidth;
        node.classList.add('is-pulse');
    });
};

const saveAndRender = () => {
    writeCart(cart);
    renderCart();
};

const addToCart = (product, qty = 1, sourceImg = null) => {
    if (! product?.id) {
        return;
    }

    const stock = Number(product.stock_available || 0);
    const existing = cart.find((line) => Number(line.id) === Number(product.id));
    const nextQty = (existing ? Number(existing.qty) : 0) + qty;

    if (stock > 0 && nextQty > stock) {
        toast('Alcanzaste el stock disponible de este producto.');

        return;
    }

    if (existing) {
        existing.qty = nextQty;
    } else {
        cart.push({
            id: product.id,
            name: product.name,
            brand: product.brand,
            image_url: product.image_url,
            effective_price: product.effective_price,
            sale_price: product.sale_price,
            qty,
        });
    }

    saveAndRender();
    flyToCart(sourceImg);
    toast(`${product.name} se agregó al carrito`);
};

const receiveInCart = () => {
    document.querySelectorAll('.fd-cart-btn').forEach((btn) => {
        btn.classList.remove('is-catching');
        void btn.offsetWidth;
        btn.classList.add('is-catching');
        window.clearTimeout(btn._catchTimer);
        btn._catchTimer = window.setTimeout(() => btn.classList.remove('is-catching'), 720);
    });
    pulseBadges();
};

const flyToCart = (sourceImg) => {
    const target = document.querySelector('.fd-cart-btn[data-cart-button]');

    if (! sourceImg || ! target || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        receiveInCart();

        return;
    }

    const from = sourceImg.getBoundingClientRect();
    const to = target.getBoundingClientRect();
    const ghost = document.createElement('img');
    ghost.className = 'fd-fly';
    ghost.src = sourceImg.currentSrc || sourceImg.src;
    ghost.alt = '';
    ghost.style.left = `${from.left + from.width / 2 - 26}px`;
    ghost.style.top = `${from.top + from.height / 2 - 26}px`;
    document.body.appendChild(ghost);

    const dx = to.left + to.width / 2 - (from.left + from.width / 2);
    const dy = to.top + to.height / 2 - (from.top + from.height / 2);

    const animation = ghost.animate([
        { transform: 'translate(0, 0) scale(1)', opacity: 1, offset: 0 },
        { transform: `translate(${dx * 0.45}px, ${dy * 0.2 - 80}px) scale(0.7)`, opacity: 0.9, offset: 0.45 },
        { transform: `translate(${dx}px, ${dy}px) scale(0.18)`, opacity: 0.35, offset: 0.86 },
        { transform: `translate(${dx}px, ${dy}px) scale(0.02)`, opacity: 0, offset: 1 },
    ], {
        duration: 780,
        easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
    });

    window.setTimeout(receiveInCart, 640);
    animation.finished.finally(() => ghost.remove()).catch(() => ghost.remove());
};

const productFromNode = (node) => {
    const raw = node?.closest('[data-product]')?.getAttribute('data-product');

    if (! raw) {
        return null;
    }

    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
};

const openDrawer = () => {
    document.querySelectorAll('[data-modal].is-open').forEach((node) => node.classList.remove('is-open'));
    setLayerOpen(els.mobileNav, false);
    setLayerOpen(els.drawer, true);
    setOverlay(true);
};

const openModal = (id) => {
    setLayerOpen(els.drawer, false);
    setLayerOpen(els.mobileNav, false);
    const modal = document.querySelector(`[data-modal="${id}"]`);
    modal?.classList.add('is-open');
    setOverlay(true);
};

const openMobileNav = () => {
    if (window.matchMedia('(min-width: 761px)').matches) {
        return;
    }

    document.querySelectorAll('[data-modal].is-open').forEach((node) => node.classList.remove('is-open'));
    setLayerOpen(els.drawer, false);
    setLayerOpen(els.mobileNav, true);
    setOverlay(true);
};

/** Cantidad seleccionada en la ficha de producto. */
const quickView = { qty: 1, max: 99 };

const fillQuickView = (product) => {
    const root = document.querySelector('[data-modal="product"]');

    if (! root || ! product) {
        return;
    }

    const image = root.querySelector('[data-qv-image]');
    const name = root.querySelector('[data-qv-name]');
    const meta = root.querySelector('[data-qv-meta]');
    const price = root.querySelector('[data-qv-price]');
    const list = root.querySelector('[data-qv-list]');
    const stock = root.querySelector('[data-qv-stock]');
    const ingredient = root.querySelector('[data-qv-ingredient]');
    const add = root.querySelector('[data-add-from-modal]');

    if (image) {
        image.src = product.image_url;
        image.alt = product.name || '';
    }

    if (name) {
        name.textContent = product.name;
    }

    if (meta) {
        meta.textContent = `${product.brand} · ${product.presentation}`;
    }

    if (price) {
        price.textContent = money(product.effective_price);
    }

    if (list) {
        if (Number(product.discount_percent) > 0) {
            list.textContent = money(product.sale_price);
            list.hidden = false;
        } else {
            list.hidden = true;
        }
    }

    if (stock) {
        stock.textContent = Number(product.stock_available) > 0
            ? `Stock disponible: ${Number(product.stock_available).toLocaleString('es-VE')}`
            : 'Sin stock';
    }

    if (ingredient) {
        ingredient.textContent = product.active_ingredient || '—';
    }

    const discount = root.querySelector('[data-qv-discount]');

    if (discount) {
        const percent = Number(product.discount_percent) || 0;
        discount.textContent = `-${Math.round(percent)}%`;
        discount.hidden = percent <= 0;
    }

    const rx = root.querySelector('[data-qv-rx]');

    if (rx) {
        rx.hidden = ! product.requires_prescription;
    }

    quickView.max = Math.max(1, Math.min(Math.floor(Number(product.stock_available) || 1), 99));
    quickView.qty = 1;
    renderQuickViewQty();

    add?.setAttribute('data-product', JSON.stringify(product));
};

const renderQuickViewQty = () => {
    const root = document.querySelector('[data-modal="product"]');

    if (! root) {
        return;
    }

    const value = root.querySelector('[data-qv-qty-value]');
    const minus = root.querySelector('[data-qv-minus]');
    const plus = root.querySelector('[data-qv-plus]');

    if (value) {
        value.textContent = String(quickView.qty);
    }

    minus?.toggleAttribute('disabled', quickView.qty <= 1);
    plus?.toggleAttribute('disabled', quickView.qty >= quickView.max);
};

const stepQuickViewQty = (delta) => {
    quickView.qty = Math.max(1, Math.min(quickView.max, quickView.qty + delta));
    renderQuickViewQty();
};

const startCheckout = async () => {
    if (cart.length === 0) {
        toast('Agrega productos al carrito para continuar.');

        return;
    }

    const endpoint = config.checkoutEndpoint;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (! endpoint) {
        toast('No pudimos iniciar el pago. Recarga e intenta de nuevo.');

        return;
    }

    const button = els.checkoutBtn;
    button?.setAttribute('disabled', 'disabled');

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                items: cart.map((line) => ({
                    product_id: Number(line.id),
                    quantity: Number(line.qty || 0),
                })),
            }),
        });

        const payload = await response.json().catch(() => ({}));

        if (! response.ok || typeof payload.redirect !== 'string' || payload.redirect === '') {
            throw new Error(typeof payload.message === 'string' ? payload.message : 'checkout failed');
        }

        window.location.assign(payload.redirect);
    } catch (error) {
        if (cart.length > 0) {
            button?.removeAttribute('disabled');
        }

        toast(error instanceof Error && error.message !== 'checkout failed'
            ? error.message
            : 'No pudimos iniciar el pago. Intenta de nuevo.');
    }
};

const bindTheme = () => {
    const buttons = document.querySelectorAll('[data-theme]');
    const apply = (theme) => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        buttons.forEach((button) => button.classList.toggle('is-on', button.dataset.theme === theme));
    };
    const stored = localStorage.getItem(THEME_KEY);
    const initial = stored ?? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    apply(initial);

    buttons.forEach((button) => {
        button.addEventListener('click', () => {
            localStorage.setItem(THEME_KEY, button.dataset.theme);
            apply(button.dataset.theme);
        });
    });
};

const bindHeader = () => {
    const onScroll = () => {
        els.header?.classList.toggle('is-compact', window.scrollY > 18);
    };

    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
};

const bindCarousels = () => {
    document.querySelectorAll('[data-carousel]').forEach((root) => {
        const track = root.querySelector('[data-carousel-track]');

        if (! track) {
            return;
        }

        const prev = root.querySelector('[data-carousel-prev]');
        const next = root.querySelector('[data-carousel-next]');

        const stepSize = () => {
            const card = track.querySelector('[data-product-card], .fd-product-card');

            if (! card) {
                return Math.max(240, track.clientWidth * 0.8);
            }

            const gap = Number.parseFloat(getComputedStyle(track).columnGap || getComputedStyle(track).gap) || 12;

            return card.getBoundingClientRect().width + gap;
        };

        const syncNav = () => {
            const max = Math.max(0, track.scrollWidth - track.clientWidth);
            const canScroll = max > 8;

            root.classList.toggle('is-scrollable', canScroll);

            if (prev) {
                prev.disabled = ! canScroll || track.scrollLeft <= 8;
            }

            if (next) {
                next.disabled = ! canScroll || track.scrollLeft >= max - 8;
            }
        };

        const scrollByStep = (direction) => {
            track.scrollBy({ left: direction * stepSize(), behavior: 'smooth' });
        };

        prev?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            scrollByStep(-1);
        });

        next?.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            scrollByStep(1);
        });

        let pointerId = null;
        let startX = 0;
        let startLeft = 0;
        let moved = false;

        track.addEventListener('pointerdown', (event) => {
            if (event.pointerType === 'mouse' && event.button !== 0) {
                return;
            }

            if (event.target.closest('button, a, input, textarea, select, label, [data-add-to-cart]')) {
                return;
            }

            pointerId = event.pointerId;
            startX = event.clientX;
            startLeft = track.scrollLeft;
            moved = false;
            track.dataset.suppressClick = '0';
            track.setPointerCapture(event.pointerId);
        });

        track.addEventListener('pointermove', (event) => {
            if (pointerId !== event.pointerId) {
                return;
            }

            const dx = event.clientX - startX;

            if (Math.abs(dx) < 8) {
                return;
            }

            moved = true;
            track.classList.add('is-dragging');
            track.scrollLeft = startLeft - dx;
        });

        const endDrag = (event) => {
            if (pointerId !== event.pointerId) {
                return;
            }

            pointerId = null;
            track.classList.remove('is-dragging');
            track.dataset.suppressClick = moved ? '1' : '0';
            syncNav();
        };

        track.addEventListener('pointerup', endDrag);
        track.addEventListener('pointercancel', endDrag);

        track.addEventListener('scroll', syncNav, { passive: true });
        window.addEventListener('resize', syncNav);

        track.addEventListener('wheel', (event) => {
            if (Math.abs(event.deltaY) > Math.abs(event.deltaX) && ! event.shiftKey) {
                track.scrollLeft += event.deltaY;
                event.preventDefault();
            }
        }, { passive: false });

        syncNav();
        window.requestAnimationFrame(syncNav);
    });
};

const bindSearch = () => {
    const input = els.searchInput;
    const dropdown = els.searchDropdown;

    if (! input || ! dropdown) {
        return;
    }

    let timer = null;
    let abortController = null;
    let activeCategoryId = 0;
    let activeOffers = false;
    const cache = new Map();
    const endpoint = input.dataset.searchEndpoint || config.searchEndpoint;

    const openDropdown = () => dropdown.classList.add('is-open');
    const closeDropdown = () => dropdown.classList.remove('is-open');

    const cacheKey = (query, categoryId, offers) => `${categoryId}|${offers ? 1 : 0}|${query.toLowerCase()}`;

    const remember = (key, items) => {
        cache.set(key, items);

        if (cache.size > 40) {
            cache.delete(cache.keys().next().value);
        }
    };

    const renderHits = (items, emptyCopy) => {
        if (! Array.isArray(items) || items.length === 0) {
            dropdown.innerHTML = `
                <div class="fd-empty">
                    <p>${escapeHtml(emptyCopy || 'No encontramos ese producto en inventario.')}</p>
                    <a class="fd-btn fd-btn--primary" href="${escapeHtml(config.whatsappUrl || '#')}" target="_blank" rel="noopener">Pedir por WhatsApp</a>
                </div>
            `;
            openDropdown();

            return;
        }

        dropdown.innerHTML = items.map((item) => `
            <article class="fd-search-hit" tabindex="0" data-product='${escapeHtml(JSON.stringify(item))}'>
                <img src="${escapeHtml(item.image_url)}" alt="">
                <div>
                    <strong>${escapeHtml(item.name)}</strong>
                    <div class="fd-product-card__meta">${escapeHtml(item.brand)} · ${money(item.effective_price)}</div>
                </div>
                <button type="button" class="fd-add" data-add-to-cart aria-label="Agregar">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M6 6h15l-1.5 9h-12z"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                </button>
            </article>
        `).join('');
        openDropdown();
    };

    const run = async ({ query = '', categoryId = 0, offers = false } = {}) => {
        if (! endpoint) {
            return;
        }

        const key = cacheKey(query, categoryId, offers);
        const emptyCopy = categoryId > 0 || offers
            ? 'No hay productos disponibles en esta categoría ahora.'
            : 'No encontramos ese producto en inventario.';

        if (cache.has(key)) {
            renderHits(cache.get(key), emptyCopy);

            return;
        }

        abortController?.abort();
        abortController = new AbortController();
        dropdown.innerHTML = '<div class="fd-empty"><p>Buscando…</p></div>';
        openDropdown();

        const params = new URLSearchParams();

        if (query !== '') {
            params.set('q', query);
        }

        if (categoryId > 0) {
            params.set('category_id', String(categoryId));
        }

        if (offers) {
            params.set('ofertas', '1');
        }

        try {
            const response = await fetch(`${endpoint}?${params.toString()}`, {
                headers: { Accept: 'application/json' },
                signal: abortController.signal,
            });

            if (! response.ok) {
                throw new Error('search failed');
            }

            const payload = await response.json();
            const items = payload.data || [];
            remember(key, items);
            renderHits(items, emptyCopy);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            dropdown.innerHTML = '<div class="fd-empty">No pudimos buscar ahora. Intenta de nuevo.</div>';
            openDropdown();
        }
    };

    const searchFromInput = () => {
        const query = input.value.trim();
        window.clearTimeout(timer);
        activeCategoryId = 0;
        activeOffers = false;

        if (query.length < 2) {
            abortController?.abort();
            closeDropdown();

            return;
        }

        timer = window.setTimeout(() => run({ query }), 180);
    };

    input.addEventListener('input', searchFromInput);

    input.addEventListener('focus', () => {
        if (dropdown.innerHTML.trim() !== '') {
            openDropdown();
        }
    });

    document.addEventListener('click', (event) => {
        if (! event.target.closest('.fd-search')) {
            closeDropdown();
        }
    });

    els.runStorefrontSearch = run;
    els.searchFromInput = searchFromInput;
    els.setSearchContext = ({ categoryId = 0, offers = false } = {}) => {
        activeCategoryId = categoryId;
        activeOffers = offers;
    };
};

const bindReveal = () => {
    const nodes = document.querySelectorAll('.fd-scroll-reveal');

    if (! ('IntersectionObserver' in window)) {
        nodes.forEach((node) => node.classList.add('is-visible'));

        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.14 });

    nodes.forEach((node) => observer.observe(node));
};

const bindSpotlight = () => {
    document.querySelectorAll('.fd-product-card, .fd-cat, .fd-hero').forEach((card) => {
        card.addEventListener('pointermove', (event) => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mx', `${event.clientX - rect.left}px`);
            card.style.setProperty('--my', `${event.clientY - rect.top}px`);
        });
    });
};

document.addEventListener('click', (event) => {
    const quickViewQtyBtn = event.target.closest('[data-qv-minus], [data-qv-plus]');

    if (quickViewQtyBtn) {
        event.preventDefault();
        stepQuickViewQty(quickViewQtyBtn.hasAttribute('data-qv-plus') ? 1 : -1);

        return;
    }

    const addBtn = event.target.closest('[data-add-to-cart]');

    if (addBtn) {
        event.preventDefault();
        event.stopPropagation();
        const product = productFromNode(addBtn);
        const fromQuickView = addBtn.hasAttribute('data-add-from-modal');
        const img = fromQuickView
            ? document.querySelector('[data-modal="product"] [data-qv-image]')
            : addBtn.closest('[data-product]')?.querySelector('img');

        addToCart(product, fromQuickView ? quickView.qty : 1, img);

        if (fromQuickView) {
            closeAllLayers();
        }

        return;
    }

    const cartBtn = event.target.closest('[data-cart-button]');

    if (cartBtn) {
        openDrawer();

        return;
    }

    const closeCart = event.target.closest('[data-close-cart]');

    if (closeCart) {
        closeAllLayers();

        return;
    }

    const qtyBtn = event.target.closest('[data-qty-delta]');

    if (qtyBtn) {
        const id = Number(qtyBtn.closest('[data-line-id]')?.dataset.lineId);
        const delta = Number(qtyBtn.dataset.qtyDelta);
        cart = cart
            .map((line) => Number(line.id) === id ? { ...line, qty: Number(line.qty) + delta } : line)
            .filter((line) => Number(line.qty) > 0);
        saveAndRender();

        return;
    }

    const removeBtn = event.target.closest('[data-remove-line]');

    if (removeBtn) {
        const id = Number(removeBtn.closest('[data-line-id]')?.dataset.lineId);
        cart = cart.filter((line) => Number(line.id) !== id);
        saveAndRender();
        toast('Producto quitado del carrito');

        return;
    }

    const checkout = event.target.closest('[data-checkout-pay]');

    if (checkout) {
        startCheckout();

        return;
    }

    const openModalBtn = event.target.closest('[data-open-modal]');

    if (openModalBtn) {
        openModal(openModalBtn.dataset.openModal);

        return;
    }

    const closeModal = event.target.closest('[data-close-modal], [data-close-nav]');

    if (closeModal) {
        closeAllLayers();

        return;
    }

    const burger = event.target.closest('[data-open-nav]');

    if (burger) {
        openMobileNav();

        return;
    }

    const category = event.target.closest('[data-category-search]');

    if (category) {
        const term = category.dataset.categorySearch || '';
        const categoryId = Number(category.dataset.categoryId || 0);
        const offers = category.dataset.categoryOffers === '1';
        const input = els.searchInput;

        if (input && (term || categoryId > 0 || offers)) {
            input.value = term;
            input.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
            els.setSearchContext?.({ categoryId, offers });
            els.runStorefrontSearch?.({
                query: categoryId > 0 || offers ? '' : term,
                categoryId,
                offers,
            });
        }

        return;
    }

    const card = event.target.closest('[data-product-card]');

    if (card && card.closest('[data-carousel-track]')?.dataset.suppressClick !== '1') {
        const product = productFromNode(card);
        fillQuickView(product);
        openModal('product');
    }
});

els.overlay?.addEventListener('click', closeAllLayers);

document.querySelectorAll('[data-modal], [data-mobile-nav]').forEach((layer) => {
    layer.addEventListener('click', (event) => {
        if (event.target === layer) {
            closeAllLayers();
        }
    });
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAllLayers();
    }
});

document.querySelector('[data-track-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const code = String(new FormData(event.currentTarget).get('code') || '').trim();
    const base = String(config.whatsappUrl || 'https://wa.me/584127018390');
    const message = code
        ? `Hola Farmadoc, quiero el estatus de mi pedido ${code}.`
        : 'Hola Farmadoc, quiero el estatus de mi pedido.';
    window.open(`${base}?text=${encodeURIComponent(message)}`, '_blank', 'noopener');
});

document.querySelector('[data-rx-form]')?.addEventListener('submit', (event) => {
    event.preventDefault();
    const note = String(new FormData(event.currentTarget).get('note') || '').trim();
    const base = String(config.whatsappUrl || 'https://wa.me/584127018390');
    const message = [
        'Hola Farmadoc, quiero cotizar una receta.',
        note ? `Detalle: ${note}` : 'Adjunto la receta por este chat.',
    ].join('\n');
    window.open(`${base}?text=${encodeURIComponent(message)}`, '_blank', 'noopener');
});

bindTheme();
bindHeader();
bindCarousels();
bindSearch();
bindReveal();
bindSpotlight();
renderCart();
setLayerOpen(els.drawer, false);
setLayerOpen(els.mobileNav, false);

window.addEventListener('resize', () => {
    if (window.matchMedia('(min-width: 761px)').matches && els.mobileNav?.classList.contains('is-open')) {
        closeAllLayers();
    }
});

document.querySelectorAll('.fd-nav a').forEach((link) => {
    link.addEventListener('click', () => {
        document.querySelectorAll('.fd-nav a').forEach((item) => item.classList.remove('is-active'));
        link.classList.add('is-active');
    });
});

document.querySelector('.fd-search__submit')?.addEventListener('click', () => {
    els.searchInput?.dispatchEvent(new Event('input', { bubbles: true }));
    els.searchInput?.focus();
});
