<?php

namespace Artisan\TokenManager\Repositories;

use Artisan\TokenManager\Entities\Token;

interface IRepository
{
    public function find(array $filters): ?Token;

    public function save(Token $token): void;

    public function delete(Token $token): int;

    public function remove(array $filters): int;

    public function normalizeEntityName(string $entityName): string;
}