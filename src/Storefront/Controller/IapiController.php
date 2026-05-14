<?php declare(strict_types=1);

namespace SanalposproPayment\Storefront\Controller;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
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

    private ?Context $requestContext = null;
    private string $requestIp        = '127.0.0.1';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly EntityRepository $orderTransactionRepository,
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

        $this->requestContext = $context;
        $this->requestIp     = $request->getClientIp() ?? '127.0.0.1';

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

        // "Merchant ID mismatch" means the stored keys belong to a DIFFERENT merchant.
        // Clear the stale keys, re-fetch for the new merchant, retry.
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

    // ── Storefront payment actions ────────────────────────────────────────────

    /**
     * PayThor handles gateway selection on its own payment page.
     * Return empty list → JS in iframe.html.twig skips gateway radio-button UI
     * and calls createPayment directly.
     */
    private function actionGetPaymentGateways(array $params): array
    {
        return $this->success('Gateway selection handled by PayThor payment page.', []);
    }

    /**
     * Creates a PayThor payment session and returns the iframe/payment-page URL.
     *
     * Params from JS:
     *   iapi_transactionId – Shopware order_transaction UUID
     *   iapi_storeUrl      – window.location.origin
     *   iapi_returnUrl     – Shopware finalize URL
     */
    private function actionCreatePayment(array $params): array
    {
        $transactionId = (string) ($params['iapi_transactionId'] ?? '');
        $storeUrl      = rtrim((string) ($params['iapi_storeUrl'] ?? ''), '/');

        if ($transactionId === '' || $storeUrl === '') {
            return $this->error('transactionId and storeUrl are required.');
        }

        $pub = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
        $sec = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');

        if ($pub === '' || $sec === '') {
            return $this->error('API keys not configured.');
        }

        // ── Step 1: load Shopware order ──────────────────────────────────────
        $amount      = 1.0;
        $currency    = 'TRY';
        $firstName   = 'Customer';
        $lastName    = 'Customer';
        $email       = 'customer@example.com';
        $phone       = '0';
        $cartItems   = [];
        $addrData    = ['line_1' => '-', 'city' => 'Istanbul', 'state' => 'Istanbul', 'postal_code' => '34000', 'country' => 'TR'];
        $orderNumber = substr($transactionId, 0, 20);

        try {
            $criteria = new Criteria([$transactionId]);
            $criteria->addAssociation('order.currency');
            $criteria->addAssociation('order.orderCustomer');
            $criteria->addAssociation('order.lineItems');
            $criteria->addAssociation('order.addresses.country');
            $criteria->addAssociation('order.addresses.countryState');

            $ctx      = $this->requestContext ?? Context::createDefaultContext();
            $txEntity = $this->orderTransactionRepository->search($criteria, $ctx)->first();

            if ($txEntity !== null) {
                $order    = $txEntity->getOrder();
                $amount   = (float) $order->getPrice()->getTotalPrice();
                $currency = $order->getCurrency() ? $order->getCurrency()->getIsoCode() : 'TRY';

                $customer    = $order->getOrderCustomer();
                $firstName   = $customer ? ($customer->getFirstName() ?: 'Customer') : 'Customer';
                $lastName    = $customer ? ($customer->getLastName()  ?: 'Customer') : 'Customer';
                $email       = $customer ? ($customer->getEmail()     ?: 'customer@example.com') : 'customer@example.com';
                $orderNumber = $order->getOrderNumber() ?: substr($transactionId, 0, 20);

                foreach ($order->getLineItems() ?? [] as $item) {
                    $cartItems[] = [
                        'id'       => $item->getId(),
                        'name'     => $item->getLabel(),
                        'type'     => 'product',
                        'price'    => (string) round((float) $item->getUnitPrice(), 2),
                        'quantity' => (int) $item->getQuantity(),
                    ];
                }

                $billingId = $order->getBillingAddressId();
                foreach ($order->getAddresses() ?? [] as $addr) {
                    if ($addr->getId() !== $billingId) continue;

                    $stateVal = '';
                    if ($addr->getCountryState()) {
                        $stateVal = $addr->getCountryState()->getName() ?? '';
                    }
                    if ($stateVal === '') {
                        $stateVal = $addr->getCity() ?? 'N/A';
                    }

                    $addrData = [
                        'line_1'      => ($addr->getStreet() ?: '-'),
                        'city'        => ($addr->getCity() ?: 'Istanbul'),
                        'state'       => $stateVal,
                        'postal_code' => ($addr->getZipcode() ?: '00000'),
                        'country'     => $addr->getCountry() ? ($addr->getCountry()->getIso() ?? 'TR') : 'TR',
                    ];
                    $phone = $addr->getPhoneNumber() ?: '0';
                    break;
                }
            } else {
                $this->logger->warning('SanalPosPro: createPayment — transaction not found', ['id' => $transactionId]);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: createPayment — order load failed', ['error' => $e->getMessage()]);
        }

        if (empty($cartItems)) {
            $cartItems = [['id' => '1', 'name' => 'Order', 'type' => 'product', 'price' => (string) round($amount, 2), 'quantity' => 1]];
        }

        // ── Step 2: POST /payment/create ─────────────────────────────────────
        $shopwareReturnUrl = (string) ($params['iapi_returnUrl'] ?? '');
        $callbackUrl = $storeUrl . '/sanalpospro/callback'
            . '?txn=' . rawurlencode($transactionId)
            . '&ret=' . rawurlencode($shopwareReturnUrl);

        $personData = ['firstName' => $firstName, 'lastName' => $lastName, 'phone' => $phone, 'email' => $email, 'address' => $addrData];

        $payload = [
            'payment' => [
                'amount'             => round($amount, 2) ?: 1.0,
                'currency'           => $currency,
                'buyerFee'           => 0,
                'method'             => 'creditcard',
                'merchant_reference' => $orderNumber,
                'return_url'         => $callbackUrl,
            ],
            'payer' => [
                'first_name' => $firstName,
                'last_name'  => $lastName,
                'email'      => $email,
                'phone'      => $phone,
                'ip'         => $this->requestIp,
                'address'    => $addrData,
            ],
            'order' => [
                'cart'     => $cartItems,
                'shipping' => $personData,
                'invoice'  => [
                    'id'        => $orderNumber,
                    'firstName' => $firstName,
                    'lastName'  => $lastName,
                    'price'     => number_format(round($amount, 2), 2, '.', ''),
                    'quantity'  => 1,
                ],
            ],
        ];

        try {
            [$hashTime, $hashRand, $hash] = $this->generateHash($pub, $sec);

            $createResp = $this->httpClient->request('POST', self::PAYTHOR_API_BASE . '/payment/create', [
                'headers' => [
                    'Authorization'  => 'ApiKeys ' . $pub . ':' . $hash,
                    'X-Timestamp'    => $hashTime,
                    'X-Nonce'        => $hashRand,
                    'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
                    'ETC-APP-ID'     => (string) $this->savedAppId(),
                    'Content-Type'   => 'application/json',
                ],
                'json'    => $payload,
                'timeout' => 15,
            ]);

            $rawBody  = $createResp->getContent(false);
            $httpCode = $createResp->getStatusCode();
            $this->logger->info('SanalPosPro: createPayment', ['http' => $httpCode, 'body' => substr($rawBody, 0, 600)]);

            $createData = json_decode($rawBody, true);
            if (!is_array($createData)) {
                return $this->error('payment/create returned non-JSON (HTTP ' . $httpCode . '): ' . substr($rawBody, 0, 200));
            }
            if (($createData['status'] ?? '') !== 'success') {
                $details = implode('; ', $createData['details'] ?? []);
                return $this->error('payment/create failed: ' . ($createData['message'] ?? 'unknown') . ($details ? ' — ' . $details : ''));
            }

            // ── Step 3: GET /payment/getbytoken/{payment_token} ───────────────
            $paymentToken = (string) ($createData['data']['payment_token'] ?? '');
            if ($paymentToken === '') {
                return $this->error('payment/create response missing payment_token.');
            }

            $iframeUrl = '';
            try {
                [$hashTime2, $hashRand2, $hash2] = $this->generateHash($pub, $sec);

                $getByTokenResp = $this->httpClient->request(
                    'GET',
                    self::PAYTHOR_API_BASE . '/payment/getbytoken/' . rawurlencode($paymentToken),
                    [
                        'headers' => [
                            'Authorization'  => 'ApiKeys ' . $pub . ':' . $hash2,
                            'X-Timestamp'    => $hashTime2,
                            'X-Nonce'        => $hashRand2,
                            'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
                            'ETC-APP-ID'     => (string) $this->savedAppId(),
                        ],
                        'timeout' => 10,
                    ]
                );

                $rawBody2  = $getByTokenResp->getContent(false);
                $httpCode2 = $getByTokenResp->getStatusCode();
                $tokenData = json_decode($rawBody2, true);
                $this->logger->info('SanalPosPro: getByToken', ['http' => $httpCode2, 'body' => substr($rawBody2, 0, 500)]);

                if (is_array($tokenData) && ($tokenData['status'] ?? '') === 'success') {
                    $d = $tokenData['data'] ?? $tokenData;
                    $iframeUrl = (string) ($d['payment_link'] ?? $d['iframe_url'] ?? $d['url'] ?? $d['embed_url'] ?? $d['iframe'] ?? '');
                }
            } catch (\Throwable $e) {
                $this->logger->warning('SanalPosPro: getByToken failed, falling back to payment_link', ['error' => $e->getMessage()]);
            }

            if ($iframeUrl === '') {
                $iframeUrl = (string) ($createData['data']['payment_link'] ?? '');
            }
            if ($iframeUrl === '') {
                $iframeUrl = 'https://pay.paythor.com/payment/' . $paymentToken;
            }

            return $this->success('Payment session created.', [
                'iframe_url'    => $iframeUrl,
                'payment_token' => $paymentToken,
            ]);

        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: createPayment failed', ['error' => $e->getMessage()]);
            return $this->error('Payment creation failed: ' . $e->getMessage());
        }
    }

    private function actionGetByToken(array $params): array
    {
        $token = (string) ($params['iapi_token'] ?? '');
        if ($token === '') {
            return $this->error('token is required.');
        }

        $pub = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
        $sec = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');

        if ($pub === '' || $sec === '') {
            return $this->error('API keys not configured.');
        }

        return $this->callGetByToken($token, $pub, $sec);
    }

    private function callGetByToken(string $token, string $pub, string $sec): array
    {
        try {
            [$hashTime, $hashRand, $hash] = $this->generateHash($pub, $sec);

            $response = $this->httpClient->request('GET', self::PAYTHOR_API_BASE . '/payment/getbytoken/' . $token, [
                'headers' => [
                    'Authorization'  => 'ApiKeys ' . $pub . ':' . $hash,
                    'X-Timestamp'    => $hashTime,
                    'X-Nonce'        => $hashRand,
                    'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
                    'ETC-APP-ID'     => (string) $this->savedAppId(),
                ],
                'timeout' => 10,
            ]);

            $rawBody  = $response->getContent(false);
            $httpCode = $response->getStatusCode();
            $this->logger->info('SanalPosPro: getByToken', ['http' => $httpCode, 'body' => substr($rawBody, 0, 500)]);

            $data = json_decode($rawBody, true);
            if (!is_array($data)) {
                return $this->error('getbytoken returned non-JSON (HTTP ' . $httpCode . ').');
            }

            return $data;
        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: getByToken failed', ['error' => $e->getMessage()]);
            return $this->error('getByToken failed: ' . $e->getMessage());
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fetchAndSaveApiKeys(string $bearerToken): array
    {
        $bearerHeaders = [
            'Authorization'  => 'Bearer ' . $bearerToken,
            'ETC-PROGRAM-ID' => (string) self::PROGRAM_ID,
            'ETC-APP-ID'     => (string) self::SHOPWARE_APP_ID_DEFAULT,
            'Content-Type'   => 'application/json',
        ];

        try {
            [$myApp, $allApps] = $this->findMyApp($bearerHeaders);

            $this->logger->info('SanalPosPro: listMyApps full', ['apps' => $allApps]);

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

                [$myApp, $allApps] = $this->findMyApp($bearerHeaders);
                $this->logger->info('SanalPosPro: listMyApps after install', ['apps' => $allApps]);
            }

            if ($myApp === null) {
                $appSummary = array_map(fn($a) => ['id' => $a['id'] ?? '?', 'app_id' => $a['app_id'] ?? '?', 'name' => $a['name'] ?? '?'], $allApps);
                return $this->error('App not found after install attempt. Available apps: ' . json_encode($appSummary));
            }

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

            $this->systemConfigService->set(self::CONFIG_PUBLIC_KEY, $publicKey);
            $this->systemConfigService->set(self::CONFIG_SECRET_KEY, $secretKey);

            $this->logger->info('SanalPosPro: API keys auto-fetched and saved', ['installed_id' => $installedId]);

            return $this->success('API keys retrieved and saved automatically.', ['public_key' => $publicKey]);

        } catch (\Throwable $e) {
            $this->logger->warning('SanalPosPro: fetchAndSaveApiKeys failed', ['error' => $e->getMessage()]);
            return $this->error('Failed to fetch API keys: ' . $e->getMessage());
        }
    }

    /** @return array{0: array|null, 1: array} */
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

        foreach ($all as $app) {
            if ((int) ($app['app_id'] ?? 0) === self::SHOPWARE_APP_ID_DEFAULT) {
                return [$app, $all];
            }
        }

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
