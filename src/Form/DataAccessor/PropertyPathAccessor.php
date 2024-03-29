<?php

declare(strict_types=1);

namespace Solido\DataMapper\Form\DataAccessor;

use DateTimeInterface;
use Solido\DataMapper\Form\Exception\AccessException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\Exception\AccessException as PropertyAccessException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPathInterface;

use function class_exists;
use function is_object;
use function strpos;

/**
 * Writes and reads values to/from an object or array using property path.
 */
class PropertyPathAccessor implements DataAccessorInterface
{
    private PropertyAccessorInterface $propertyAccessor;

    public function __construct(PropertyAccessorInterface|null $propertyAccessor = null)
    {
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
    }

    public function getValue(mixed $viewData, FormInterface $form): mixed
    {
        $propertyPath = $form->getPropertyPath();
        if ($propertyPath === null) {
            throw new AccessException('Unable to read from the given form data as no property path is defined.');
        }

        return $this->getPropertyValue($viewData, $propertyPath);
    }

    public function setValue(mixed &$viewData, mixed $value, FormInterface $form): void
    {
        $propertyPath = $form->getPropertyPath();
        if ($propertyPath === null) {
            throw new AccessException('Unable to write the given value as no property path is defined.');
        }

        // If the field is of type DateTimeInterface and the data is the same skip the update to
        // keep the original object hash
        // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
        if ($value instanceof DateTimeInterface && $value == $this->getPropertyValue($viewData, $propertyPath)) {
            return;
        }

        // If the data is identical to the value in $data, we are
        // dealing with a reference
        if (is_object($viewData) && $form->getConfig()->getByReference() && $value === $this->getPropertyValue($viewData, $propertyPath)) {
            return;
        }

        $this->propertyAccessor->setValue($viewData, $propertyPath, $value);
    }

    public function isReadable(mixed $viewData, FormInterface $form): bool
    {
        return $form->getPropertyPath() !== null;
    }

    public function isWritable(mixed $viewData, FormInterface $form): bool
    {
        return $form->getPropertyPath() !== null;
    }

    /**
     * Returns the value at the end of the property path of the object graph.
     *
     * @param object|mixed[] $data
     */
    private function getPropertyValue(object|array $data, string|PropertyPathInterface $propertyPath): mixed
    {
        try {
            return $this->propertyAccessor->getValue($data, $propertyPath);
        } catch (PropertyAccessException $e) {
            if (
                ! $e instanceof UninitializedPropertyException
                // For versions without UninitializedPropertyException check the exception message
                && (class_exists(UninitializedPropertyException::class) || strpos($e->getMessage(), 'You should initialize it') === false)
            ) {
                throw $e;
            }

            return null;
        }
    }
}
