<?php

namespace Tests\Repositories;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Query\QueryBuilder;
use Artisan\TokenManager\Repositories\DoctrineRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\TestCase;

class DoctrineRepositoryTest extends TestCase
{
    private QueryBuilder $qb;
    private DoctrineRepository $repo;
    private string $table = 'tokens';

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->qb = $connection->createQueryBuilder();
        $this->repo = new DoctrineRepository($this->table);
    }

    /**
     * @throws Exception
     */
    public function testParseFiltersThrowsExceptionOnInvalidOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported operator: ~~");

        $filters = [
            'status' => ['~~', 'invalid'],
        ];

        $this->qb->select('*')->from($this->table);
        $this->repo->parseFilters($this->qb, $filters);
    }

    /**
     * @throws Exception
     */
    public function testParseFiltersThrowsExceptionOnInvalidFilterStructure(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The 'in' operator expects an array.");

        $filters = [
            'status' => ['in'],
        ];

        $this->qb->select('*')->from($this->table);
        $this->repo->parseFilters($this->qb, $filters);
    }

    /**
     * @throws Exception
     */
    public function testParseFiltersWithAllSupportedOperators(): void
    {
        $this->qb->select('*')->from($this->table);

        $filters = [
            'field_eq' => 1, // default "="
            'field_neq' => ['!=', 2],
            'field_neq_alt' => ['<>', 3],
            'field_lt' => ['<', 4],
            'field_lte' => ['<=', 5],
            'field_gt' => ['>', 6],
            'field_gte' => ['>=', 7],
            'field_like' => ['like', '%abc%'],
            'field_in' => ['in', [1, 2, 3]],
            'field_not_in' => ['not in', [4, 5, 6]],
            'field_null' => ['is null'],
            'field_not_null' => ['is not null'],
            'field_between' => ['between', 10, 20],
            'AND' => [
                'nested1' => ['>=', 100],
                'nested2' => ['<=', 200],
            ],
            'OR' => [
                'nested_or_1' => 999,
                'nested_or_2' => ['like', '%xyz%']
            ]
        ];

        $this->repo->parseFilters($this->qb, $filters);
        $sql = $this->qb->getSQL();

        $this->assertStringContainsString('field_eq =', $sql);
        $this->assertStringContainsString('field_neq <>', $sql);
        $this->assertStringContainsString('field_neq_alt <>', $sql);
        $this->assertStringContainsString('field_lt <', $sql);
        $this->assertStringContainsString('field_lte <=', $sql);
        $this->assertStringContainsString('field_gt >', $sql);
        $this->assertStringContainsString('field_gte >=', $sql);
        $this->assertStringContainsString('field_like LIKE', $sql);
        $this->assertMatchesRegularExpression('/field_in IN\s*\(.+?,.+?,.+?\)/i', $sql);
        $this->assertMatchesRegularExpression('/field_not_in NOT IN\s*\(.+?,.+?,.+?\)/i', $sql);
        $this->assertStringContainsString('field_null IS NULL', $sql);
        $this->assertStringContainsString('field_not_null IS NOT NULL', $sql);
        $this->assertMatchesRegularExpression('/field_between >= :.*_from/i', $sql);
        $this->assertMatchesRegularExpression('/field_between <= :.*_to/i', $sql);
        $this->assertStringContainsString('nested1 >=', $sql);
        $this->assertStringContainsString('nested2 <=', $sql);
        $this->assertStringContainsString('(nested_or_1 =', $sql);
        $this->assertStringContainsString('nested_or_2 LIKE', $sql);
    }

}