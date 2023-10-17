<?php

declare(strict_types=1);

namespace Solido\DataMapper;

use Solido\BodyConverter\BodyConverterInterface;
use Solido\Common\AdapterFactoryInterface;
use Solido\DataMapper\Form\OneWayDataMapper;
use Solido\DataMapper\Form\RequestHandler;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\FormFactory;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormRegistryInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataMapperFactory
{
    private FormRegistryInterface $formRegistry;
    private FormFactoryInterface $formFactory;
    private RequestHandlerInterface $formRequestHandler;
    private TranslatorInterface|null $translator = null;
    private AdapterFactoryInterface|null $adapterFactory = null;
    private BodyConverterInterface|null $bodyConverter = null;
    private PropertyAccessorInterface|null $propertyAccessor = null;
    private ValidatorInterface|null $validator = null;

    public function setFormRegistry(FormRegistryInterface $formRegistry): void
    {
        $this->formRegistry = $formRegistry;
        if (isset($this->formFactory)) {
            return;
        }

        $this->formFactory = new FormFactory($formRegistry);
    }

    public function setFormFactory(FormFactoryInterface $formFactory): void
    {
        $this->formFactory = $formFactory;
    }

    public function setFormRequestHandler(RequestHandlerInterface $handler): void
    {
        $this->formRequestHandler = $handler;
    }

    public function setTranslator(TranslatorInterface|null $translator): void
    {
        $this->translator = $translator;
    }

    public function setAdapterFactory(AdapterFactoryInterface|null $adapterFactory): void
    {
        $this->adapterFactory = $adapterFactory;
    }

    public function setBodyConverter(BodyConverterInterface|null $bodyConverter): void
    {
        $this->bodyConverter = $bodyConverter;
    }

    public function setPropertyAccessor(PropertyAccessorInterface|null $propertyAccessor): void
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    public function setValidator(ValidatorInterface|null $validator): void
    {
        $this->validator = $validator;
    }

    public function createFormMapper(FormInterface $value): DataMapperInterface
    {
        if (! isset($this->formRequestHandler)) {
            $this->formRequestHandler = new RequestHandler();
        }

        return new Form\DataMapper($value, $this->formRequestHandler, $this->translator);
    }

    /** @param array<string, mixed> $options */
    public function createFormBuilderMapper(string $formType, mixed $target, array $options = []): DataMapperInterface
    {
        $defaultOptions = [];
        $formExtensions = $this->formRegistry->getExtensions();
        foreach ($formExtensions as $extension) {
            if (! $extension instanceof CsrfExtension) {
                continue;
            }

            $defaultOptions['csrf_protection'] = false;
        }

        $builder = $this->formFactory->createNamedBuilder('', $formType, $target, $options + $defaultOptions);
        $builder->setDataMapper(new OneWayDataMapper());

        return $this->createFormMapper($builder->getForm());
    }

    /** @param string[] $fields */
    public function createPropertyAccessorMapper(object $target, array $fields): DataMapperInterface
    {
        return new PropertyAccessor\DataMapper(
            $target,
            $fields,
            $this->adapterFactory,
            $this->bodyConverter,
            $this->propertyAccessor,
            $this->validator,
        );
    }
}
