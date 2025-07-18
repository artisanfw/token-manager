<?php

namespace Artisan\TokenManager\Entities;

use DateTimeInterface;

class Token
{
    private ?int $id = null;
    private string $entityName;
    private int $entityId;
    private string $code;
    private string $type;
    private string $behavior;
    private ?int $remainingUses = null;
    private DateTimeInterface $expirationAt;
    private DateTimeInterface $createdAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): void
    {
        $this->entityName = $entityName;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): void
    {
        $this->entityId = $entityId;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): void
    {
        $this->code = $code;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getBehavior(): string
    {
        return $this->behavior;
    }

    public function setBehavior(string $behavior): void
    {
        $this->behavior = $behavior;
    }

    public function getRemainingUses(): ?int
    {
        return $this->remainingUses;
    }

    public function setRemainingUses(?int $remainingUses): void
    {
        $this->remainingUses = $remainingUses;
    }

    public function getExpirationAt(): DateTimeInterface
    {
        return $this->expirationAt;
    }

    public function setExpirationAt(DateTimeInterface $expirationAt): void
    {
        $this->expirationAt = $expirationAt;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function isExpired(DateTimeInterface $now): bool
    {
        return $this->expirationAt <= $now;
    }
}