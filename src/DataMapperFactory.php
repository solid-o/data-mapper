<?php

declare(strict_types=1);

namespace Solido\DataMapper;

use Solido\DataMapper\Exception\InvalidArgumentException;
use Solido\DataMapper\Form\RequestHandler;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataMapperFactory implements DataMapperFactoryInterface
{
    private RequestHandlerInterface $formRequestHandler;
    private ?TranslatorInterface $translator;

    public function setFormRequestHandler(RequestHandlerInterface $handler): void
    {
        $this->formRequestHandler = $handler;
    }

    public function setTranslator(?TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @inheritDoc
     */
    public function createMapper($value): DataMapperInterface
    {
        if ($value instanceof FormInterface) {
            return $this->createFormMapper($value);
        }

        throw new InvalidArgumentException('Cannot create a valid data mapper for the given value.');
    }

    private function createFormMapper(FormInterface $value): DataMapperInterface
    {
        if (! isset($this->formRequestHandler)) {
            $this->formRequestHandler = new RequestHandler();
        }

        return new Form\DataMapper($value, $this->formRequestHandler, $this->translator);
    }
}
