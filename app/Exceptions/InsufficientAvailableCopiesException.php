<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientAvailableCopiesException extends RuntimeException
{
    public function __construct(public readonly int $copiesOnLoan)
    {
        parent::__construct(
            "Cannot reduce total copies below the {$copiesOnLoan} currently on loan. "
            .'Those copies must be returned first.'
        );
    }
}
