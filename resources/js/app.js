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

    alpine.data('employeePortalMenu', () => ({
        menuOpen: false,
        soonHint: false,
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
            this.soonHint = false;
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
        showSoon() {
            this.soonHint = true;
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
            context.fillStyle = '#ffffff';
            context.fillRect(0, 0, exportCanvas.width, exportCanvas.height);
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
            this.preview = canvas.toDataURL('image/jpeg', 0.86);
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
