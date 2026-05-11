<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\Yes;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void add(YesEntity $entity)
 * @method void set(string $key, YesEntity $entity)
 * @method YesEntity[] getIterator()
 * @method YesEntity[] getElements()
 * @method YesEntity|null get(string $key)
 * @method YesEntity|null first()
 * @method YesEntity|null last()
 */
class YesCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return YesEntity::class;
    }
}
