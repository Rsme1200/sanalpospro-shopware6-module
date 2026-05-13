<?php declare(strict_types=1);

namespace SanalposproPayment\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class IapiController extends StorefrontController
{
    private const PAYTHOR_API_BASE        = 'https://live-api.sanalpospro.com';
    private const SHOPWARE_APP_ID_DEFAULT = 106;
    private const PROGRAM_ID             = 1;
    private const CONFIG_APP_ID          = 'SanalPosPro.config.appId';
    private const CONFIG_PUBLIC_KEY      = 'SanalPosPro.config.publicApiKey';
    private const CONFIG_SECRET_KEY      = 'SanalPosPro.config.secretApiKey';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route(
        path: '/sanalpospro/iapi/index',
        name: 'frontend.sanalpospro.iapi',
        defaults: ['csrf_protected' => false, 'XmlHttpRequest' => true],
        methods: ['POST', 'OPTIONS'],
    )]
    public function index(Request $request, Context $context): JsonResponse
    {
        $corsHeaders = [
            'Access-Control-Allow-Origin'  => '*',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization, etc-program-id, etc-app-id',
        ];

        if ($request->getMethod() === 'OPTIONS') {
            $response = new JsonResponse(null, 204, $corsHeaders);
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            return $response;
        }

        $action     = (string) $request->request->get('iapi_action', '');
        $xfvv       = (string) $request->request->get('iapi_xfvv', '');
        $iapiParams = json_decode((string) $request->request->get('iapi_params', '{}'), true) ?? [];

        if ($action === '') {
            return new JsonResponse($this->error('Action not specified.'), 200, $corsHeaders);
        }

        $method = 'action' . ucfirst($action);
        if (!method_exists($this, $method)) {
            return new JsonResponse($this->error('Unknown action: ' . $action), 200, $corsHeaders);
        }

        return new JsonResponse($this->$method($iapiParams), 200, $corsHeaders);
    }

    #[Route(
        path: '/sanalpospro/iapi/config',
        name: 'frontend.sanalpospro.iapi.config',
        defaults: ['csrf_protected' => false],
        methods: ['GET'],
    )]
    public function config(): JsonResponse
    {
        return new JsonResponse(
            ['app_id' => $this->savedAppId()],
            200,
            ['Access-Control-Allow-Origin' => '*'],
        );
    }

    // ── Actions (mirror Magento Handler.php) ──────────────────────────────────

    private function actionCheckApiKeys(array $params): array
    {
        $token = (string) ($params['iapi_accessToken'] ?? '');
        if ($token === '') {
            return $this->error('No access token provided.');
        }

        $pub = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
        $sec = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');

        // No keys yet (fresh install) → fetch them now using the Bearer token.
        if ($pub === '' || $sec === '') {
            $fetchResult = $this->fetchAndSaveApiKeys($token);
            if ($fetchResult['status'] !== 'success') {
                return $fetchResult;
            }
            $pub = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
            $sec = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');
        }

        if ($pub === '' || $sec === '') {
            return $this->error('API keys could not be retrieved automatically.');
        }

        $data = $this->callCheckAccessToken($token, $pub, $sec);

        // "Merchant ID mismatch" means the stored keys belong to a DIFFERENT merchant
        // than the one who just logged in (e.g. the store is being reconnected to a new
        // PayThor account).  Clear the stale keys, re-fetch for the new merchant, retry.
        if (
            ($data['status'] ?? '') !== 'success'
            && str_contains(strtolower((string) ($data['message'] ?? '')), 'mismatch')
        ) {
            $this->logger->info('SanalPosPro: merchant mismatch — clearing stale keys and re-fetching');
            $this->systemConfigService->delete(self::CONFIG_PUBLIC_KEY);
            $this->systemConfigService->delete(self::CONFIG_SECRET_KEY);
            $this->systemConfigService->delete(self::CONFIG_APP_ID);

            $fetchResult = $this->fetchAndSaveApiKeys($token);
            if ($fetchResult['status'] !== 'success') {
                return $fetchResult;
            }

            $pub  = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
            $sec  = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');
            $data = $this->callCheckAccessToken($token, $pub, $sec);
        }

        $this->logger->info('SanalPosPro: checkApiKeys', ['status' => $data['status'] ?? 'unknown']);

        if (($data['status'] ?? '') === 'success') {
            $this->discoverAndSaveShopwareAppId($token, $pub, $sec);
        }

        return $data;
    }

    private function callCheckAccessToken(string $token, string $pub, string $sec): array
    {
        try {
            [$hashTime, $hashRand, $hash] = $this->generateHash($pub, $sec);

            $response = $this->httpClient->request('POST', self::PAYTHOR_API_BASE . '/check/accesstoken', [
                'headers' => [
                    'Authorization'  => 'ApiKeys ' . $pub . ':' . $hash,
                    'X-Timestamp'    => $hashTime,
                    'X-Nonce'        => $hashRand,
                    'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
                    'ETC-APP-ID'     => (string) $this->savedAppId(),
                    'Content-Type'   => 'application/json',
                ],
                'json'    => ['accesstoken' => $token],
                'timeout' => 10,
            ]);

            $rawBody  = $response->getContent(false);
            $httpCode = $response->getStatusCode();
            $this->logger->info('SanalPosPro: checkApiKeys raw', ['http' => $httpCode, 'body' => substr($rawBody, 0, 500)]);

            $data = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return $this->error('API returned non-JSON (HTTP ' . $httpCode . '): ' . substr($rawBody, 0, 300));
            }

            return $data;
        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: checkApiKeys failed', ['error' => $e->getMessage()]);
            return $this->error('Request failed: ' . $e->getMessage());
        }
    }

    private function actionFetchApiKeys(array $params): array
    {
        $token = (string) ($params['iapi_accessToken'] ?? '');
        if ($token === '') {
            return $this->error('No access token provided.');
        }

        return $this->fetchAndSaveApiKeys($token);
    }

    private function actionSaveApiKeys(array $params): array
    {
        $pub = trim((string) ($params['iapi_publicKey'] ?? ''));
        $sec = trim((string) ($params['iapi_secretKey'] ?? ''));

        if ($pub === '' || $sec === '') {
            return $this->error('Missing API keys.');
        }

        $this->systemConfigService->set(self::CONFIG_PUBLIC_KEY, $pub);
        $this->systemConfigService->set(self::CONFIG_SECRET_KEY, $sec);

        return $this->success('API keys saved.');
    }

    private function actionSetInstallmentOptions(array $params): array
    {
        $options = $params['iapi_installmentOptions'] ?? null;
        if (empty($options)) {
            return $this->error('Invalid installment options.');
        }

        $this->systemConfigService->set('SanalPosPro.config.installments', json_encode($options));

        $this->logger->info('SanalPosPro: installment options saved', ['count' => is_array($options) ? count($options) : 0]);

        return $this->success('Installment options updated.');
    }

    private function actionSetModuleSettings(array $params): array
    {
        $settings = $params['iapi_moduleSettings'] ?? [];
        if (empty($settings) || !is_array($settings)) {
            return $this->error('No settings provided.');
        }

        $allowedMap = [
            'order_status'        => 'orderStatus',
            'currency_convert'    => 'currencyConvert',
            'showinstallmentstabs' => 'showInstallmentsTabs',
            'paymentpagetheme'    => 'paymentPageTheme',
        ];

        $updated = [];
        foreach ($settings as $key => $value) {
            $normalized = strtolower((string) $key);
            if (isset($allowedMap[$normalized])) {
                $this->systemConfigService->set('SanalPosPro.config.' . $allowedMap[$normalized], (string) $value);
                $updated[$key] = $value;
            }
        }

        $this->logger->info('SanalPosPro: module settings updated', ['keys' => array_keys($updated)]);

        return $this->success('Module settings updated.', ['updated_settings' => $updated]);
    }

    /**
     * Return the persisted installment options so the CDN React admin app can
     * hydrate its "Taksitler" (Installments) tab on page load.
     */
    private function actionGetInstallmentOptions(array $params): array
    {
        $raw = (string) ($this->systemConfigService->get('SanalPosPro.config.installments') ?? '');
        $options = json_decode($raw, true);

        if (!is_array($options)) {
            $options = [];
        }

        return $this->success('Installment options retrieved.', ['installmentOptions' => $options]);
    }

    /**
     * Return the persisted module settings so the CDN React admin app can
     * hydrate its "Ayarlar" (Settings) tab on page load.
     */
    private function actionGetModuleSettings(array $params): array
    {
        $settingsMap = [
            'order_status'         => 'orderStatus',
            'currency_convert'     => 'currencyConvert',
            'showInstallmentsTabs' => 'showInstallmentsTabs',
            'paymentPageTheme'     => 'paymentPageTheme',
        ];

        $settings = [];
        foreach ($settingsMap as $key => $configKey) {
            $value = $this->systemConfigService->get('SanalPosPro.config.' . $configKey);
            $settings[$key] = $value !== null ? (string) $value : '';
        }

        return $this->success('Module settings retrieved.', ['moduleSettings' => $settings]);
    }

    private function actionGetMerchantInfo(array $params): array
    {
        $pub = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
        $sec = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');

        if ($pub === '' || $sec === '') {
            return $this->error('API keys not configured.');
        }

        try {
            [$hashTime, $hashRand, $hash] = $this->generateHash($pub, $sec);

            $response = $this->httpClient->request('POST', self::PAYTHOR_API_BASE . '/merchant/info', [
                'headers' => [
                    'Authorization'  => 'ApiKeys ' . $pub . ':' . $hash,
                    'X-Timestamp'    => $hashTime,
                    'X-Nonce'        => $hashRand,
                    'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
                    'ETC-APP-ID'     => (string) $this->savedAppId(),
                    'Content-Type'   => 'application/json',
                ],
                'json'    => [],
                'timeout' => 10,
            ]);

            return $response->toArray(false);
        } catch (\Throwable $e) {
            return $this->error('Merchant info request failed: ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Full key-bootstrap sequence mirroring Magento Workflows/Login.php:56-83:
     *   1. GET  /app/list/my          → find installed app record for app_id 106
     *   2. POST /app/install/106      → install if not present yet
     *   3. GET  /app/getapikeys/{id}  → {id} is the installed record ID, not 106
     *   4. Persist public_key + secret_key into system_config
     */
    private function fetchAndSaveApiKeys(string $bearerToken): array
    {
        $bearerHeaders = [
            'Authorization'  => 'Bearer ' . $bearerToken,
            'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
            'ETC-APP-ID'     => (string) self::SHOPWARE_APP_ID_DEFAULT,
            'Content-Type'   => 'application/json',
        ];

        try {
            // Step 1: find the merchant's installed app record
            [$myApp, $allApps] = $this->findMyApp($bearerHeaders);

            $this->logger->info('SanalPosPro: listMyApps full', ['apps' => $allApps]);

            // Step 2: not installed yet — install it now
            if ($myApp === null) {
                $this->logger->info('SanalPosPro: app not installed, installing now');

                $installRaw = $this->httpClient->request(
                    'POST',
                    self::PAYTHOR_API_BASE . '/app/install/' . self::SHOPWARE_APP_ID_DEFAULT,
                    [
                        'headers' => $bearerHeaders,
                        'json'    => [
                            'install' => [
                                'app_stage'  => 'production',
                                'app_id'     => self::SHOPWARE_APP_ID_DEFAULT,
                                'program_id' => self::PROGRAM_ID,
                                'store_url'  => 'http://localhost',
                            ],
                        ],
                        'timeout' => 10,
                    ]
                );

                $installBody = $installRaw->getContent(false);
                $installData = json_decode($installBody, true) ?? [];
                $this->logger->info('SanalPosPro: install response', [
                    'http'    => $installRaw->getStatusCode(),
                    'body'    => substr($installBody, 0, 500),
                    'status'  => $installData['status'] ?? 'unknown',
                    'message' => $installData['message'] ?? '',
                ]);

                // Re-fetch after install attempt
                [$myApp, $allApps] = $this->findMyApp($bearerHeaders);
                $this->logger->info('SanalPosPro: listMyApps after install', ['apps' => $allApps]);
            }

            if ($myApp === null) {
                $appSummary = array_map(fn($a) => ['id' => $a['id'] ?? '?', 'app_id' => $a['app_id'] ?? '?', 'name' => $a['name'] ?? '?'], $allApps);
                return $this->error('App not found after install attempt. Available apps: ' . json_encode($appSummary));
            }

            // Step 3: get the API keys using the installed record's own ID
            $installedId = (int) ($myApp['id'] ?? 0);
            if ($installedId === 0) {
                return $this->error('Installed app record has no id field. Record: ' . json_encode($myApp));
            }

            $keysResp = $this->httpClient->request(
                'GET',
                self::PAYTHOR_API_BASE . '/app/getapikeys/' . $installedId,
                ['headers' => $bearerHeaders, 'timeout' => 10]
            );

            $rawBody  = $keysResp->getContent(false);
            $httpCode = $keysResp->getStatusCode();
            $this->logger->info('SanalPosPro: getApiKeys raw', ['http' => $httpCode, 'body' => substr($rawBody, 0, 500)]);

            $data = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return $this->error('getApiKeys returned non-JSON (HTTP ' . $httpCode . '): ' . substr($rawBody, 0, 300));
            }

            if (($data['status'] ?? '') !== 'success') {
                return $this->error('getApiKeys failed: ' . ($data['message'] ?? 'unknown error'));
            }

            $publicKey = (string) ($data['data']['public_key'] ?? '');
            $secretKey = (string) ($data['data']['secret_key'] ?? '');

            if ($publicKey === '' || $secretKey === '') {
                return $this->error('getApiKeys response missing public_key or secret_key.');
            }

            // Step 4: persist
            $this->systemConfigService->set(self::CONFIG_PUBLIC_KEY, $publicKey);
            $this->systemConfigService->set(self::CONFIG_SECRET_KEY, $secretKey);

            $this->logger->info('SanalPosPro: API keys auto-fetched and saved', ['installed_id' => $installedId]);

            return $this->success('API keys retrieved and saved automatically.', ['public_key' => $publicKey]);

        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: fetchAndSaveApiKeys failed', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch API keys: ' . $e->getMessage());
        }
    }

    /**
     * GET /app/list/my and return [matchedApp|null, allApps[]].
     * Matches by app_id == SHOPWARE_APP_ID_DEFAULT, or falls back to any app
     * whose name contains "shopware" or "swr".
     *
     * @return array{0: array|null, 1: array}
     */
    private function findMyApp(array $bearerHeaders): array
    {
        $response = $this->httpClient->request('GET', self::PAYTHOR_API_BASE . '/app/list/my', [
            'headers' => $bearerHeaders,
            'timeout' => 10,
        ]);

        $data = json_decode($response->getContent(false), true);
        $this->logger->info('SanalPosPro: listMyApps', ['status' => $data['status'] ?? 'unknown']);

        if (($data['status'] ?? '') !== 'success' || !is_array($data['data'] ?? null)) {
            return [null, []];
        }

        $all = $data['data'];

        // Primary match: exact app_id
        foreach ($all as $app) {
            if ((int) ($app['app_id'] ?? 0) === self::SHOPWARE_APP_ID_DEFAULT) {
                return [$app, $all];
            }
        }

        // Fallback: name contains "shopware" or "swr"
        foreach ($all as $app) {
            $name = strtolower((string) ($app['name'] ?? ''));
            if (str_contains($name, 'shopware') || str_contains($name, 'swr')) {
                return [$app, $all];
            }
        }

        return [null, $all];
    }

    private function savedAppId(): int
    {
        $saved = $this->systemConfigService->get(self::CONFIG_APP_ID);
        return ($saved !== null && (int) $saved > 0) ? (int) $saved : self::SHOPWARE_APP_ID_DEFAULT;
    }

    private function generateHash(string $publicKey, string $secretKey): array
    {
        $hashTime = (string) microtime(true);
        $hashRand = (string) random_int(1000000, 9999999);
        $hash     = hash('sha256', $publicKey . $secretKey . $hashTime . $hashRand);

        return [$hashTime, $hashRand, $hash];
    }

    private function discoverAndSaveShopwareAppId(string $token, string $pub, string $sec): void
    {
        try {
            [$hashTime, $hashRand, $hash] = $this->generateHash($pub, $sec);

            $response = $this->httpClient->request('GET', self::PAYTHOR_API_BASE . '/app/list/all', [
                'headers' => [
                    'Authorization'  => 'ApiKeys ' . $pub . ':' . $hash,
                    'X-Timestamp'    => $hashTime,
                    'X-Nonce'        => $hashRand,
                    'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
                    'ETC-APP-ID'     => (string) self::SHOPWARE_APP_ID_DEFAULT,
                    'Content-Type'   => 'application/json',
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray(false);

            foreach ($data['data'] ?? [] as $app) {
                $appId = (int) ($app['id'] ?? 0);
                $name  = strtolower((string) ($app['name'] ?? ''));
                if ($appId === self::SHOPWARE_APP_ID_DEFAULT || str_contains($name, 'swr') || str_contains($name, 'shopware')) {
                    $this->systemConfigService->set(self::CONFIG_APP_ID, $appId);
                    $this->logger->info('SanalPosPro: app ID saved', ['app_id' => $appId]);
                    return;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: discoverAppId failed', ['error' => $e->getMessage()]);
        }
    }

    private function success(string $message, array $data = []): array
    {
        return [
            'status'  => 'success',
            'message' => $message,
            'data'    => $data,
            'details' => [],
            'meta'    => ['xfvv' => null, 'nonce' => null],
        ];
    }

    private function error(string $message): array
    {
        return [
            'status'  => 'error',
            'message' => $message,
            'details' => [],
            'meta'    => ['xfvv' => null, 'nonce' => null],
        ];
    }
}
