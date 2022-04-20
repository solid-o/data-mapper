<?php

declare(strict_types=1);

namespace Solido\DataMapper;

use Solido\BodyConverter\BodyConverterInterface;
use Solido\Common\AdapterFactoryInterface;
use Solido\DataMapper\Form\OneWayDataMapper;
use Solido\DataMapper\Form\RequestHandler;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataMapperFactory
{
    private FormFactoryInterface $formFactory;
    private RequestHandlerInterface $formRequestHandler;
    private ?TranslatorInterface $translator;
    private ?AdapterFactoryInterface $adapterFactory = null;
    private ?BodyConverterInterface $bodyConverter = null;
    private ?PropertyAccessorInterface $propertyAccessor = null;
    private ?ValidatorInterface $validator = null;

    public function setFormFactory(FormFactoryInterface $formFactory): void
    {
        $this->formFactory = $formFactory;
    }

    public function setFormRequestHandler(RequestHandlerInterface $handler): void
    {
        $this->formRequestHandler = $handler;
    }

    public function setTranslator(?TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function setAdapterFactory(?AdapterFactoryInterface $adapterFactory): void
    {
        $this->adapterFactory = $adapterFactory;
    }

    public function setBodyConverter(?BodyConverterInterface $bodyConverter): void
    {
        $this->bodyConverter = $bodyConverter;
    }

    public function setPropertyAccessor(?PropertyAccessorInterface $propertyAccessor): void
    {
        $this->propertyAccessor = $propertyAccessor;
    }

    public function setValidator(?ValidatorInterface $validator): void
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

    /**
     * @param mixed $target
     * @param array<string, mixed> $options
     */
    public function createFormBuilderMapper(string $formType, $target, array $options = []): DataMapperInterface
    {
        $builder = $this->formFactory->createNamedBuilder('', $formType, $target, $options);
        $builder->setDataMapper(new OneWayDataMapper());

        return $this->createFormMapper($builder->getForm());
    }

    /**
     * @param string[] $fields
     */
    public function createPropertyAccessorMapper(object $target, array $fields): DataMapperInterface
    {
        return new PropertyAccessor\DataMapper(
            $target,
            $fields,
            $this->adapterFactory,
            $this->bodyConverter,
            $this->propertyAccessor,
            $this->validator
        );
    }
}
