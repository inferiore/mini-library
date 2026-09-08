<?php

namespace App\Exceptions;

use RuntimeException;

class LoanAlreadyReturnedException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This loan has already been returned.');
    }
}
