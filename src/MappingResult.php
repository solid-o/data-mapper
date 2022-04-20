<?php

declare(strict_types=1);

namespace Solido\DataMapper;

class MappingResult implements MappingResultInterface
{
    private string $name;

    /** @var MappingResult[] */
    private array $children;

    /** @var string[] */
    private array $errors;

    /**
     * @param self[] $children
     * @param string[] $errors
     */
    public function __construct(string $name, array $children, array $errors)
    {
        $this->name = $name;
        $this->children = $children;
        $this->errors = $errors;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return self[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * @inheritDoc
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
