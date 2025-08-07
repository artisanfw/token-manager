<?php

namespace Artisan\TokenManager\Repositories;

use Artisan\Services\Doctrine;
use Artisan\TokenManager\Entities\Token;
use Artisan\TokenManager\Exceptions\UnknownEntityException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;
use Throwable;

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
            } catch (Throwable) {
                throw new UnknownEntityException("Class $entityName is not a valid Doctrine entity.");
            }
        }

        return $entityName;
    }

    /**
     * The purpose of this function is to handle complex logic such as filters.
     *
     * All filters must follow the input format:
     * 'fieldname' => ['operator', value]
     *
     * If a filter is in the format:
     *    'fieldname' => value (where value is not an array)
     * it will be interpreted as:
     *    'fieldname' => ['=', value]
     *
     *
     * Example for WHERE type LIKE '%code%' AND entity_name = 'products' AND entity_id >= 23 AND entity_id <= 35 AND (expired_at <= 'date_here' OR remaining_uses = 0)
     *
     * $filters = [
     *    'type' => ['like', '%code%'],
     *    'entity_name' => 'products',
     *    'entity_id' => ['>=', 23, '<=', 35],
     *    'OR' => [
     *       'expired_at' => ['<=', DateTimeImmutable],
     *       'remaining_uses' => 0
     *    ],
     * ];
     *
     * Considerations:
     * - When the value is a date, parsedFilters will automatically use the format 'Y-m-d H:i:s'
     * - There are two special keys: 'AND' and 'OR'. 'AND' is the default
     */
    public function parseFilters(QueryBuilder $qb, array $filters): void
    {
        $where = $this->buildFiltersRecursive($qb, $filters);
        if ($where !== null) {
            $qb->andWhere($where);
        }
    }

    private function buildFiltersRecursive(QueryBuilder $qb, array $filters, string $logic = 'AND'): ?string
    {
        $expr = $qb->expr();
        $conditions = [];

        foreach ($filters as $key => $value) {
            $upperKey = strtoupper($key);
            if ($upperKey === 'AND' || $upperKey === 'OR') {
                $nested = $this->buildFiltersRecursive($qb, $value, $upperKey);
                if ($nested) {
                    $conditions[] = $nested;
                }
            } else {
                $conditions[] = $this->buildCondition($qb, $key, $value, $logic);
            }
        }

        if (empty($conditions)) {
            return null;
        }

        return count($conditions) === 1
            ? $conditions[0]
            : call_user_func_array([$expr, strtolower($logic)], $conditions);
    }

    private function buildCondition(QueryBuilder $qb, string $field, mixed $value, string $logic = 'AND'): string
    {
        $expr = $qb->expr();
        $conditions = [];

        if (!is_array($value)) {
            $value = ['=', $value];
        }

        $i = 0;
        while ($i < count($value)) {
            $operator = strtolower($value[$i]);
            $paramKey = uniqid(str_replace('.', '_', $field) . '_');

            $binaryOperators = ['=', '!=', '<>', '<', '<=', '>', '>=', 'like'];

            if (in_array($operator, $binaryOperators, true)) {
                if (!isset($value[$i + 1])) {
                    throw new \InvalidArgumentException("Operator '$operator' expects a value.");
                }

                $val = $value[++$i];
                if ($val instanceof \DateTimeInterface) {
                    $val = $val->format('Y-m-d H:i:s');
                }

                $qb->setParameter($paramKey, $val);

                $conditions[] = match ($operator) {
                    '=', '==' => $expr->eq($field, ":$paramKey"),
                    '!=', '<>' => $expr->neq($field, ":$paramKey"),
                    '<' => $expr->lt($field, ":$paramKey"),
                    '<=' => $expr->lte($field, ":$paramKey"),
                    '>' => $expr->gt($field, ":$paramKey"),
                    '>=' => $expr->gte($field, ":$paramKey"),
                    'like' => $expr->like($field, ":$paramKey"),
                };

                $i++;
            } elseif ($operator === 'in' || $operator === 'not in') {
                if (!isset($value[$i + 1])) {
                    throw new \InvalidArgumentException("The '$operator' operator expects an array.");
                }

                $val = $value[++$i];

                if (!is_array($val)) {
                    throw new \InvalidArgumentException("The '$operator' operator expects an array.");
                }

                $placeholders = [];
                foreach ($val as $idx => $item) {
                    $subKey = $paramKey . "_$idx";
                    if ($item instanceof \DateTimeInterface) {
                        $item = $item->format('Y-m-d H:i:s');
                    }
                    $qb->setParameter($subKey, $item);
                    $placeholders[] = ":$subKey";
                }

                $conditions[] = $operator === 'in'
                    ? $expr->in($field, implode(', ', $placeholders))
                    : $expr->notIn($field, implode(', ', $placeholders));

                $i++;
            } elseif ($operator === 'is null') {
                $conditions[] = $expr->isNull($field);
                $i++;
            } elseif ($operator === 'is not null') {
                $conditions[] = $expr->isNotNull($field);
                $i++;
            } elseif ($operator === 'between') {
                // Aquí estamos asegurándonos de que haya al menos dos valores después del operador "between"
                if (count($value) < 3) {
                    throw new InvalidArgumentException("The 'between' operator expects two values.");
                }

                // Asegúrese de avanzar correctamente en el índice y obtener los valores del "between"
                $val1 = $value[++$i]; // primer valor
                $val2 = $value[++$i]; // segundo valor

                // Generamos claves de parámetro únicas para los valores del "between"
                $paramKey1 = $paramKey . '_from';
                $paramKey2 = $paramKey . '_to';

                // Convertir las fechas si son instancias de DateTime
                if ($val1 instanceof DateTimeInterface) {
                    $val1 = $val1->format('Y-m-d H:i:s');
                }
                if ($val2 instanceof DateTimeInterface) {
                    $val2 = $val2->format('Y-m-d H:i:s');
                }

                // Asignar parámetros al query builder
                $qb->setParameter($paramKey1, $val1);
                $qb->setParameter($paramKey2, $val2);

                // Crear la condición "between"
                $conditions[] = $expr->and(
                    $expr->gte($field, ":$paramKey1"),
                    $expr->lte($field, ":$paramKey2")
                );
                $i++; // No olvidar avanzar el índice después de manejar ambos valores del "between"
            } else {
                throw new \InvalidArgumentException("Unsupported operator: $operator");
            }
        }

        return count($conditions) === 1
            ? $conditions[0]
            : ($logic === 'AND'
                ? $expr->and(...$conditions)
                : $expr->or(...$conditions));
    }

}