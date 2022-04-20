<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Fixtures;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * @Assert\Callback("validateObject")
 */
class ChildClass
{
    /**
     * @Assert\Valid()
     */
    public $child;
    private bool $valid;

    public function __construct(bool $valid)
    {
        $this->valid = $valid;
    }

    public function validateObject(ExecutionContextInterface $context): void
    {
        if ($this->valid) {
            return;
        }

        $context->addViolation('This is not valid');
    }
}
