<?php

declare(strict_types=1);

namespace Solido\DataMapper\Form\DataAccessor;

use Solido\DataMapper\Form\Exception\AccessException;
use Symfony\Component\Form\FormInterface;

use function assert;
use function is_callable;

/**
 * Writes and reads values to/from an object or array using callback functions.
 */
class CallbackAccessor implements DataAccessorInterface
{
    public function getValue(mixed $viewData, FormInterface $form): mixed
    {
        $getter = $form->getConfig()->getOption('getter');
        if ($getter === null) {
            throw new AccessException('Unable to read from the given form data as no getter is defined.');
        }

        assert(is_callable($getter));

        return ($getter)($viewData, $form);
    }

    /**
     * {@inheritdoc}
     */
    public function setValue(mixed &$viewData, $value, FormInterface $form): void
    {
        $setter = $form->getConfig()->getOption('setter');
        if ($setter === null) {
            throw new AccessException('Unable to write the given value as no setter is defined.');
        }

        assert(is_callable($setter));
        ($setter)($viewData, $form->getData(), $form);
    }

    public function isReadable(mixed $viewData, FormInterface $form): bool
    {
        return $form->getConfig()->getOption('getter') !== null;
    }

    public function isWritable(mixed $viewData, FormInterface $form): bool
    {
        return $form->getConfig()->getOption('setter') !== null;
    }
}
