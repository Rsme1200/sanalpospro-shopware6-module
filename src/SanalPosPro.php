<?php declare(strict_types=1);

namespace SanalposproPayment;

use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use SanalposproPayment\Service\CustomFieldsInstaller;

class SanalPosPro extends Plugin
{
    public function install(InstallContext $installContext): void
    {
        $this->getPaymentMethodInstaller()->install($installContext->getContext());
    }

    public function uninstall(UninstallContext $uninstallContext): void
    {
        parent::uninstall($uninstallContext);

        if ($uninstallContext->keepUserData()) {
            return;
        }

        $this->getPaymentMethodInstaller()->setPaymentMethodIsActive(false, $uninstallContext->getContext());
    }

    public function activate(ActivateContext $activateContext): void
    {
        $this->getPaymentMethodInstaller()->setPaymentMethodIsActive(true, $activateContext->getContext());
        
        // Force bind payment method to all sales channels to prevent it from hiding in storefront
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $this->container->get(\Doctrine\DBAL\Connection::class);
        $connection->executeStatement("
            INSERT IGNORE INTO sales_channel_payment_method (sales_channel_id, payment_method_id)
            SELECT id, (SELECT id FROM payment_method WHERE handler_identifier='SanalposproPayment\\\\Service\\\\SanalPosProPaymentHandler' LIMIT 1)
            FROM sales_channel;
        ");
    }

    public function deactivate(DeactivateContext $deactivateContext): void
    {
        $this->getPaymentMethodInstaller()->setPaymentMethodIsActive(false, $deactivateContext->getContext());
    }

    public function update(UpdateContext $updateContext): void
    {
    }

    public function postInstall(InstallContext $installContext): void
    {
    }

    public function postUpdate(UpdateContext $updateContext): void
    {
    }

    private function getCustomFieldsInstaller(): CustomFieldsInstaller
    {
        if ($this->container->has(CustomFieldsInstaller::class)) {
            return $this->container->get(CustomFieldsInstaller::class);
        }

        return new CustomFieldsInstaller(
            $this->container->get('custom_field_set.repository'),
            $this->container->get('custom_field_set_relation.repository')
        );
    }

    private function getPaymentMethodInstaller(): \SanalposproPayment\Util\PaymentMethodInstaller
    {
        if ($this->container->has(\SanalposproPayment\Util\PaymentMethodInstaller::class)) {
            return $this->container->get(\SanalposproPayment\Util\PaymentMethodInstaller::class);
        }
        
        return new \SanalposproPayment\Util\PaymentMethodInstaller(
            $this->container->get('payment_method.repository'),
            $this->container->get(\Shopware\Core\Framework\Plugin\Util\PluginIdProvider::class)
        );
    }
}
