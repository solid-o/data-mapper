<?php

declare(strict_types=1);

namespace Solido\DataMapper;

interface MappingResultInterface
{
    /**
     * Gets the name of the property the mapper refers to.
     */
    public function getName(): string;

    /**
     * When trying to map an object or an array this will be populated
     * with the mapping results of the sub-fields.
     *
     * @return self[]
     */
    public function getChildren(): array;

    /**
     * The errors on the current field (already translated if applicable).
     *
     * @return string[]
     */
    public function getErrors(): array;
}
