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
        $pub = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
        $sec = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');

        if ($pub === '' || $sec === '') {
            return $this->error('API keys not configured. Please enter your Public and Secret keys in the plugin settings.');
        }

        $token = (string) ($params['iapi_accessToken'] ?? '');
        if ($token === '') {
            return $this->error('No access token provided.');
        }

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

            $rawBody = $response->getContent(false);
            $httpCode = $response->getStatusCode();
            $this->logger->info('SanalPosPro: checkApiKeys raw', ['http' => $httpCode, 'body' => substr($rawBody, 0, 500)]);

            $data = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
                return $this->error('API returned non-JSON (HTTP ' . $httpCode . '): ' . substr($rawBody, 0, 300));
            }

            $this->logger->info('SanalPosPro: checkApiKeys', ['status' => $data['status'] ?? 'unknown']);

            if (($data['status'] ?? '') === 'success') {
                $this->discoverAndSaveShopwareAppId($token, $pub, $sec);
            }

            return $data;
        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: checkApiKeys failed', ['error' => $e->getMessage()]);
            return $this->error('Request failed: ' . $e->getMessage());
        }
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

        return $this->success('Module settings updated.', ['updated_settings' => $updated]);
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
