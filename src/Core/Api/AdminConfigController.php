<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Api;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Provides the /api/sanalpospro/admin-config endpoint consumed by
 * the administration CDN React app (sanalpospro-connect-index component).
 *
 * Returns xfvv token and target_url so the PayThor UI can communicate
 * with the storefront IAPI bridge.
 */
#[Route(defaults: ['_routeScope' => ['api']])]
class AdminConfigController extends AbstractController
{
    private const CONFIG_PUBLIC_KEY = 'SanalPosPro.config.publicApiKey';
    private const CONFIG_SECRET_KEY = 'SanalPosPro.config.secretApiKey';

    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {}

    #[Route(
        path: '/api/sanalpospro/admin-config',
        name: 'api.sanalpospro.admin-config',
        methods: ['GET'],
        defaults: ['auth_required' => true],
    )]
    public function adminConfig(): JsonResponse
    {
        // xfvv = hash derived from the stored API keys.
        // The CDN React app sends this value back to the storefront IAPI
        // bridge so we can verify the request came from an authenticated
        // admin session that has access to the real keys.
        $pub = (string) ($this->systemConfigService->get(self::CONFIG_PUBLIC_KEY) ?? '');
        $sec = (string) ($this->systemConfigService->get(self::CONFIG_SECRET_KEY) ?? '');

        if ($pub !== '' && $sec !== '') {
            $xfvv = hash('sha256', $pub . $sec);
        } else {
            // Fallback — keys not yet configured; CDN app will still load
            // but certain API calls may fail until login completes.
            $xfvv = 'shopware';
        }

        return new JsonResponse([
            'xfvv'       => $xfvv,
            'target_url' => '/sanalpospro/iapi/index',
        ]);
    }
}
