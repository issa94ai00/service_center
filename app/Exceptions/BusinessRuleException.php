<?php

namespace App\Exceptions;

use Exception;

class BusinessRuleException extends Exception
{
    public function __construct(string $message, public readonly ?string $field = null)
    {
        parent::__construct($message);
    }
}
