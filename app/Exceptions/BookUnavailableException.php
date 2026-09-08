<?php

namespace App\Exceptions;

use RuntimeException;

class BookUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This book is no longer available to check out.');
    }
}
