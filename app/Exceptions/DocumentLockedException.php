<?php

namespace App\Exceptions;

class DocumentLockedException extends BusinessRuleException
{
    public function __construct(string $message = '')
    {
        parent::__construct($message ?: __('This document is locked and cannot be modified.'));
    }
}
