<?php declare(strict_types=1);

namespace SanalposproPayment\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\AsyncPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\RouterInterface;

class SanalPosProPaymentHandler implements AsynchronousPaymentHandlerInterface
{
    private OrderTransactionStateHandler $transactionStateHandler;
    private RouterInterface $router;

    public function __construct(
        OrderTransactionStateHandler $transactionStateHandler,
        RouterInterface $router
    ) {
        $this->transactionStateHandler = $transactionStateHandler;
        $this->router = $router;
    }

    public function pay(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): RedirectResponse
    {
        $transactionId = $transaction->getOrderTransaction()->getId();

        // Müşteriyi SanalPosPro'nun kendi oluşturduğumuz özel ödeme sayfasına (Storefront Route) yönlendiriyoruz.
        // O sayfada CDN React Iframe açılıp kart bilgileri istenecek.
        $redirectUrl = $this->router->generate('frontend.sanalpospro.iframe', [
            'transactionId' => $transactionId,
            'returnUrl'     => $transaction->getReturnUrl(),
        ]);

        return new RedirectResponse($redirectUrl);
    }

    public function finalize(AsyncPaymentTransactionStruct $transaction, RequestDataBag $dataBag, SalesChannelContext $salesChannelContext): void
    {
        // Müşteri React formunu doldurup, ReturnURL üzerinden Shopware'e geri döndüğünde burası tetiklenir.
        // İstersek işlemi direkt "paid" (ödendi) yapabiliriz veya Webhook'a bırakabiliriz.
        // Şimdilik siparişi işlemde (process) bırakalım, webhook "paid" yapacaktır.
        
        $this->transactionStateHandler->process($transaction->getOrderTransaction()->getId(), $salesChannelContext->getContext());
    }
}
