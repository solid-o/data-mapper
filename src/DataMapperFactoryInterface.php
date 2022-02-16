<?php

declare(strict_types=1);

namespace Solido\DataMapper;

interface DataMapperFactoryInterface
{
    /**
     * Create a data mapper for the given value.
     *
     * @param mixed $value
     */
    public function createMapper($value): DataMapperInterface;
}
