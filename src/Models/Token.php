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
    private string $entity_name;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $entityId;

    #[ORM\Column(type: 'string', length: 32)]
    private string $code;

    #[ORM\Column(type: 'string', length: 16)]
    private string $type;

    #[ORM\Column(type: 'string', length: 16)]
    private string $behavior;

    #[ORM\Column(type: 'smallint', nullable: true, options: ['unsigned' => true, 'default' => 1])]
    private ?int $remaining_uses = 1;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $expiration_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    // Getters & Setters

    public function getId(): int
    {
        return $this->id;
    }

    public function getEntityName(): string
    {
        return $this->entity_name;
    }

    public function setEntityName(string $entityName): self
    {
        $this->entity_name = $entityName;
        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entity_id;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entity_id = $entityId;
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
        return $this->remaining_uses;
    }

    public function setRemainingUses(?int $remainingUses): self
    {
        $this->remaining_uses = $remainingUses;
        return $this;
    }

    public function getExpirationAt(): \DateTimeImmutable
    {
        return $this->expiration_at;
    }

    public function setExpirationAt(\DateTimeImmutable $expirationAt): self
    {
        $this->expiration_at = $expirationAt;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->created_at = $createdAt;
        return $this;
    }
}
