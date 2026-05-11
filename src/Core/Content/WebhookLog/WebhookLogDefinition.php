<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\WebhookLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;

class WebhookLogDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'sanalpospro_webhook_log';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return WebhookLogEntity::class;
    }

    public function getCollectionClass(): string
    {
        return WebhookLogCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('order_tx_id', 'orderTxId'))->addFlags(new Required()),
            new StringField('paythor_tx_id', 'paythorTxId'),
            (new StringField('action', 'action'))->addFlags(new Required()),
            (new StringField('status', 'status'))->addFlags(new Required()),
            new FloatField('amount', 'amount'),
            new StringField('currency', 'currency'),
            new LongTextField('raw_payload', 'rawPayload'),
        ]);
    }
}
