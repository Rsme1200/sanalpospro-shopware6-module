<?php declare(strict_types=1);

namespace SanalposproPayment\Storefront\Controller;

use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Shopware\Core\Framework\Context;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class IapiController extends StorefrontController
{
    // This is the Internal API bridge that PayThor CDN app communicates with after OTP/login.
    #[Route(path: '/sanalpospro/iapi/index', name: 'frontend.sanalpospro.iapi', defaults: ['csrf_protected' => false, 'XmlHttpRequest' => true], methods: ['POST', 'OPTIONS'])]
    public function index(Request $request, Context $context): JsonResponse
    {
        // Handle CORS Preflight request from PayThor React App
        if ($request->getMethod() === 'OPTIONS') {
            $response = new JsonResponse();
            $response->headers->set('Access-Control-Allow-Origin', '*');
            $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Origin, etc-program-id, etc-app-id');
            return $response;
        }

        $data = json_decode($request->getContent(), true);

        // Here we would typically validate the XFVV token and create a secure session.
        // For now, we return a success payload to satisfy the React App's expectations.
        return new JsonResponse([
            'status' => 'success',
            'data' => [
                'token' => 'dummy-shopware-iapi-token',
                'message' => 'Connected successfully to Shopware IAPI'
            ]
        ], 200, [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
        ]);
    }
}
