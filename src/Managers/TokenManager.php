<?php

namespace Artisan\TokenManager\Managers;

use Artisan\Services\Doctrine;
use Artisan\TokenManager\Exceptions\UnknownBehaviorException;
use Artisan\TokenManager\Exceptions\UnknownEntityException;
use Artisan\TokenManager\Exceptions\TokenRepositoryException;
use Artisan\TokenManager\Exceptions\UnknownTypeException;
use Artisan\TokenManager\Entities\Token;
use Artisan\TokenManager\Repositories\IRepository;
use DateInterval;
use DateMalformedIntervalStringException;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Exception;
use InvalidArgumentException;
use Throwable;

/**
 * TokenManager centralizes the creation of validation codes in one place, using a single logic.
 * Any generated code are automatically saved in the database and can be redeemed later.
 * All tokens must be associated to an entity name and their entity id (usually a user id).
 *
 * Examples:
 *   - email validation
 *   - discount codes
 *   - recovery pin
 *   - access tokens
 *   - any other unique link
 */
class TokenManager
{
    private const string CONFIG_ERROR = 'TokenManager requires setup before being used.';

    const string BEHAVIOR_ADD = 'add';
    const string BEHAVIOR_UNIQUE = 'unique';
    const string BEHAVIOR_RENEW = 'renew';
    const string BEHAVIOR_REPLACE = 'replace';

    const array COMMON_CHARSET = [
        'letters' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'numbers' => '0123456789',
    ];

    private static int $defCodeLength = 32;
    private static array $charset = [];

    /** @var string[] */
    private static array $types = [];

    private static bool $isConfigured = false;
    private static ?self $instance = null;
    private static IRepository $repository;


    private function __construct() {
        if (!self::$isConfigured) {
            throw new \LogicException(self::CONFIG_ERROR);
        }
    }

    public static function i(): static
    {
        if (!self::$isConfigured) {
            throw new \LogicException(self::CONFIG_ERROR);
        }

        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Returns the accepted list of behaviors
     * @return string[]
     */
    public static function getBehaviors(): array
    {
        return [
            self::BEHAVIOR_ADD,
            self::BEHAVIOR_UNIQUE,
            self::BEHAVIOR_RENEW,
            self::BEHAVIOR_REPLACE,
        ];
    }

    /**
     * $conf_example = [
     *    'types' => ['email_validation', 'discount_code', 'pin', ...],
     *    'default_code_length' => 32,
     *    'repository' => \Artisan\TokenManager\Repositories\DoctrineRepository
     * ];
     * @throws TokenRepositoryException
     */
    public static function load(array $config): void
    {
        self::$types = array_map(fn($t) => strtolower(trim($t)), $config['types'] ?? []);
        self::$defCodeLength = isset($config['default_code_length']) && is_numeric($config['default_code_length'])
            ? (int) $config['default_code_length']
            : 32;
        self::$charset = $config['charset'] ?? self::COMMON_CHARSET;
        $tablename = $config['table_name'] ?? 'tokens';

        if (empty($config['repository'])) {
            throw new TokenRepositoryException('Unknown token repository');
        }
        $repo = $config['repository'];

        if (is_string($repo)) {
            if (!class_exists($repo)) {
                throw new \InvalidArgumentException("Repository class '$repo' does not exist.");
            }
            $repo = new $repo($tablename);
        }
        if (!$repo instanceof IRepository) {
            throw new \InvalidArgumentException("Repository must implement ".IRepository::class);
        }

        self::$repository = $repo;

        self::$isConfigured = true;
    }

    /**
     * Returns the accepted list of types
     * @return string[]
     */
    public static function getTypes(): array
    {
        return self::$types;
    }

    /**
     * This method creates a security token for general purposes and saves a copy in the database.
     * A token can be recovered with the redeem method.
     *
     * @param string $entityName Associated entity name. You can pass a Doctrine model name or a database name string.
     *                           IE: App\Models\User::class , "users"
     *
     * @param int $entityId     Associated entity id.
     *
     * @param string $type      It allows distinguishing between different types of tokens associated
     *                          with the same userId.
     *
     * @param string $behavior  It determines the behavior when trying to insert a new token
     *                          that matches userId and type with another active token.
     *                          @see constants BEHAVIOR_*
     *                              - ADD: adds a new token no matter how many previously exist.
     *                              - REPLACE: only one is allowed. Delete previous and add a new.
     *                              - UNIQUE: only one is allowed. The second token creation will return the first token.
     *                              - RENEW: only one is allowed, but update expiration datetime and remaining uses.
     *
     * @param int $duration    Period of seconds from now until token expiration.
     * @param int $maxUses     Number of uses before being eliminated.
     *                         By default 0 (unlimited uses until expiration date), max 255
     * @param int $codeLength
     *
     * @return Token
     * @throws UnknownBehaviorException
     * @throws UnknownTypeException
     * @throws DateMalformedStringException
     * @throws DateMalformedIntervalStringException
     * @throws Exception
     */
    public function create(
        string $entityName,
        int $entityId,
        string $type,
        string $behavior,
        int $duration, //seconds
        int $maxUses = 0,
        int $codeLength = 0
    ): Token {
        $entityName = self::$repository->normalizeEntityName($entityName);

        if (!$this->validateType($type)) {
            throw new UnknownTypeException();
        }

        if (!$this->validateBehavior($behavior)) {
            throw new UnknownBehaviorException();
        }

        if ($maxUses < 1) $maxUses = null;

        if ($codeLength < 1) $codeLength = self::$defCodeLength;

        $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiration = $createdAt->add(new DateInterval('PT' . $duration . 'S'));

        $token = $this->getToken(['entity_name' => $entityName, 'entity_id' => $entityId, 'type' => $type]);

        if (is_null($token) || $token->getBehavior() == self::BEHAVIOR_ADD) {
            $token = new Token();
            $token->setEntityName($entityName);
            $token->setEntityId($entityId);
            $token->setType(strtolower($type));
            $token->setBehavior(trim(strtolower($behavior)));
            $token->setCode($this->generateCode($codeLength));
            $token->setRemainingUses($maxUses);
            $token->setCreatedAt($createdAt);
            $token->setExpirationAt($expiration);
            self::$repository->save($token);

        }
        elseif ($token->getBehavior() == self::BEHAVIOR_REPLACE) {
            $token->setCode($this->generateCode($codeLength));
            $token->setRemainingUses($maxUses);
            $token->setExpirationAt($expiration);
            self::$repository->save($token);
        }
        elseif ($token->getBehavior() == self::BEHAVIOR_RENEW) {
            $token->setRemainingUses($maxUses);
            $token->setExpirationAt($expiration);
            self::$repository->save($token);
        }
        //if behavior is unique, simply return the same token

        return $token;
    }

    /**
     * Redeem a token stored in the database only if the token has not expired or it has reached the usage limit.
     * @param string $code
     * @param string $type @see TokenManager::create
     *
     * @return Token|null
     * @throws UnknownTypeException|Throwable
     */
    public function redeem(string $code, string $type): ?Token
    {
        $token = $this->getToken(['code' => $code, 'type' => $type]);

        if (!is_null($token) && !is_null($token->getRemainingUses())) {
            $uses = $token->getRemainingUses();
            if ($uses >= 1) {
                $token->setRemainingUses(--$uses);
                self::$repository->save($token);
            } else {
                self::$repository->delete($token);
                $token = null;
            }
        }
        return $token;
    }




    /**
     * @throws UnknownTypeException
     * @throws DateMalformedStringException
     */
    protected function getToken(array $filters): ?Token
    {
        if (!isset($filters['type']) || !$this->validateType($filters['type'])) {
            throw new UnknownTypeException();
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $token = self::$repository->find($filters);
        if (!$token) return null;

        if ($token->getExpirationAt() <= $now) {
            self::$repository->delete($token);
            return null;
        }

        return $token;
    }

    protected function generateCode(int $codeLength, $allowNumbers = true, $allowLetters = true): string
    {
        if (!$allowNumbers && !$allowLetters) {
            throw new InvalidArgumentException('You must allow at least one type of character: letters or numbers');
        }

        $minimunSecurityRandomLength = 4;
        $codeLength = max($minimunSecurityRandomLength, $codeLength);


        $pool = '';
        if ($allowNumbers) {
            $pool .= self::$charset['numbers'];
        }
        if ($allowLetters) {
            $pool .= self::$charset['letters'];
        }

        $code = '';
        $maxIndex = strlen($pool) - 1;

        for ($i = 0; $i < $codeLength; $i++) {
            $code .= $pool[random_int(0, $maxIndex)];
        }

        return $code;
    }

    protected function validateType(string $type): bool
    {
        return in_array($type, self::getTypes());
    }

    protected function validateBehavior(string $behavior): bool
    {
        return in_array($behavior, self::getBehaviors());
    }
}