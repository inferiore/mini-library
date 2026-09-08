<?php

namespace App\Exceptions;

use RuntimeException;

class BookHasActiveLoansException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This book cannot be deleted while it has active (unreturned) loans.');
    }
}
