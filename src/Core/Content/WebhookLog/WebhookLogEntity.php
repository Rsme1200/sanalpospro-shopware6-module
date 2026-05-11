<?php declare(strict_types=1);

namespace SanalposproPayment\Core\Content\WebhookLog;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class WebhookLogEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderTxId;

    protected ?string $paythorTxId = null;

    protected string $action;

    protected string $status;

    protected ?float $amount = null;

    protected ?string $currency = null;

    protected ?string $rawPayload = null;

    public function getOrderTxId(): string
    {
        return $this->orderTxId;
    }

    public function setOrderTxId(string $orderTxId): void
    {
        $this->orderTxId = $orderTxId;
    }

    public function getPaythorTxId(): ?string
    {
        return $this->paythorTxId;
    }

    public function setPaythorTxId(?string $paythorTxId): void
    {
        $this->paythorTxId = $paythorTxId;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): void
    {
        $this->action = $action;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function setAmount(?float $amount): void
    {
        $this->amount = $amount;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(?string $currency): void
    {
        $this->currency = $currency;
    }

    public function getRawPayload(): ?string
    {
        return $this->rawPayload;
    }

    public function setRawPayload(?string $rawPayload): void
    {
        $this->rawPayload = $rawPayload;
    }
}
