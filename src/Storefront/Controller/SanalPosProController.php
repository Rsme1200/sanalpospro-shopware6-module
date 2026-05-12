<?php declare(strict_types=1);

namespace SanalposproPayment\Storefront\Controller;

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class SanalPosProController extends StorefrontController
{
    public function __construct(
        private readonly SystemConfigService $systemConfigService,
    ) {}

    #[Route(
        path: '/sanalpospro/iframe/{transactionId}',
        name: 'frontend.sanalpospro.iframe',
        methods: ['GET']
    )]
    public function iframe(string $transactionId, Request $request): Response
    {
        if ($transactionId === '') {
            return $this->redirectToRoute('frontend.checkout.cart.page');
        }

        $returnUrl   = (string) $request->query->get('returnUrl', '');
        $publicApiKey = (string) ($this->systemConfigService->get('SanalPosPro.config.publicApiKey') ?? '');

        return $this->renderStorefront('@SanalPosPro/storefront/page/checkout/iframe.html.twig', [
            'transactionId' => $transactionId,
            'returnUrl'     => $returnUrl,
            'publicApiKey'  => $publicApiKey,
            'iframeUrl'     => '',
        ]);
    }

    #[Route(
        path: '/sanalpospro/callback',
        name: 'frontend.sanalpospro.callback',
        methods: ['GET']
    )]
    public function callback(Request $request): Response
    {
        $status    = (string) $request->query->get('status', 'failure');
        $reference = (string) $request->query->get('reference', '');
        $message   = (string) $request->query->get('message', '');

        if (!in_array($status, ['success', 'failure', 'cancel'], true)) {
            $status = 'failure';
        }

        $payload = json_encode(
            [
                'source'    => 'paythor_sanalpospro',
                'status'    => $status,
                'reference' => $reference,
                'message'   => $message,
            ],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        $html = '<!DOCTYPE html>'
            . '<html lang="en"><head><meta charset="UTF-8"></head><body>'
            . '<script>window.parent.postMessage(' . $payload . ', window.location.origin);</script>'
            . '</body></html>';

        return new Response(
            $html,
            Response::HTTP_OK,
            [
                'Content-Type'  => 'text/html; charset=UTF-8',
                'X-Frame-Options' => 'SAMEORIGIN',
                'Cache-Control' => 'no-store',
            ]
        );
    }
}
