<?php

declare(strict_types=1);

namespace Solido\DataMapper;

class MappingResult implements MappingResultInterface
{
    /**
     * @param self[] $children
     * @param string[] $errors
     */
    public function __construct(
        private readonly string $name,
        private readonly array $children,
        private readonly array $errors,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /** @return self[] */
    public function getChildren(): array
    {
        return $this->children;
    }

    /** @inheritDoc */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
