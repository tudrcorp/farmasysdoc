document.addEventListener('alpine:init', () => {
    const alpine = window.Alpine;

    alpine.store('epTheme', {
        dark: document.documentElement.getAttribute('data-theme') === 'dark',
        init() {
            document.addEventListener('ep-theme-changed', (event) => {
                this.dark = Boolean(event.detail?.dark);
            });
        },
        toggle() {
            window.epTheme?.toggle();
            this.dark = Boolean(window.epTheme?.isDark());
        },
    });

    /**
     * Avisos del portal: número en la campana y en el ícono de la PWA (Badging API).
     * Una consulta liviana cada 60 s, solo con la pantalla visible.
     */
    alpine.store('epNotifications', {
        count: 0,
        endpoint: null,
        timer: null,
        lastFetchAt: 0,
        init() {
            this.readFromPage();

            document.addEventListener('livewire:navigated', () => this.readFromPage());
            window.addEventListener('ep-notifications-read', () => this.set(0));
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible' && Date.now() - this.lastFetchAt > 15000) {
                    this.refresh();
                }
            });

            this.timer = window.setInterval(() => {
                if (document.visibilityState === 'visible') {
                    this.refresh();
                }
            }, 60000);
        },
        readFromPage() {
            const source = document.querySelector('[data-ep-unread-count]');
            this.endpoint = source?.dataset.endpoint ?? null;
            this.set(source ? Number(source.dataset.epUnreadCount) || 0 : 0);
        },
        async refresh() {
            if (! this.endpoint) {
                return;
            }

            this.lastFetchAt = Date.now();

            try {
                const response = await fetch(this.endpoint, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (response.ok) {
                    const data = await response.json();
                    this.set(Number(data.count) || 0);
                }
            } catch {
                // Sin red: se conserva el último valor.
            }
        },
        set(count) {
            this.count = count;

            if (! ('setAppBadge' in navigator)) {
                return;
            }

            const result = count > 0 ? navigator.setAppBadge(count) : navigator.clearAppBadge();
            result?.catch?.(() => undefined);
        },
        canAskPermission() {
            return 'setAppBadge' in navigator
                && 'Notification' in window
                && Notification.permission === 'default'
                && window.matchMedia('(display-mode: standalone)').matches;
        },
        askPermission() {
            if (! ('Notification' in window)) {
                return Promise.resolve();
            }

            return Notification.requestPermission()
                .then(() => this.set(this.count))
                .catch(() => undefined);
        },
    });

    /**
     * Selector de rango del portal, con la misma interacción que el del dashboard:
     * primer toque = "desde", segundo = "hasta". Respeta fechas mínima/máxima y el máximo de días.
     */
    alpine.data('employeeDateRangePicker', (config = {}) => ({
        open: false,
        today: config.today,
        min: config.min,
        max: config.max,
        maxDays: Number(config.maxDays) || 90,
        businessDays: Boolean(config.businessDays),
        rightStartsOn: config.rightStartsOn ?? null,
        entitledDays: config.entitledDays ?? null,
        from: config.from || null,
        to: config.to || null,
        draftStart: null,
        draftEnd: null,
        hoverDate: null,
        viewYear: 0,
        viewMonth: 0,
        monthsToShow: 2,
        weekdays: ['lu', 'ma', 'mi', 'ju', 'vi', 'sá', 'do'],

        get selecting() {
            return this.draftStart !== null && this.draftEnd === null;
        },

        get canGoPrev() {
            const min = this.parse(this.min);

            return this.viewYear * 12 + this.viewMonth > min.getFullYear() * 12 + min.getMonth();
        },

        get canGoNext() {
            const max = this.parse(this.max);

            return this.viewYear * 12 + this.viewMonth + this.monthsToShow - 1 < max.getFullYear() * 12 + max.getMonth();
        },

        get months() {
            const months = [];

            for (let offset = 0; offset < this.monthsToShow; offset++) {
                months.push(this.buildMonth(new Date(this.viewYear, this.viewMonth + offset, 1)));
            }

            return months;
        },

        get draftLabel() {
            if (this.selecting) {
                return `${this.formatDay(this.draftStart)} – …`;
            }

            return this.draftStart && this.draftEnd ? this.formatRange(this.draftStart, this.draftEnd) : '';
        },

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            this.monthsToShow = window.matchMedia('(min-width: 40rem)').matches ? 2 : 1;
            this.draftStart = this.from;
            this.draftEnd = this.to;
            this.hoverDate = null;

            const anchor = this.parse(this.from || (this.today < this.min ? this.min : this.today));

            this.viewYear = anchor.getFullYear();
            this.viewMonth = anchor.getMonth();
            this.open = true;
        },

        close() {
            this.open = false;
            this.hoverDate = null;
        },

        shiftMonths(step) {
            if ((step > 0 && ! this.canGoNext) || (step < 0 && ! this.canGoPrev)) {
                return;
            }

            const first = new Date(this.viewYear, this.viewMonth + step, 1);
            this.viewYear = first.getFullYear();
            this.viewMonth = first.getMonth();
        },

        buildMonth(firstDay) {
            const monthName = new Intl.DateTimeFormat('es', { month: 'long' }).format(firstDay);
            const leadingBlanks = (firstDay.getDay() + 6) % 7;
            const daysInMonth = new Date(firstDay.getFullYear(), firstDay.getMonth() + 1, 0).getDate();
            const monthKey = this.key(firstDay).slice(0, 7);
            const cells = [];

            for (let blank = 0; blank < leadingBlanks; blank++) {
                cells.push({ key: `${monthKey}-blank-${blank}`, date: null, day: '', ariaLabel: null });
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(firstDay.getFullYear(), firstDay.getMonth(), day);

                cells.push({
                    key: this.key(date),
                    date: this.key(date),
                    day,
                    weekend: date.getDay() === 0 || date.getDay() === 6,
                    ariaLabel: new Intl.DateTimeFormat('es', { dateStyle: 'full' }).format(date),
                });
            }

            return {
                key: monthKey,
                label: `${monthName.charAt(0).toUpperCase()}${monthName.slice(1)} ${firstDay.getFullYear()}`,
                cells,
            };
        },

        isDisabled(date) {
            if (date < this.min || date > this.max) {
                return true;
            }

            return this.selecting && Math.abs(this.diffDays(this.draftStart, date)) >= this.maxDays;
        },

        visibleRange() {
            if (this.selecting) {
                const edge = this.hoverDate && ! this.isDisabled(this.hoverDate) ? this.hoverDate : this.draftStart;

                return edge < this.draftStart ? [edge, this.draftStart] : [this.draftStart, edge];
            }

            return [this.draftStart, this.draftEnd];
        },

        dayClasses(cell) {
            if (cell.date === null) {
                return 'is-empty';
            }

            const [start, end] = this.visibleRange();
            const isStart = start !== null && cell.date === start;
            const isEnd = end !== null && cell.date === end;

            return {
                'is-start': isStart,
                'is-end': isEnd,
                'is-single': isStart && isEnd,
                'is-in-range': start !== null && end !== null && cell.date > start && cell.date < end,
                'is-today': cell.date === this.today,
                'is-weekend': this.businessDays && cell.weekend,
            };
        },

        selectDay(date) {
            if (date === null || this.isDisabled(date)) {
                return;
            }

            if (! this.selecting) {
                this.draftStart = date;
                this.draftEnd = null;

                return;
            }

            const [from, to] = date < this.draftStart ? [date, this.draftStart] : [this.draftStart, date];
            this.applyRange(from, to);
        },

        applyRange(from, to) {
            this.from = from;
            this.to = to;
            this.draftStart = from;
            this.draftEnd = to;
            this.close();

            this.$wire.$set('startsOn', from, false);
            this.$wire.$set('endsOn', to, false);
        },

        count(from, to) {
            if (! from || ! to) {
                return 0;
            }

            if (! this.businessDays) {
                return this.diffDays(from, to) + 1;
            }

            let total = 0;
            const cursor = this.parse(from);
            const end = this.parse(to);

            while (cursor <= end) {
                const weekday = cursor.getDay();

                if (weekday !== 0 && weekday !== 6) {
                    total++;
                }

                cursor.setDate(cursor.getDate() + 1);
            }

            return total;
        },

        countLabel(from, to) {
            const total = this.count(from, to);
            const unit = this.businessDays ? (total === 1 ? 'día hábil' : 'días hábiles') : (total === 1 ? 'día' : 'días');

            return `${total} ${unit}`;
        },

        isEarly() {
            return Boolean(this.rightStartsOn && this.from && this.from < this.rightStartsOn);
        },

        exceedsEntitlement() {
            return Boolean(this.entitledDays && this.from && this.count(this.from, this.to) > this.entitledDays);
        },

        parse(dateKey) {
            const [year, month, day] = String(dateKey).split('-').map(Number);

            return new Date(year, month - 1, day);
        },

        key(date) {
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');

            return `${date.getFullYear()}-${month}-${day}`;
        },

        diffDays(from, to) {
            return Math.round((this.parse(to) - this.parse(from)) / 86400000);
        },

        shortMonth(date) {
            return new Intl.DateTimeFormat('es', { month: 'short' }).format(date).replace('.', '');
        },

        formatDay(dateKey) {
            if (! dateKey) {
                return '';
            }

            const date = this.parse(dateKey);

            return `${date.getDate()} ${this.shortMonth(date)} ${date.getFullYear()}`;
        },

        formatRange(from, to) {
            if (! to || from === to) {
                return this.formatDay(from);
            }

            const start = this.parse(from);
            const end = this.parse(to);

            if (start.getFullYear() === end.getFullYear() && start.getMonth() === end.getMonth()) {
                return `${start.getDate()} – ${end.getDate()} ${this.shortMonth(end)} ${end.getFullYear()}`;
            }

            if (start.getFullYear() === end.getFullYear()) {
                return `${start.getDate()} ${this.shortMonth(start)} – ${end.getDate()} ${this.shortMonth(end)} ${end.getFullYear()}`;
            }

            return `${this.formatDay(from)} – ${this.formatDay(to)}`;
        },
    }));

    alpine.data('employeePortalMenu', () => ({
        menuOpen: false,
        dragging: false,
        dragY: 0,
        startY: 0,
        lastFocused: null,
        init() {
            this._onNavigate = () => this.close();
            document.addEventListener('livewire:navigating', this._onNavigate);
        },
        open() {
            if (this.menuOpen) {
                return;
            }
            this.lastFocused = document.activeElement;
            this.menuOpen = true;
            this.lockScroll();
            this.$nextTick(() => {
                this.$refs.menuSheet?.focus({ preventScroll: true });
            });
        },
        close() {
            if (! this.menuOpen) {
                return;
            }
            this.menuOpen = false;
            this.dragging = false;
            this.dragY = 0;
            this.unlockScroll();
            this.$nextTick(() => {
                this.lastFocused?.focus?.({ preventScroll: true });
            });
        },
        toggle() {
            this.menuOpen ? this.close() : this.open();
        },
        lockScroll() {
            const gap = window.innerWidth - document.documentElement.clientWidth;
            document.body.classList.add('ep-menu-locked');
            if (gap > 0) {
                document.body.style.paddingRight = `${gap}px`;
            }
        },
        unlockScroll() {
            document.body.classList.remove('ep-menu-locked');
            document.body.style.paddingRight = '';
        },
        sheetStyle() {
            if (! this.dragging && this.dragY === 0) {
                return {};
            }

            return {
                transform: `translate3d(0, ${this.dragY}px, 0)`,
                opacity: String(Math.max(0.4, 1 - this.dragY / 420)),
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
            const shouldClose = this.dragY > 72;
            this.dragging = false;
            this.dragY = 0;
            if (shouldClose) {
                this.close();
            }
        },
        trapTab(event) {
            if (! this.menuOpen || ! this.$refs.menuSheet) {
                return;
            }
            const nodes = [...this.$refs.menuSheet.querySelectorAll('a[href], button:not([disabled]):not(.ep-menu-grab)')];
            if (nodes.length === 0) {
                return;
            }
            const first = nodes[0];
            const last = nodes[nodes.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (! event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },
        destroy() {
            document.removeEventListener('livewire:navigating', this._onNavigate);
            this.unlockScroll();
        },
    }));

    alpine.data('employeeSignaturePad', () => ({
        drawing: false,
        hasInk: false,
        ctx: null,
        last: null,
        init() {
            this.$nextTick(() => this.resize());
            this._onResize = () => this.resize();
            window.addEventListener('resize', this._onResize);
        },
        destroy() {
            window.removeEventListener('resize', this._onResize);
        },
        resize() {
            const canvas = this.$refs.pad;
            if (! canvas) {
                return;
            }
            const rect = canvas.getBoundingClientRect();
            const ratio = window.devicePixelRatio || 1;
            canvas.width = Math.max(1, Math.round(rect.width * ratio));
            canvas.height = Math.max(1, Math.round(rect.height * ratio));
            const ctx = canvas.getContext('2d');
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#0f172a';
            ctx.lineWidth = 2.6;
            this.ctx = ctx;
            this.hasInk = false;
        },
        pos(event) {
            const rect = this.$refs.pad.getBoundingClientRect();

            return { x: event.clientX - rect.left, y: event.clientY - rect.top };
        },
        start(event) {
            this.drawing = true;
            this.last = this.pos(event);
            this.$refs.pad.setPointerCapture?.(event.pointerId);
        },
        move(event) {
            if (! this.drawing || ! this.ctx) {
                return;
            }
            const point = this.pos(event);
            this.ctx.beginPath();
            this.ctx.moveTo(this.last.x, this.last.y);
            this.ctx.lineTo(point.x, point.y);
            this.ctx.stroke();
            this.last = point;
            this.hasInk = true;
        },
        end() {
            this.drawing = false;
        },
        clear() {
            const canvas = this.$refs.pad;
            if (! canvas || ! this.ctx) {
                return;
            }
            this.ctx.clearRect(0, 0, canvas.width, canvas.height);
            this.hasInk = false;
        },
        submit() {
            if (! this.hasInk) {
                return;
            }
            const canvas = this.$refs.pad;
            const rect = canvas.getBoundingClientRect();
            const exportCanvas = document.createElement('canvas');
            exportCanvas.width = Math.round(rect.width * 2);
            exportCanvas.height = Math.round(rect.height * 2);
            const context = exportCanvas.getContext('2d');
            context.clearRect(0, 0, exportCanvas.width, exportCanvas.height);
            context.drawImage(canvas, 0, 0, exportCanvas.width, exportCanvas.height);
            this.$wire.saveSignatureStroke(exportCanvas.toDataURL('image/png'));
        },
    }));

    alpine.data('employeeFingerprintCamera', () => ({
        stream: null,
        facing: 'environment',
        preview: null,
        error: null,
        init() {
            this.start();
        },
        destroy() {
            this.stop();
        },
        async start() {
            if (! navigator.mediaDevices?.getUserMedia) {
                this.error = 'Este dispositivo no permite la cámara. Sube una foto.';

                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: {
                        facingMode: this.facing,
                        width: { ideal: 1280 },
                        height: { ideal: 1280 },
                    },
                    audio: false,
                });
                this.error = null;
                this.$nextTick(() => {
                    if (this.$refs.video) {
                        this.$refs.video.srcObject = this.stream;
                    }
                });
            } catch {
                this.error = 'No pudimos abrir la cámara. Puedes subir una foto.';
            }
        },
        stop() {
            this.stream?.getTracks().forEach((track) => track.stop());
            this.stream = null;
        },
        async flip() {
            this.facing = this.facing === 'environment' ? 'user' : 'environment';
            this.stop();
            await this.start();
        },
        capture() {
            const video = this.$refs.video;
            if (! video || ! video.videoWidth) {
                this.error = 'Espera un segundo a que la cámara enfoque.';

                return;
            }
            const size = Math.min(video.videoWidth, video.videoHeight);
            const canvas = document.createElement('canvas');
            canvas.width = 900;
            canvas.height = 900;
            const context = canvas.getContext('2d');
            context.drawImage(
                video,
                (video.videoWidth - size) / 2,
                (video.videoHeight - size) / 2,
                size,
                size,
                0,
                0,
                900,
                900,
            );
            this.preview = canvas.toDataURL('image/png');
        },
        retake() {
            this.preview = null;
            this.$nextTick(() => {
                if (this.$refs.video && this.stream) {
                    this.$refs.video.srcObject = this.stream;
                }
            });
        },
        use() {
            if (! this.preview) {
                return;
            }
            this.stop();
            this.$wire.saveFingerprintCapture(this.preview);
        },
    }));
});

if ('serviceWorker' in navigator && window.location.pathname.startsWith('/portal')) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/portal-sw.js', { scope: '/portal' }).catch(() => undefined);
    });
}
