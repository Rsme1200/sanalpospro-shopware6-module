// SanalPosPro Administration Modules

// Fix: the CDN React app hardcodes app_id=105 (Magento) in its auth payload.
// Intercept fetch calls going to paythor.com and replace auth_query.app_id with
// the active Shopware app id before the request leaves the browser.
// Scoped to paythor.com only so Shopware's own API calls are never touched.
// Runs once at startup before any module or CDN script loads.
(function patchPaythorAuthFetch() {
    const DEFAULT_APP_ID = 106;
    const MAX_SANE_APP_ID = 1000;
    const SHOPWARE_APP_NAME = 'Shopware SanalPOS PRO!';

    function normalizeAppId(raw) {
        const parsed = Number.parseInt(raw, 10);
        if (!Number.isInteger(parsed) || parsed <= 0 || parsed > MAX_SANE_APP_ID) {
            return null;
        }

        return parsed;
    }

    function resolveActiveAppId() {
        const fromWindow = normalizeAppId(window.app_id);
        if (fromWindow !== null) {
            return fromWindow;
        }

        try {
            const fromMarker = normalizeAppId(localStorage.getItem('paythor-connect-app-id'));
            if (fromMarker !== null) {
                return fromMarker;
            }
        } catch (_) {}

        return DEFAULT_APP_ID;
    }

    function parseUrl(rawUrl) {
        try {
            return new URL(rawUrl, window.location.origin);
        } catch (_) {
            return null;
        }
    }

    function normalizeCatalogApp(app) {
        if (!app || typeof app !== 'object' || Array.isArray(app)) {
            return null;
        }

        const row = Object.assign({}, app);
        const id = normalizeAppId(row.id);
        const name = typeof row.name === 'string' ? row.name.trim() : '';
        const slug = name.toLowerCase();

        if (row.app_id === undefined && id !== null) {
            row.app_id = id;
        }

        if (row.appId === undefined && id !== null) {
            row.appId = id;
        }

        if (id === DEFAULT_APP_ID || slug.includes('swr') || slug.includes('shopware')) {
            row.name = SHOPWARE_APP_NAME;
            row.platform = 'shopware';
        }

        return row;
    }

    function normalizeCatalogApps(apps) {
        if (!Array.isArray(apps)) {
            return [];
        }

        return apps.map(normalizeCatalogApp).filter(Boolean);
    }

    async function ensureArrayDataResponse(response, pathname) {
        try {
            const payload = await response.clone().json();
            if (!payload || typeof payload !== 'object') {
                return response;
            }

            if (!Array.isArray(payload.data)) {
                payload.data = [];
            }

            if (pathname === '/app/list/all' || pathname === '/app/list/my') {
                payload.data = normalizeCatalogApps(payload.data);
            }

            const headers = new Headers(response.headers);
            headers.set('Content-Type', 'application/json');

            return new Response(JSON.stringify(payload), {
                status: response.status,
                statusText: response.statusText,
                headers,
            });
        } catch (_) {
            return response;
        }
    }

    const _nativeFetch = window.fetch.bind(window);

    window.fetch = function (input, init) {
        const url = typeof input === 'string' ? input : (input && input.url) || '';
        const parsedUrl = parseUrl(url);
        const hostname = parsedUrl ? parsedUrl.hostname.toLowerCase() : '';
        const pathname = parsedUrl ? parsedUrl.pathname : '';
        const isPaythorUrl = hostname.includes('paythor.com') || hostname.includes('sanalpospro.com');
        const shouldEnsureArrayData = hostname === 'live-api.sanalpospro.com'
            && (pathname === '/app/list/my' || pathname === '/app/list/all');

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
        const appId = resolveActiveAppId();

        // Patch etc-app-id header on every request to PayThor/SanalPosPro APIs.
        const rawHeaders = init.headers || {};
        const headers = rawHeaders instanceof Headers ? rawHeaders : new Headers(rawHeaders);
        headers.set('etc-app-id', String(appId));
        headers.set('ETC-APP-ID', String(appId));
        init.headers = headers;

        // Patch app_id inside auth_query body.
        if (init.body && typeof init.body === 'string') {
            try {
                const parsed = JSON.parse(init.body);
                if (parsed.auth_query && parsed.auth_query.app_id !== appId) {
                    parsed.auth_query.app_id = appId;
                    init.body = JSON.stringify(parsed);
                }
            } catch (_) {}
        }

        const request = _nativeFetch(input, init);

        if (shouldEnsureArrayData) {
            return request.then(response => ensureArrayDataResponse(response, pathname));
        }

        return request;
    };
})();

// Import all child modules directly
import './module/sanalpospro-installment';
import './module/sanalpospro-webhook-log';
import './module/sanalpospro-connect';
