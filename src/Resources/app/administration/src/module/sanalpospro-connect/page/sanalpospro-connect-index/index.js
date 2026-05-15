Shopware.Component.register('sanalpospro-connect-index', {
    template: `
        <sw-page class="sanalpospro-connect-index sanalpospro-single-scroll">
            <template #smart-bar-header>
                <h2>SanalPos Pro Management</h2>
            </template>
            <template #content>
                <div ref="reactContainer" class="sanalpospro-react-container"></div>
            </template>
        </sw-page>
    `,

    mounted() {
        // Inject CSS overrides to eliminate double scroll (make it single-scroll like OpenCart)
        if (!document.getElementById('sanalpospro-scroll-fix')) {
            const style = document.createElement('style');
            style.id = 'sanalpospro-scroll-fix';
            style.textContent = `
                /* --- SanalPosPro: Single-scroll fix (OpenCart-style) --- */
                .sanalpospro-single-scroll .sw-page__content {
                    overflow: visible !important;
                    overflow-y: visible !important;
                    height: auto !important;
                    max-height: none !important;
                    position: static !important;
                }
                .sanalpospro-single-scroll .sw-card-view {
                    overflow: visible !important;
                    height: auto !important;
                }
                .sanalpospro-single-scroll .sw-page__main-content {
                    overflow: visible !important;
                    height: auto !important;
                    max-height: none !important;
                }
                .sanalpospro-react-container {
                    width: 100%;
                    min-height: 800px;
                    padding: 0;
                }
                .sanalpospro-react-container #root {
                    width: 100%;
                    min-height: 800px;
                    background: transparent;
                }
            `;
            document.head.appendChild(style);
        }

        this._resolvedAppId = 106;
        this._fallbackAppIds = [];
        this._triedAppIds = new Set();
        this._recovering = false;
        this.installRuntimeRecovery();
        this.loadPayThorApp();
    },

    beforeDestroy() {
        if (this._runtimeErrorHandler) {
            window.removeEventListener('error', this._runtimeErrorHandler, true);
            this._runtimeErrorHandler = null;
        }

        if (this._runtimeRejectionHandler) {
            window.removeEventListener('unhandledrejection', this._runtimeRejectionHandler, true);
            this._runtimeRejectionHandler = null;
        }

        this.cleanupPayThorApp();
    },

    methods: {
        normalizeAppId(raw) {
            const parsed = Number.parseInt(raw, 10);
            if (!Number.isInteger(parsed) || parsed <= 0 || parsed > 1000) {
                return null;
            }

            return parsed;
        },

        buildAppCandidates(primary) {
            const candidates = [primary, 106, 103]
                .map(v => this.normalizeAppId(v))
                .filter(v => v !== null);

            return candidates.filter((v, idx) => candidates.indexOf(v) === idx);
        },

        installRuntimeRecovery() {
            if (this._runtimeErrorHandler || this._runtimeRejectionHandler) {
                return;
            }

            const scheduleFallbackReload = (nextAppId) => {
                this._recovering = true;
                console.warn('SanalPosPro: runtime id crash detected, retrying with fallback app_id', nextAppId);

                setTimeout(async () => {
                    try {
                        await this.loadPayThorApp(nextAppId);
                    } catch (e) {
                        console.error('SanalPosPro: fallback reload failed', e);
                    } finally {
                        this._recovering = false;
                    }
                }, 50);
            };

            const tryRecoverByMessage = (message, event) => {
                const isIdCrash = message.includes("reading 'id'") || message.includes('reading "id"');
                if (!isIdCrash || this._recovering) {
                    return;
                }

                const nextAppId = Array.isArray(this._fallbackAppIds) ? this._fallbackAppIds.shift() : null;
                if (!nextAppId) {
                    return;
                }

                if (typeof event?.preventDefault === 'function') {
                    event.preventDefault();
                }

                scheduleFallbackReload(nextAppId);
            };

            this._runtimeErrorHandler = (event) => {
                const message = String(event?.message || event?.error?.message || '');
                tryRecoverByMessage(message, event);
            };

            this._runtimeRejectionHandler = (event) => {
                const reason = event?.reason;
                const message = String(reason?.message || reason?.toString?.() || reason || '');
                tryRecoverByMessage(message, event);
            };

            window.addEventListener('error', this._runtimeErrorHandler, true);
            window.addEventListener('unhandledrejection', this._runtimeRejectionHandler, true);
        },

        async loadPayThorApp(overrideAppId = null) {
            this.cleanupPayThorApp();

            this._createdRoot = !document.getElementById('root');
            if (this._createdRoot) {
                const div = document.createElement('div');
                div.id = 'root';

                if (this.$refs.reactContainer) {
                    this.$refs.reactContainer.appendChild(div);
                } else {
                    // Fallback: attach to body if Vue ref is unavailable
                    div.style.cssText = 'position:fixed;top:130px;left:240px;right:0;bottom:0;z-index:10;background:#fff;overflow:auto;';
                    document.body.appendChild(div);
                }
            }

            let xfvv = 'shopware';
            let targetPath = '/sanalpospro/iapi/index';
            let resolvedAppId = this.normalizeAppId(this._resolvedAppId) || 106;
            let savedSettings = {
                order_status: 'process',
                currency_convert: 'no',
                showInstallmentsTabs: 'no',
                paymentPageTheme: 'modern',
            };

            try {
                const token = Shopware.Context.api.authToken && Shopware.Context.api.authToken.access;
                if (token) {
                    const response = await fetch('/api/sanalpospro/admin-config', {
                        headers: {
                            'Authorization': 'Bearer ' + token,
                            'Accept': 'application/json',
                        },
                    });

                    if (response.ok) {
                        const cfg = await response.json();
                        xfvv = cfg.xfvv || xfvv;
                        targetPath = cfg.target_url || targetPath;
                        const cfgAppId = this.normalizeAppId(cfg.app_id);
                        if (cfgAppId !== null) {
                            resolvedAppId = cfgAppId;
                        }
                        if (cfg.module_settings && typeof cfg.module_settings === 'object') {
                            savedSettings = Object.assign(savedSettings, cfg.module_settings);
                        }
                    } else {
                        console.error('SanalPosPro: Failed to fetch admin config', response.status);
                    }
                } else {
                    console.warn('SanalPosPro: no admin auth token available');
                }
            } catch (e) {
                console.error('SanalPosPro: Error fetching admin config', e);
            }

            const selectedAppId = this.normalizeAppId(overrideAppId) || resolvedAppId;
            const appCandidates = this.buildAppCandidates(selectedAppId);

            this._triedAppIds.add(selectedAppId);
            this._fallbackAppIds = appCandidates.filter(id => !this._triedAppIds.has(id));

            try {
                const forcedAppId = String(selectedAppId || 106);
                const markerKey = 'paythor-connect-app-id';
                const staleKeys = [
                    'etc-token', 'etc-user-level', 'etc-is-impersonating',
                    'etc-original-admin-token', 'etc-impersonate-token',
                    'paythor-merchant-app',
                ];

                // This key may contain an installed app record id from a previous
                // account, which breaks app matching during re-login flows.
                localStorage.removeItem('paythor-merchant-app');

                if (localStorage.getItem(markerKey) !== forcedAppId) {
                    staleKeys.forEach(k => localStorage.removeItem(k));
                    sessionStorage.clear();
                    localStorage.setItem(markerKey, forcedAppId);
                }
            } catch (e) {
                console.warn('SanalPosPro: LocalStorage access denied', e);
            }

            this._resolvedAppId = selectedAppId;
            this._currentAppId = selectedAppId;
            const CDN_BASE = `https://cdn.paythor.com/1/${selectedAppId}/10.0.4`;

            window.xfvv = xfvv;
            window.target_url = window.location.origin + targetPath;
            window.store_url = window.location.origin;
            window.app_id = selectedAppId;
            window.platform = 'shopware';
            window.program_id = 1;
            window.style_url = `${CDN_BASE}/index.css`;

            window.generalSettings = {
                order_status: { default_value: savedSettings.order_status, options: { process: 'Processing' } },
                currency_convert: { default_value: savedSettings.currency_convert, options: { yes: 'Yes', no: 'No' } },
                showInstallmentsTabs: { default_value: savedSettings.showInstallmentsTabs, options: { yes: 'Yes', no: 'No' } },
                paymentPageTheme: { default_value: savedSettings.paymentPageTheme, options: { classic: 'Classic', modern: 'Modern' } },
            };

            const link = document.createElement('link');
            link.id = 'paythor-style';
            link.rel = 'stylesheet';
            link.href = window.style_url;
            document.head.appendChild(link);

            const script = document.createElement('script');
            script.id = 'paythor-script';
            script.type = 'module';
            script.src = `${CDN_BASE}/index.js?v=` + Date.now();
            script.onerror = () => console.error('[SanalPosPro] CDN script failed to load:', script.src);
            document.body.appendChild(script);
        },

        cleanupPayThorApp() {
            const script = document.getElementById('paythor-script');
            if (script) script.remove();

            const style = document.getElementById('paythor-style');
            if (style) style.remove();

            const scrollFix = document.getElementById('sanalpospro-scroll-fix');
            if (scrollFix) scrollFix.remove();

            if (this._createdRoot) {
                const root = document.getElementById('root');
                if (root) root.remove();
                this._createdRoot = false;
            } else {
                const rootNode = document.getElementById('root');
                if (rootNode) rootNode.innerHTML = '';
            }
        },
    },
});
