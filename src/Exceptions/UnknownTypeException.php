<?php

namespace Artisan\TokenManager\Exceptions;

use Exception;

class UnknownTypeException extends Exception {
    protected $message = 'Unknown token type';
}