<?php

namespace Artisan\TokenManager\Models;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'tokens')]
class Token
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private int $id;

    #[ORM\Column(type: 'string', length: 32)]
    private string $entityName;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $entityId;

    #[ORM\Column(type: 'string', length: 32)]
    private string $code;

    #[ORM\Column(type: 'string', length: 16)]
    private string $type;

    #[ORM\Column(type: 'string', length: 16)]
    private string $behavior;

    #[ORM\Column(type: 'smallint', nullable: true, options: ['unsigned' => true, 'default' => 1])]
    private ?int $remainingUses = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expirationAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // Getters & Setters

    public function getId(): int
    {
        return $this->id;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    public function setEntityName(string $entityName): self
    {
        $this->entityName = $entityName;
        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getBehavior(): string
    {
        return $this->behavior;
    }

    public function setBehavior(string $behavior): self
    {
        $this->behavior = $behavior;
        return $this;
    }

    public function getRemainingUses(): ?int
    {
        return $this->remainingUses;
    }

    public function setRemainingUses(?int $remainingUses): self
    {
        $this->remainingUses = $remainingUses;
        return $this;
    }

    public function getExpirationAt(): \DateTimeImmutable
    {
        return $this->expirationAt;
    }

    public function setExpirationAt(\DateTimeImmutable $expirationAt): self
    {
        $this->expirationAt = $expirationAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
