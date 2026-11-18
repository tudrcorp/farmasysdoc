<script>
    (() => {
        if (window.farmadocNotificationSound) {
            return;
        }

        const MUTE_KEY = 'farmadoc.notifications.muted';

        const storage = {
            get(key) {
                try {
                    return window.localStorage.getItem(key);
                } catch (error) {
                    return null;
                }
            },
            set(key, value) {
                try {
                    window.localStorage.setItem(key, value);
                } catch (error) {
                    // Almacenamiento no disponible (modo privado): se ignora.
                }
            },
        };

        const sound = {
            context: null,
            unlock() {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;

                if (! AudioContextClass) {
                    return;
                }

                if (! sound.context) {
                    sound.context = new AudioContextClass();
                }

                if (sound.context.state === 'suspended') {
                    sound.context.resume().catch(() => {});
                }
            },
            isMuted() {
                return storage.get(MUTE_KEY) === '1';
            },
            setMuted(muted) {
                storage.set(MUTE_KEY, muted ? '1' : '0');
                window.dispatchEvent(new CustomEvent('farmadoc-notifications-muted', { detail: { muted } }));
            },
            play() {
                if (sound.isMuted() || ! sound.context || sound.context.state !== 'running') {
                    return;
                }

                const context = sound.context;
                const start = context.currentTime;

                [[880, 0], [1318.5, 0.16]].forEach(([frequency, offset]) => {
                    const oscillator = context.createOscillator();
                    const gain = context.createGain();

                    oscillator.type = 'sine';
                    oscillator.frequency.value = frequency;
                    gain.gain.setValueAtTime(0.0001, start + offset);
                    gain.gain.exponentialRampToValueAtTime(0.35, start + offset + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, start + offset + 0.45);

                    oscillator.connect(gain);
                    gain.connect(context.destination);
                    oscillator.start(start + offset);
                    oscillator.stop(start + offset + 0.5);
                });
            },
            vibrate() {
                if (sound.isMuted() || typeof navigator.vibrate !== 'function') {
                    return;
                }

                try {
                    navigator.vibrate([180, 90, 180]);
                } catch (error) {
                    // El navegador bloqueó la vibración.
                }
            },
        };

        ['pointerdown', 'keydown', 'touchstart'].forEach((eventName) => {
            document.addEventListener(eventName, sound.unlock, { capture: true, passive: true });
        });

        window.farmadocNotificationSound = sound;

        document.addEventListener('alpine:init', () => {
            window.Alpine.data('farmadocNotificationBell', (userKey) => ({
                count: 0,
                observer: null,
                storageKey: 'farmadoc.notifications.lastUnread.' + userKey,

                init() {
                    this.count = this.readCount();

                    const previous = parseInt(storage.get(this.storageKey) ?? '', 10);

                    if (! Number.isNaN(previous) && this.count > previous) {
                        this.$nextTick(() => this.alert());
                    }

                    storage.set(this.storageKey, String(this.count));

                    this.observer = new MutationObserver(() => {
                        const next = this.readCount();

                        if (next > this.count) {
                            this.alert();
                        }

                        this.count = next;
                        storage.set(this.storageKey, String(next));
                    });

                    this.observer.observe(this.$el, { attributes: true, attributeFilter: ['data-unread-count'] });
                },

                destroy() {
                    this.observer?.disconnect();
                },

                readCount() {
                    return parseInt(this.$el.dataset.unreadCount ?? '0', 10) || 0;
                },

                alert() {
                    this.$el.classList.remove('farmadoc-bell--alert');
                    void this.$el.offsetWidth;
                    this.$el.classList.add('farmadoc-bell--alert');
                    window.setTimeout(() => this.$el.classList.remove('farmadoc-bell--alert'), 2800);

                    sound.play();
                    sound.vibrate();
                },
            }));

            window.Alpine.data('farmadocNotificationMute', () => ({
                muted: sound.isMuted(),

                init() {
                    window.addEventListener('farmadoc-notifications-muted', (event) => {
                        this.muted = Boolean(event.detail?.muted);
                    });
                },

                toggle() {
                    sound.unlock();
                    sound.setMuted(! this.muted);

                    if (! this.muted) {
                        window.setTimeout(() => sound.play(), 60);
                    }
                },
            }));
        });
    })();
</script>
