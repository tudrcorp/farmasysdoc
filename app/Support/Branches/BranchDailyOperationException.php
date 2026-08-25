<?php

namespace App\Support\Branches;

use InvalidArgumentException;

final class BranchDailyOperationException extends InvalidArgumentException
{
    /**
     * @param  list<string>  $openCashBoxLabels
     */
    public function __construct(
        string $message,
        public readonly array $openCashBoxLabels = [],
    ) {
        parent::__construct($message);
    }
}
