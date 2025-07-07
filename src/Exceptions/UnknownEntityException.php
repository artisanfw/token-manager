<?php

namespace Artisan\TokenManager\Exceptions;

use Exception;

class UnknownEntityException extends Exception {
    protected $message = 'Unknown Entity exception';
}