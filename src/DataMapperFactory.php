<?php

declare(strict_types=1);

namespace Solido\DataMapper;

use Solido\DataMapper\Form\OneWayDataMapper;
use Solido\DataMapper\Form\RequestHandler;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataMapperFactory
{
    private FormFactoryInterface $formFactory;
    private RequestHandlerInterface $formRequestHandler;
    private ?TranslatorInterface $translator;

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
    public function createFormBuilderMapper(string $value, $target, array $options = []): DataMapperInterface
    {
        $builder = $this->formFactory->createNamedBuilder('', $value, $target, $options);
        $builder->setDataMapper(new OneWayDataMapper());

        return $this->createFormMapper($builder->getForm());
    }
}
