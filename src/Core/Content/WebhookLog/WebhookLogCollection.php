<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\WebhookLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(WebhookLogEntity $entity)
 * @method void                  set(string $key, WebhookLogEntity $entity)
 * @method WebhookLogEntity[]    getIterator()
 * @method WebhookLogEntity[]    getElements()
 * @method WebhookLogEntity|null get(string $key)
 * @method WebhookLogEntity|null first()
 * @method WebhookLogEntity|null last()
 */
class WebhookLogCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return WebhookLogEntity::class;
    }
}