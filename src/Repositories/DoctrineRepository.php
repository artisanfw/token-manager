<?php

namespace Artisan\TokenManager\Repositories;

use Artisan\Services\Doctrine;
use Artisan\TokenManager\Entities\Token;
use Artisan\TokenManager\Exceptions\UnknownEntityException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;

class DoctrineRepository implements IRepository
{
    private string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function find(array $filters): ?Token
    {
        $em = Doctrine::i()->getEntityManager();
        $conn = $em->getConnection();
        $qb = $conn->createQueryBuilder();

        $qb->select('*')->from($this->table);

        $this->parseFilters($qb, $filters);

//        foreach ($filters as $field => $value) {
//            $qb->andWhere("$field = :$field")->setParameter($field, $value);
//        }

        $stmt = $qb->executeQuery();
        $row = $stmt->fetchAssociative();

        if (!$row) return null;

        $token = new Token();
        $token->setId($row['id']);
        $token->setEntityName($row['entity_name']);
        $token->setEntityId($row['entity_id']);
        $token->setCode($row['code']);
        $token->setType($row['type']);
        $token->setBehavior($row['behavior']);
        $token->setRemainingUses($row['remaining_uses']);
        $token->setCreatedAt(new DateTimeImmutable($row['created_at'], new DateTimeZone('UTC')));
        $token->setExpirationAt(new DateTimeImmutable($row['expiration_at'], new DateTimeZone('UTC')));

        return $token;
    }

    /**
     * @throws Exception
     */
    public function save(Token $token): void
    {
        $em = Doctrine::i()->getEntityManager();
        $conn = $em->getConnection();
        $qb = $conn->createQueryBuilder();

        if ($token->getId()) {
            $qb->update($this->table)
                ->set('code', ':code')
                ->set('remaining_uses', ':remainingUses')
                ->set('expiration_at', ':expirationAt')
                ->where('id = :id')
                ->setParameters([
                    'code' => $token->getCode(),
                    'remainingUses' => $token->getRemainingUses(),
                    'expirationAt' => $token->getExpirationAt()->format('Y-m-d H:i:s'),
                    'id' => $token->getId(),
                ])
                ->executeStatement();
        } else {
            $qb->insert($this->table)
                ->values([
                    'entity_name' => ':entityName',
                    'entity_id' => ':entityId',
                    'code' => ':code',
                    'type' => ':type',
                    'behavior' => ':behavior',
                    'remaining_uses' => ':remainingUses',
                    'expiration_at' => ':expirationAt',
                    'created_at' => ':createdAt',
                ])
                ->setParameters([
                    'entityName' => $token->getEntityName(),
                    'entityId' => $token->getEntityId(),
                    'code' => $token->getCode(),
                    'type' => $token->getType(),
                    'behavior' => $token->getBehavior(),
                    'remainingUses' => $token->getRemainingUses(),
                    'expirationAt' => $token->getExpirationAt()->format('Y-m-d H:i:s'),
                    'createdAt' => $token->getCreatedAt()->format('Y-m-d H:i:s'),
                ])
                ->executeStatement();

            $token->setId($conn->lastInsertId());
        }
    }

    /**
     * @throws Exception
     */
    public function delete(Token $token): int
    {
        return $this->remove(['id' => $token->getId()]);
    }

    /**
     * @throws Exception
     */
    public function remove(array $filters): int
    {
        if (empty($filters)) {
            throw new InvalidArgumentException('Filters cannot be empty');
        }

        $em = Doctrine::i()->getEntityManager();
        $conn = $em->getConnection();
        $qb = $conn->createQueryBuilder();

        $qb->delete($this->table);

        $this->parseFilters($qb, $filters);

//        foreach ($filters as $field => $value) {
//            $qb->andWhere("$field = :$field")->setParameter($field, $value);
//        }

        return $qb->executeStatement();
    }

    /**
     * This method should extract the table name of the entity passed as parameter.
     *
     * @throws UnknownEntityException
     */
    public function normalizeEntityName(string $entityName): string
    {
        if (empty($entityName)) {
            throw new UnknownEntityException();
        }

        if (str_contains($entityName, '\\')) {
            if (!class_exists($entityName)) {
                throw new UnknownEntityException("Class $entityName does not exist.");
            }

            try {
                $em = Doctrine::i()->getEntityManager();
                return $em->getClassMetadata($entityName)->getTableName();
            } catch (\Throwable $e) {
                throw new UnknownEntityException("Class $entityName is not a valid Doctrine entity.");
            }
        }

        return $entityName;
    }

    /**
     * La finalidad de esta función es pasar lógica compleja como filtros
     * y rellenar el QueryBuilder.
     *
     * Como TokenManager puede usar distintos repositorios
     * y Doctrine solo es uno de ellos,
     * no podemos simplemente pasarle un QueryBuilder
     * en lugar de filtros array.
     *
     * Todos los filtros deben tener el siguiente formato de entrada:
     * 'fieldname' => ['operator', value]
     *
     * Si un filtro tiene el formato:
     *    'fieldname' => value (siendo value algo distinto de un array)
     * se considerará:
     *    'fieldname => ['=', value]
     *
     *
     * Ejemplo:
     * $filters = [
     *    'type' => ['like', '%code%'],
     *    'entity_name => 'products'
     *    'entity_id' => ['>=', 23, '<=', 35],
     *    'OR' => [
     *       'expired_at' => ['<=', DateTimeInmutable],
     *       'remaining_uses => 0
     *    ],
     * ];
     *
     * Consideraciones:
     * - Cuando el valor es una fecha, parsedFilters obtendrá
     *   automáticamente el formato 'Y-m-d H:i:s'
     * - Hay dos keys especiales: 'AND' y 'OR'. Por defecto se considera 'AND'
     */
    private function parseFilters(QueryBuilder $qb, array $filters): void
    {

    }
}