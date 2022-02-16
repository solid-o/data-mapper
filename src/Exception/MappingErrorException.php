<?php

declare(strict_types=1);

namespace Solido\DataMapper\Exception;

use RuntimeException;
use Solido\DataMapper\MappingResultInterface;
use Throwable;

class MappingErrorException extends RuntimeException
{
    private MappingResultInterface $result;

    public function __construct(MappingResultInterface $result, string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->result = $result;
    }

    public function getResult(): MappingResultInterface
    {
        return $this->result;
    }
}
