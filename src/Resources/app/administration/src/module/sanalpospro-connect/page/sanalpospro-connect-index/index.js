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

        let resolvedAppId = 106;
        try {
            const stored = localStorage.getItem('paythor-merchant-app');
            if (stored && !isNaN(parseInt(stored))) {
                resolvedAppId = parseInt(stored);
            }
        } catch (e) { }

        this._resolvedAppId = resolvedAppId;
        this.loadPayThorApp();
    },

    beforeDestroy() {
        this.cleanupPayThorApp();
    },

    methods: {
        async loadPayThorApp() {
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

            try {
                const forcedAppId = String(this._resolvedAppId || 106);
                const markerKey = 'paythor-connect-app-id';
                const staleKeys = [
                    'etc-token', 'etc-user-level', 'etc-is-impersonating',
                    'etc-original-admin-token', 'etc-impersonate-token',
                ];
                if (localStorage.getItem(markerKey) !== forcedAppId) {
                    staleKeys.forEach(k => localStorage.removeItem(k));
                    sessionStorage.clear();
                    localStorage.setItem(markerKey, forcedAppId);
                }
            } catch (e) {
                console.warn('SanalPosPro: LocalStorage access denied', e);
            }

            let xfvv = 'shopware';
            let targetPath = '/sanalpospro/iapi/index';
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

            const resolvedAppId = this._resolvedAppId || 106;
            const CDN_BASE = `https://cdn.paythor.com/1/${resolvedAppId}/10.0.4`;

            window.xfvv = xfvv;
            window.target_url = window.location.origin + targetPath;
            window.store_url = window.location.origin;
            window.app_id = resolvedAppId;
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
