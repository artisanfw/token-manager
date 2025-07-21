<?php

namespace Tests\Managers;

use PHPUnit\Framework\TestCase;
use Artisan\TokenManager\Managers\TokenManager;
use Artisan\TokenManager\Entities\Token;
use Artisan\TokenManager\Repositories\IRepository;
use Artisan\TokenManager\Exceptions\UnknownTypeException;
use Artisan\TokenManager\Exceptions\UnknownBehaviorException;
use ReflectionClass;

class TokenManagerTest extends TestCase
{
    private IRepository $repositoryMock;

    protected function setUp(): void
    {
        $this->repositoryMock = $this->createMock(IRepository::class);

        TokenManager::load([
            'types' => ['email_verification', 'discount'],
            'repository' => $this->repositoryMock,
            'table_name' => 'tokens',
        ]);
    }

    public function testCreateWithUniqueBehaviorReturnsSameTokenIfExists(): void
    {
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
        $existingToken->setCode('EXISTING_CODE');
        $existingToken->setCreatedAt(new \DateTimeImmutable('-10 minutes'));
        $existingToken->setExpirationAt(new \DateTimeImmutable('+50 minutes'));

        // Simulamos que el token ya existe en el repositorio
        $this->repositoryMock
            ->method('normalizeEntityName')
            ->willReturn($entityName);

        $this->repositoryMock
            ->method('find')
            ->willReturn($existingToken);

        $manager = TokenManager::i();

        $token = $manager->create($entityName, $entityId, $type, $behavior, $duration);

        // Verificamos que se devuelve el token existente
        $this->assertSame($existingToken, $token);
        $this->assertEquals('EXISTING_CODE', $token->getCode());
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

//    public function testCreateTokenWithAddBehavior()
//    {
//        $token = new Token();
//        $this->repositoryMock
//            ->expects($this->once())
//            ->method('find')
//            ->willReturn(null);
//
//        $this->repositoryMock
//            ->expects($this->once())
//            ->method('save')
//            ->with($this->callback(function ($t) use (&$token) {
//                $token = $t;
//                return $t instanceof Token;
//            }));
//
//        $manager = TokenManager::i();
//
//        $result = $manager->create(
//            'users',
//            1,
//            'email_verification',
//            TokenManager::BEHAVIOR_ADD,
//            3600,
//            1,
//            6
//        );
//
//        $this->assertInstanceOf(Token::class, $result);
//        $this->assertSame($token, $result);
//        $this->assertEquals(6, strlen($result->getCode()));
//    }
//
//    public function testCreateTokenWithUnknownTypeThrowsException()
//    {
//        $this->expectException(UnknownTypeException::class);
//
//        TokenManager::i()->create(
//            'users',
//            1,
//            'invalid_type',
//            TokenManager::BEHAVIOR_ADD,
//            3600
//        );
//    }
//
//    public function testCreateTokenWithUnknownBehaviorThrowsException()
//    {
//        $this->expectException(UnknownBehaviorException::class);
//
//        TokenManager::i()->create(
//            'users',
//            1,
//            'email_verification',
//            'invalid_behavior',
//            3600
//        );
//    }
//
//    public function testRedeemValidTokenReducesUsage()
//    {
//        $token = new Token();
//        $token->setId(1);
//        $token->setRemainingUses(2);
//        $token->setCode('ABC123');
//        $token->setType('email_verification');
//        $token->setBehavior(TokenManager::BEHAVIOR_UNIQUE);
//        $token->setCreatedAt(new \DateTimeImmutable());
//        $token->setExpirationAt((new \DateTimeImmutable())->modify('+1 hour'));
//
//        $this->repositoryMock
//            ->expects($this->once())
//            ->method('find')
//            ->with(['code' => 'ABC123', 'type' => 'email_verification'])
//            ->willReturn($token);
//
//        $this->repositoryMock
//            ->expects($this->once())
//            ->method('save')
//            ->with($this->callback(function (Token $t) {
//                return $t->getRemainingUses() === 1;
//            }));
//
//        $manager = TokenManager::i();
//        $result = $manager->redeem('ABC123', 'email_verification');
//
//        $this->assertInstanceOf(Token::class, $result);
//        $this->assertEquals(1, $result->getRemainingUses());
//    }
//
//    public function testRedeemExpiredTokenReturnsNull()
//    {
//        $token = new Token();
//        $token->setId(1);
//        $token->setRemainingUses(1);
//        $token->setCode('EXPIRED');
//        $token->setType('email_verification');
//        $token->setBehavior(TokenManager::BEHAVIOR_UNIQUE);
//        $token->setCreatedAt(new \DateTimeImmutable('-2 hours'));
//        $token->setExpirationAt(new \DateTimeImmutable('-1 hour'));
//
//        $this->repositoryMock
//            ->method('find')
//            ->willReturn($token);
//
//        $manager = TokenManager::i();
//        $result = $manager->redeem('EXPIRED', 'email_verification');
//
//        $this->assertNull($result);
//    }
//
//    public function testRedeemTokenWithNoRemainingUsesReturnsNull()
//    {
//        $token = new Token();
//        $token->setId(1);
//        $token->setRemainingUses(0);
//        $token->setCode('USEDUP');
//        $token->setType('email_verification');
//        $token->setBehavior(TokenManager::BEHAVIOR_UNIQUE);
//        $token->setCreatedAt(new \DateTimeImmutable());
//        $token->setExpirationAt(new \DateTimeImmutable('+1 hour'));
//
//        $this->repositoryMock
//            ->method('find')
//            ->willReturn($token);
//
//        $manager = TokenManager::i();
//        $result = $manager->redeem('USEDUP', 'email_verification');
//
//        $this->assertNull($result);
//    }
//
//    public function testRedeemNonexistentTokenReturnsNull()
//    {
//        $this->repositoryMock
//            ->method('find')
//            ->willReturn(null);
//
//        $manager = TokenManager::i();
//        $result = $manager->redeem('INVALID', 'email_verification');
//
//        $this->assertNull($result);
//    }
//
//    public function testCreateTokenWithUniqueBehaviorReturnsExistingToken()
//    {
//        $existingToken = new Token();
//        $existingToken->setId(5);
//        $existingToken->setCode('UNIQUECODE');
//        $existingToken->setType('email_verification');
//        $existingToken->setBehavior(TokenManager::BEHAVIOR_UNIQUE);
//        $existingToken->setExpirationAt(new \DateTimeImmutable('+1 hour'));
//        $existingToken->setRemainingUses(3);
//
//        $this->repositoryMock
//            ->method('find')
//            ->willReturn($existingToken);
//
//        $this->repositoryMock
//            ->expects($this->never())
//            ->method('save');
//
//        $manager = TokenManager::i();
//        $result = $manager->create(
//            'users',
//            1,
//            'email_verification',
//            TokenManager::BEHAVIOR_UNIQUE,
//            3600
//        );
//
//        $this->assertSame($existingToken, $result);
//    }




}
