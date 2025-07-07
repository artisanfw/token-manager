<?php

namespace Artisan\TokenManager\Exceptions;

use Exception;

class UnknownBehaviorException extends Exception {
    protected $message = 'Unknown token behavior';
}