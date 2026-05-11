<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\WebhookLog;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;

/**
 * DAL definition for `sanalpospro_webhook_log`.
 * Column names match Contract C exactly (agreed between Dev 2 and Dev 3).
 *
 * Maps to: paythor_transaction_log in Magento (see README §Contract C).
 */
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
            // id  BINARY(16) PRIMARY KEY — UUID
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),

            // order_tx_id  VARCHAR(128) NOT NULL — Shopware order_transaction.id
            (new StringField('order_tx_id', 'orderTxId'))->addFlags(new Required()),

            // paythor_tx_id  VARCHAR(128) NULL — PayThor gateway transaction_id
            new StringField('paythor_tx_id', 'paythorTxId'),

            // action  VARCHAR(32) NOT NULL — 'webhook' | 'callback'
            (new StringField('action', 'action'))->addFlags(new Required()),

            // status  VARCHAR(32) NOT NULL — 'success' | 'failed' | 'pending' | 'refunded'
            (new StringField('status', 'status'))->addFlags(new Required()),

            // amount  DECIMAL(20,4) NULL
            new FloatField('amount', 'amount'),

            // currency  CHAR(3) NULL
            new StringField('currency', 'currency'),

            // raw_payload  LONGTEXT NULL — full JSON
            new LongTextField('raw_payload', 'rawPayload'),
        ]);
    }
}