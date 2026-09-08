<?php

namespace App\Exceptions;

use RuntimeException;

class AlreadyBorrowedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('You already have an active loan for this book. Return it before borrowing again.');
    }
}
