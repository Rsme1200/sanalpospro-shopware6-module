<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\Installment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;

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
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('bank_name', 'bankName'))->addFlags(new Required()),
            new StringField('card_type', 'cardType'),
            (new IntField('installment_count', 'installmentCount'))->addFlags(new Required()),
            new FloatField('interest_rate', 'interestRate'),
            new BoolField('is_active', 'isActive'),
        ]);
    }
}
