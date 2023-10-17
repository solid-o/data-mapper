<?php

declare(strict_types=1);

namespace Solido\DataMapper\Form\DataAccessor;

use Solido\DataMapper\Form\Exception\AccessException;
use Symfony\Component\Form\FormInterface;

class ChainAccessor implements DataAccessorInterface
{
    /** @param iterable<DataAccessorInterface> $accessors */
    public function __construct(private readonly iterable $accessors)
    {
    }

    public function getValue(mixed $viewData, FormInterface $form): mixed
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isReadable($viewData, $form)) {
                return $accessor->getValue($viewData, $form);
            }
        }

        throw new AccessException('Unable to read from the given form data as no accessor in the chain is able to read the data.');
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(mixed &$viewData, $value, FormInterface $form): void
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isWritable($viewData, $form)) {
                $accessor->setValue($viewData, $value, $form);

                return;
            }
        }

        throw new AccessException('Unable to write the given value as no accessor in the chain is able to set the data.');
    }

    public function isReadable(mixed $viewData, FormInterface $form): bool
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isReadable($viewData, $form)) {
                return true;
            }
        }

        return false;
    }

    public function isWritable(mixed $viewData, FormInterface $form): bool
    {
        foreach ($this->accessors as $accessor) {
            if ($accessor->isWritable($viewData, $form)) {
                return true;
            }
        }

        return false;
    }
}
