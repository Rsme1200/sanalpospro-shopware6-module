// SanalPosPro Administration Modules

// Fix: the CDN React app hardcodes app_id=105 (Magento) in its auth payload.
// Intercept fetch calls going to paythor.com and replace auth_query.app_id with
// the correct Shopware ID (106) before the request leaves the browser.
// Scoped to paythor.com only so Shopware's own API calls are never touched.
// Runs once at startup before any module or CDN script loads.
(function patchPaythorAuthFetch() {
    const APP_ID = 106;
    const _nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        const url = typeof input === 'string' ? input : (input && input.url) || '';
        const isPaythorUrl = url.includes('paythor.com') || url.includes('sanalpospro.com');

        if (!isPaythorUrl) {
            return _nativeFetch(input, init);
        }

        // Skip CDN requests — adding custom headers to static CDN files
        // triggers CORS preflight which the CDN server does not support.
        // Only patch headers for API endpoints.
        const isCdnUrl = url.includes('cdn.paythor.com');
        if (isCdnUrl) {
            return _nativeFetch(input, init);
        }

        init = init ? Object.assign({}, init) : {};

        // Patch etc-app-id header on every request to PayThor/SanalPosPro APIs.
        const rawHeaders = init.headers || {};
        const headers = rawHeaders instanceof Headers ? rawHeaders : new Headers(rawHeaders);
        headers.set('etc-app-id', String(APP_ID));
        init.headers = headers;

        // Patch app_id inside auth_query body.
        if (init.body && typeof init.body === 'string') {
            try {
                const parsed = JSON.parse(init.body);
                if (parsed.auth_query && parsed.auth_query.app_id !== APP_ID) {
                    parsed.auth_query.app_id = APP_ID;
                    init.body = JSON.stringify(parsed);
                }
            } catch (_) {}
        }

        return _nativeFetch(input, init);
    };
})();

// Import all child modules directly
import './module/sanalpospro-installment';
import './module/sanalpospro-webhook-log';
import './module/sanalpospro-connect';
