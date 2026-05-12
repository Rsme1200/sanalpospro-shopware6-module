Shopware.Component.register('sanalpospro-connect-index', {
    template: `
        <sw-page class="sanalpospro-connect-index">
            <template #smart-bar-header>
                <h2>SanalPos Pro Management</h2>
            </template>
            <template #content>
                <sw-card-view></sw-card-view>
            </template>
        </sw-page>
    `,

    mounted() {
        let resolvedAppId = 106;
        try {
            const stored = localStorage.getItem('paythor-merchant-app');
            if (stored && !isNaN(parseInt(stored))) {
                resolvedAppId = parseInt(stored);
            }
        } catch (e) {}

        this._resolvedAppId = resolvedAppId;
        this.loadPayThorApp();
    },

    beforeDestroy() {
        this.cleanupPayThorApp();
    },

    methods: {
        loadPayThorApp() {
            this.cleanupPayThorApp();

            // Create #root AFTER cleanup so the CDN can always find it.
            // Appended to body with fixed positioning to render over the Shopware admin layout.
            this._createdRoot = !document.getElementById('root');
            if (this._createdRoot) {
                const div = document.createElement('div');
                div.id = 'root';
                div.style.cssText = 'position:fixed;top:50px;left:220px;right:0;bottom:0;z-index:10;background:#fff;overflow:auto;';
                document.body.appendChild(div);
            }

            const resolvedAppId = this._resolvedAppId || 106;
            const CDN_BASE = `https://cdn.paythor.com/1/${resolvedAppId}/10.0.4`;

            window.xfvv       = 'shopware';
            window.target_url = window.location.origin + '/sanalpospro/iapi/index';
            window.store_url  = window.location.origin;
            window.app_id     = resolvedAppId;
            window.platform   = 'shopware';
            window.program_id = 1;
            window.style_url  = `${CDN_BASE}/index.css`;

            window.generalSettings = {
                order_status:         { default_value: 'process', options: { process: 'Processing' } },
                currency_convert:     { default_value: 'no',      options: { yes: 'Yes', no: 'No' } },
                showInstallmentsTabs: { default_value: 'no',      options: { yes: 'Yes', no: 'No' } },
                paymentPageTheme:     { default_value: 'modern',  options: { classic: 'Classic', modern: 'Modern' } },
            };

            // Clear stale session tokens if app_id changed since last load.
            try {
                const markerKey = 'paythor-connect-app-id';
                const forcedKey = String(resolvedAppId);
                if (localStorage.getItem(markerKey) !== forcedKey) {
                    ['etc-token', 'etc-user-level', 'etc-is-impersonating',
                     'etc-original-admin-token', 'etc-impersonate-token']
                        .forEach(k => localStorage.removeItem(k));
                    sessionStorage.clear();
                    localStorage.setItem(markerKey, forcedKey);
                }
            } catch (e) {
                console.warn('[SanalPosPro] localStorage access denied');
            }

            const link = document.createElement('link');
            link.id  = 'paythor-style';
            link.rel = 'stylesheet';
            link.href = window.style_url;
            document.head.appendChild(link);

            const script = document.createElement('script');
            script.id   = 'paythor-script';
            script.type = 'module';
            script.src  = `${CDN_BASE}/index.js?v=` + Date.now();
            script.onerror = () => console.error('[SanalPosPro] CDN script failed to load:', script.src);
            document.body.appendChild(script);
        },

        cleanupPayThorApp() {
            const script = document.getElementById('paythor-script');
            if (script) script.remove();

            const style = document.getElementById('paythor-style');
            if (style) style.remove();

            // Only remove #root if WE created it — never destroy a pre-existing host-page root.
            if (this._createdRoot) {
                const root = document.getElementById('root');
                if (root) root.remove();
                this._createdRoot = false;
            }
        },
    },
});
