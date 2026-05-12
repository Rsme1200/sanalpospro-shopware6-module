Shopware.Component.register('sanalpospro-connect-index', {
    template: `
        <sw-page class="sanalpospro-connect-index">
            <template #smart-bar-header>
                <h2>SanalPos Pro Management</h2>
            </template>
            <template #content>
                <sw-card-view>
                    <!-- PayThor React App Container -->
                    <div id="root" data-platform="shopware" data-app-id="106" data-program-id="1" style="min-height: 800px; width: 100%;"></div>
                </sw-card-view>
            </template>
        </sw-page>
    `,

    mounted() {
        this.loadPayThorApp();
    },

    beforeDestroy() {
        this.cleanupPayThorApp();
    },

    methods: {
        loadPayThorApp() {
            // First cleanup if any existing
            this.cleanupPayThorApp();

            // Setup Paythor environment variables
            window.xfvv = 'shopware-dummy-xfvv-token';
            window.target_url = window.location.origin.replace('/admin', '') + '/sanalpospro/iapi/index';
            window.store_url = window.location.origin;
            window.app_id = 106;
            window.platform = 'shopware';
            window.program_id = 1;
            window.style_url = 'https://cdn.paythor.com/1/106/10.0.4/index.css';

            window.generalSettings = {
                order_status: { default_value: 'process', options: { 'process': 'Processing' } },
                currency_convert: { default_value: 'no', options: { yes: 'Yes', no: 'No' } },
                showInstallmentsTabs: { default_value: 'no', options: { yes: 'Yes', no: 'No' } },
                paymentPageTheme: { default_value: 'modern', options: { classic: 'Classic', modern: 'Modern' } }
            };

            // Clean stale Paythor session data
            try {
                const forcedAppId = '106';
                const markerKey = 'paythor-connect-app-id';
                const staleKeys = [
                    'etc-token', 'etc-user-level', 'etc-is-impersonating',
                    'etc-original-admin-token', 'etc-impersonate-token'
                ];
                if (localStorage.getItem(markerKey) !== forcedAppId) {
                    staleKeys.forEach(k => localStorage.removeItem(k));
                    sessionStorage.clear();
                    localStorage.setItem(markerKey, forcedAppId);
                }
            } catch (e) {
                console.warn('LocalStorage access denied for Paythor');
            }

            // Inject CSS
            const link = document.createElement('link');
            link.id = 'paythor-style';
            link.rel = 'stylesheet';
            link.href = window.style_url;
            document.head.appendChild(link);

            // Inject JS Module with cache busting so it forces React to re-execute in SPA
            const script = document.createElement('script');
            script.id = 'paythor-script';
            script.type = 'module';
            script.src = 'https://cdn.paythor.com/1/106/10.0.4/index.js?v=' + Date.now();
            document.body.appendChild(script);
        },

        cleanupPayThorApp() {
            const script = document.getElementById('paythor-script');
            if (script) script.remove();

            const style = document.getElementById('paythor-style');
            if (style) style.remove();

            // Try to force unmount React node if it holds memory
            const rootNode = document.getElementById('root');
            if (rootNode) rootNode.innerHTML = '';
        }
    }
});
