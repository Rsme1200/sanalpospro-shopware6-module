<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\Installment;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class InstallmentEntity extends Entity
{
    use EntityIdTrait;

    protected string $bankName;

    protected ?string $cardType = null;

    protected int $installmentCount;

    protected float $interestRate = 0.00;

    protected bool $isActive = true;

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function setBankName(string $bankName): void
    {
        $this->bankName = $bankName;
    }

    public function getCardType(): ?string
    {
        return $this->cardType;
    }

    public function setCardType(?string $cardType): void
    {
        $this->cardType = $cardType;
    }

    public function getInstallmentCount(): int
    {
        return $this->installmentCount;
    }

    public function setInstallmentCount(int $installmentCount): void
    {
        $this->installmentCount = $installmentCount;
    }

    public function getInterestRate(): float
    {
        return $this->interestRate;
    }

    public function setInterestRate(float $interestRate): void
    {
        $this->interestRate = $interestRate;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
}
