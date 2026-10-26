<?php

namespace App\Services\ProductImport;

use RuntimeException;

class ImportException extends RuntimeException
{
    /** @param  bool  $retryable  false for permanent problems (blocked host, 404, no product data) that retrying cannot fix */
    public function __construct(string $message, public readonly bool $retryable = true)
    {
        parent::__construct($message);
    }
}
