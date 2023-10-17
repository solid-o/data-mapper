<?php

declare(strict_types=1);

namespace Solido\DataMapper\Exception;

use RuntimeException;
use Solido\DataMapper\MappingResultInterface;
use Throwable;

class MappingErrorException extends RuntimeException
{
    public function __construct(
        private readonly MappingResultInterface $result,
        string $message = '',
        int $code = 0,
        Throwable|null $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getResult(): MappingResultInterface
    {
        return $this->result;
    }
}
