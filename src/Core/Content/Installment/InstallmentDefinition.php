<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\Installment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;

/**
 * DAL definition for `sanalpospro_installment`.
 * Column names match Contract C exactly.
 *
 * Replaces Block/Product/Installments.php from Magento — now a proper
 * Shopware entity managed via the Vue.js Admin CRUD module (Task 3.2).
 */
class InstallmentDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'sanalpospro_installment';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return InstallmentEntity::class;
    }

    public function getCollectionClass(): string
    {
        return InstallmentCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            // id  BINARY(16) PRIMARY KEY — UUID
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),

            // bank_name  VARCHAR(128) NOT NULL
            (new StringField('bank_name', 'bankName'))->addFlags(new Required()),

            // card_type  VARCHAR(64) NULL
            new StringField('card_type', 'cardType'),

            // installment_count  INT NOT NULL
            (new IntField('installment_count', 'installmentCount'))->addFlags(new Required()),

            // interest_rate  DECIMAL(5,2) NOT NULL DEFAULT 0.00
            (new FloatField('interest_rate', 'interestRate'))->addFlags(new Required()),

            // is_active  TINYINT(1) NOT NULL DEFAULT 1
            (new BoolField('is_active', 'isActive'))->addFlags(new Required()),
        ]);
    }
}