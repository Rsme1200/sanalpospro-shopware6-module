<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\Installment;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                    add(InstallmentEntity $entity)
 * @method void                    set(string $key, InstallmentEntity $entity)
 * @method InstallmentEntity[]     getIterator()
 * @method InstallmentEntity[]     getElements()
 * @method InstallmentEntity|null  get(string $key)
 * @method InstallmentEntity|null  first()
 * @method InstallmentEntity|null  last()
 */
class InstallmentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return InstallmentEntity::class;
    }
}