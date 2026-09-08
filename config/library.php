<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Loan Period
    |--------------------------------------------------------------------------
    |
    | How many days a checked-out book is due for. Used by LoanService to
    | compute a loan's due_at at checkout time. Kept in config (not hardcoded
    | inline) so it can be tuned without a code change — spec 005.
    |
    */

    'loan_period_days' => (int) env('LOAN_PERIOD_DAYS', 14),

];
