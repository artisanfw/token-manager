# Token Manager

A utility to generate, store, and redeem tokens in your database.  
Tokens can be configured with an expiration time and a limited number of uses.

## Install
1. Require the package via Composer:
```shell
composer require artisanfw/token-manager
````

2. Add the following table to your database:
```ddl
CREATE TABLE tokens (
   id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
   entity_name VARCHAR(32) NOT NULL,
   entity_id INT(32) UNSIGNED NOT NULL,
   code VARCHAR(32) NOT NULL,
   type VARCHAR(16) NOT NULL,
   behavior VARCHAR(16) NOT NULL,
   remaining_uses TINYINT UNSIGNED NULL DEFAULT '1',
   expiration_at DATETIME NOT NULL,
   created_at DATETIME NOT NULL,
   PRIMARY KEY (id),
   INDEX idx_entity_type (entity_name, entity_id, type),
   INDEX idx_code_type (code, type)
) ENGINE = InnoDB CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

## Usage

### Load configuration
Before using the `TokenManager`, you must initialize it once (typically during application bootstrap):

```php
use Artisan\Managers\TokenManager;

$config = [
    'types' => ['email_validation', 'discount_code', 'pincode', ...],
    'default_code_length' => 16,
    'charset' => [
        'letters' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
        'numbers' => '23456789',
    ],
    'table_name' => 'tokens',
    'repository' => \Artisan\TokenManager\Repositories\DoctrineRepository,
];

TokenManager::load($config);
```

## Create a token
To generate and persist a new token:
```php
use Artisan\Managers\TokenManager;
use Artisan\TokenManager\Models\Token;

$token = TokenManager::i()->create(
    entityName: 'users',
    entityId: 42,
    type: 'email_validation',
    behavior: TokenManager::BEHAVIOR_UNIQUE,
    duration: 3600, //seconds
    maxUses: 1, // 0 = unlimited usage until expiration
    codeLength: 10
);

echo $token->getCode(); // For example: 8KD7PWY3N2
```
### Behavior options:
When invoking the create method, the behavior of the token creation depends on whether an existing token with the same entityName, entityId, and type already exists. The behavior parameter defines how to proceed:

* `BEHAVIOR_ADD`: Always creates a new token (multiple tokens allowed).
* `BEHAVIOR_REPLACE`: Deletes the existing token (if any) and creates a new one.
* `BEHAVIOR_UNIQUE`: Returns the existing token without creating a new one.
* `BEHAVIOR_RENEW`: Updates the expiration time and usage count of the existing token.

### Types
You can define any token type required by your application.
The `type` field accepts a maximum of 16 characters.

## Redeem a token
To validate and consume a token (i.e., if it hasn’t expired and still has remaining uses):

```php
use Artisan\Managers\TokenManager;

$token = TokenManager::i()->redeem(
    code: '8KD7PWY3N2',
    type: 'email_validation',
);

if ($token) {
    echo "Token redeemed, remaining uses: " . $token->getRemainingUses();
} else {
    echo "Token expired or invalid.";
}
```

### Remaining uses
* The remaining usage count is decremented every time `redeem()` is called.
* A token with **infinite uses** will return *null* when `getRemainingUses()` is called.
* A token with **0 remaining uses** will be deleted upon the next `redeem()` attempt and will not be returned.

## Timestamps
Both **created** and **expiration** timestamps are stored in UTC.
