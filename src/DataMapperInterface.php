<?php

declare(strict_types=1);

namespace Solido\DataMapper;

use Solido\DataMapper\Exception\MappingErrorException;

interface DataMapperInterface
{
    /**
     * Map a request to the target object.
     *
     * @throws MappingErrorException if mapping fails because of invalid data.
     */
    public function map(object $request): void;
}
