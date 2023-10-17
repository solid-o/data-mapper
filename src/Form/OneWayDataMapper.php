<?php

declare(strict_types=1);

namespace Solido\DataMapper\Form;

use DateTimeInterface;
use Solido\DataTransformers\Exception\TransformationFailedException;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormError;

use function assert;
use function get_debug_type;
use function is_array;
use function is_object;
use function is_scalar;

class OneWayDataMapper implements DataMapperInterface
{
    public function __construct(private readonly DataAccessor\DataAccessorInterface $dataAccessor = new DataAccessor\ChainAccessor([
        new DataAccessor\CallbackAccessor(),
        new DataAccessor\PropertyPathAccessor(),
    ]),)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function mapDataToForms(mixed $viewData, iterable $forms): void
    {
        assert($viewData === null || is_array($viewData) || is_object($viewData));

        $viewData ??= [];
        foreach ($forms as $form) {
            $config = $form->getConfig();

            if (! $config->getCompound() || ! $this->dataAccessor->isReadable($viewData, $form)) {
                continue;
            }

            $form->setData($this->dataAccessor->getValue($viewData, $form));
        }
    }

    /**
     * {@inheritdoc}
     */
    public function mapFormsToData(iterable $forms, mixed &$viewData): void
    {
        if ($viewData === null) {
            return;
        }

        if (! is_array($viewData) && ! is_object($viewData)) {
            throw new UnexpectedTypeException($viewData, 'object, array or empty');
        }

        foreach ($forms as $form) {
            $config = $form->getConfig();

            // Write-back is disabled if the form is not synchronized (transformation failed),
            // if the form was not submitted and if the form is disabled (modification not allowed)
            if (! $config->getMapped() || ! $form->isSubmitted() || ! $form->isSynchronized() || $form->isDisabled() || ! $this->dataAccessor->isWritable($viewData, $form)) {
                continue;
            }

            $propertyValue = $form->getData();

            // If the field is of type DateTimeInterface and the data is the same skip the update to
            // keep the original object hash
            // phpcs:ignore SlevomatCodingStandard.Operators.DisallowEqualOperators.DisallowedEqualOperator
            if ($propertyValue instanceof DateTimeInterface && $propertyValue == $this->dataAccessor->getValue($viewData, $form)) {
                continue;
            }

            try {
                $this->dataAccessor->setValue($viewData, $form->getData(), $form);
            } catch (TransformationFailedException $e) { /* @phpstan-ignore-line */
                $data = $form->getViewData();

                $form->addError(new FormError(
                    $config->getOption('invalid_message'),
                    $config->getOption('invalid_message'),
                    ($config->getOption('invalid_message_parameters') ?? []) + [
                        '{{ value }}' => is_scalar($data) ? $data : get_debug_type($data),
                    ],
                    null,
                    $e,
                ));
            }
        }
    }
}
