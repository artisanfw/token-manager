<?php

namespace Artisan\TokenManager\Exceptions;

use Exception;

class TokenRepositoryException extends Exception {
    protected $message = 'Unknown token repository';
}