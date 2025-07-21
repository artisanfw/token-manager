<?php

namespace Tests\Managers;

use PHPUnit\Framework\TestCase;
use Artisan\TokenManager\Managers\TokenManager;
use Artisan\TokenManager\Entities\Token;
use Artisan\TokenManager\Repositories\IRepository;
use Artisan\TokenManager\Exceptions\UnknownTypeException;
use Artisan\TokenManager\Exceptions\UnknownBehaviorException;

class TokenManagerTest extends TestCase
{
    private IRepository $repositoryMock;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(IRepository::class);

        TokenManager::load([
            'types' => ['email_validation', 'discount'],
            'repository' => $this->repositoryMock,
            'table_name' => 'tokens',
            'length' => 4
        ]);
    }

    public function testCreateTokenWithUnknownTypeThrowsException()
    {
        $this->expectException(UnknownTypeException::class);

        TokenManager::i()->create(
            'users',
            1,
            'invalid_type',
            TokenManager::BEHAVIOR_ADD,
            3600
        );
    }

    public function testCreateTokenWithUnknownBehaviorThrowsException()
    {
        $this->expectException(UnknownBehaviorException::class);

        TokenManager::i()->create(
            'users',
            1,
            'discount',
            'invalid_behavior',
            3600
        );
    }

    public function testCreateWithUniqueBehaviorReturnsSameTokenIfExists(): void
    {
        $code = '1234';
        $entityName = 'users';
        $entityId = 42;
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_UNIQUE;
        $duration = 3600;

        $existingToken = new Token();
        $existingToken->setEntityName($entityName);
        $existingToken->setEntityId($entityId);
        $existingToken->setType($type);
        $existingToken->setBehavior($behavior);
        $existingToken->setCode($code);
        $existingToken->setCreatedAt(new \DateTimeImmutable('-10 minutes'));
        $existingToken->setExpirationAt(new \DateTimeImmutable('+50 minutes'));

        $this->repositoryMock
            ->method('normalizeEntityName')
            ->willReturn($entityName);

        $this->repositoryMock
            ->method('find')
            ->willReturn($existingToken);

        $manager = TokenManager::i();

        $token = $manager->create($entityName, $entityId, $type, $behavior, $duration);

        $this->assertSame($existingToken, $token);
        $this->assertEquals($code, $token->getCode());
    }

    public function testCreateWithUniqueBehaviorCreatesNewTokenIfNotExists(): void
    {
        $entityName = 'users';
        $entityId = 100;
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_UNIQUE;
        $duration = 1800;

        // No existe token previo
        $this->repositoryMock
            ->method('normalizeEntityName')
            ->willReturn($entityName);

        $this->repositoryMock
            ->method('find')
            ->willReturn(null);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Token::class));

        $manager = TokenManager::i();

        $token = $manager->create($entityName, $entityId, $type, $behavior, $duration);

        $this->assertInstanceOf(Token::class, $token);
        $this->assertEquals($entityName, $token->getEntityName());
        $this->assertEquals($entityId, $token->getEntityId());
        $this->assertEquals($type, $token->getType());
        $this->assertEquals($behavior, $token->getBehavior());
        $this->assertNotEmpty($token->getCode());
    }

    public function testCreateWithAddBehaviorAlwaysCreatesNewToken(): void
    {
        $code = '1234';
        $entityName = 'users';
        $entityId = 123;
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_ADD;
        $duration = 3600;

        $existingToken = new Token();
        $existingToken->setEntityName($entityName);
        $existingToken->setEntityId($entityId);
        $existingToken->setType($type);
        $existingToken->setBehavior($behavior);
        $existingToken->setCode($code);
        $existingToken->setCreatedAt(new \DateTimeImmutable('-10 minutes'));
        $existingToken->setExpirationAt(new \DateTimeImmutable('+50 minutes'));

        $this->repositoryMock
            ->method('normalizeEntityName')
            ->willReturn($entityName);

        $this->repositoryMock
            ->method('find')
            ->willReturn($existingToken);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Token $t) use ($entityId, $type, $code) {
                return $t->getEntityId() === $entityId &&
                    $t->getType() === $type &&
                    $t->getCode() !== $code;
            }));

        $manager = TokenManager::i();
        $newToken = $manager->create($entityName, $entityId, $type, $behavior, $duration);

        $this->assertInstanceOf(Token::class, $newToken);
        $this->assertNotEquals($code, $newToken->getCode());
    }

    public function testCreateWithReplaceBehaviorUpdatesExistingToken(): void
    {
        $entityName = 'users';
        $entityId = 10;
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_REPLACE;
        $duration = 3600;
        $oldCode = '1234';

        $token = new Token();
        $token->setEntityName($entityName);
        $token->setEntityId($entityId);
        $token->setType($type);
        $token->setBehavior($behavior);
        $token->setCode($oldCode);
        $token->setCreatedAt(new \DateTimeImmutable('-10 minutes'));
        $token->setExpirationAt(new \DateTimeImmutable('+50 minutes'));

        $this->repositoryMock
            ->method('normalizeEntityName')
            ->willReturn($entityName);

        $this->repositoryMock
            ->method('find')
            ->willReturn($token);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Token $t) use ($oldCode) {
                return $t->getCode() !== $oldCode;
            }));

        $manager = TokenManager::i();
        $newToken = $manager->create($entityName, $entityId, $type, $behavior, $duration, 5);

        $this->assertInstanceOf(Token::class, $newToken);
        $this->assertNotEquals($oldCode, $newToken->getCode());
        $this->assertEquals(5, $newToken->getRemainingUses());
    }

    public function testCreateWithRenewBehaviorExtendsExpirationAndUsage(): void
    {
        $entityName = 'users';
        $entityId = 5;
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_RENEW;
        $duration = 7200;
        $originalCode = '1234';

        $token = new Token();
        $token->setEntityName($entityName);
        $token->setEntityId($entityId);
        $token->setType($type);
        $token->setBehavior($behavior);
        $token->setCode($originalCode);
        $token->setCreatedAt(new \DateTimeImmutable('-1 hour'));
        $token->setExpirationAt(new \DateTimeImmutable('+1 hour'));

        $this->repositoryMock
            ->method('normalizeEntityName')
            ->willReturn($entityName);

        $this->repositoryMock
            ->method('find')
            ->willReturn($token);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Token $t) use ($originalCode) {
                return $t->getCode() === $originalCode && $t->getRemainingUses() === 10;
            }));

        $manager = TokenManager::i();
        $renewedToken = $manager->create($entityName, $entityId, $type, $behavior, $duration, 10);

        $this->assertInstanceOf(Token::class, $renewedToken);
        $this->assertEquals($originalCode, $renewedToken->getCode());
        $this->assertEquals(10, $renewedToken->getRemainingUses());
    }


    public function testRedeemValidTokenReducesUsage()
    {
        $code = '1234';
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_UNIQUE;

        $token = new Token();
        $token->setId(1);
        $token->setRemainingUses(2);
        $token->setCode($code);
        $token->setType($type);
        $token->setBehavior($behavior);
        $token->setCreatedAt(new \DateTimeImmutable('-10 minutes'));
        $token->setExpirationAt(new \DateTimeImmutable('+50 minutes'));

        $this->repositoryMock
            ->method('find')
            ->willReturn($token);

        $this->repositoryMock
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Token $t) {
                return $t->getRemainingUses() === 1;
            }));

        $manager = TokenManager::i();
        $result = $manager->redeem($token->getCode(), $token->getType());

        $this->assertInstanceOf(Token::class, $result);
        $this->assertEquals(1, $result->getRemainingUses());
    }
//
    public function testRedeemExpiredTokenReturnsNull()
    {
        $code = '1234';
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_UNIQUE;

        $token = new Token();
        $token->setId(1);
        $token->setRemainingUses(2);
        $token->setCode($code);
        $token->setType($type);
        $token->setBehavior($behavior);
        $token->setCreatedAt(new \DateTimeImmutable('-20 minutes'));
        $token->setExpirationAt(new \DateTimeImmutable('-10 minutes'));

        $this->repositoryMock
            ->method('find')
            ->willReturn($token);

        $manager = TokenManager::i();
        $result = $manager->redeem($token->getCode(), $token->getType());

        $this->assertNull($result);
    }

    public function testRedeemTokenWithNoRemainingUsesReturnsNull()
    {
        $code = '1234';
        $type = 'email_validation';
        $behavior = TokenManager::BEHAVIOR_UNIQUE;

        $token = new Token();
        $token->setId(1);
        $token->setRemainingUses(0);
        $token->setCode($code);
        $token->setType($type);
        $token->setBehavior($behavior);
        $token->setCreatedAt(new \DateTimeImmutable('-10 minutes'));
        $token->setExpirationAt(new \DateTimeImmutable('+50 minutes'));

        $this->repositoryMock
            ->method('find')
            ->willReturn($token);

        $manager = TokenManager::i();
        $result = $manager->redeem($token->getCode(), $token->getType());

        $this->assertNull($result);
    }

    public function testRedeemNonexistentTokenReturnsNull()
    {
        $this->repositoryMock
            ->method('find')
            ->willReturn(null);

        $manager = TokenManager::i();
        $result = $manager->redeem('code', 'email_validation');

        $this->assertNull($result);
    }
}
