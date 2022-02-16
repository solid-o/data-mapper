<?php

declare(strict_types=1);

namespace Solido\DataMapper\Form;

use Solido\DataMapper\DataMapperInterface;
use Solido\DataMapper\Exception\MappingErrorException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mapper based on symfony form component.
 */
class DataMapper implements DataMapperInterface
{
    private FormInterface $form;
    private RequestHandlerInterface $requestHandler;
    private ?TranslatorInterface $translator;

    public function __construct(
        FormInterface $form,
        ?RequestHandlerInterface $requestHandler = null,
        ?TranslatorInterface $translator = null
    ) {
        $this->form = $form;
        $this->requestHandler = $requestHandler ?? new RequestHandler();
        $this->translator = $translator;
    }

    public function map(object $request): void
    {
        $this->requestHandler->handleRequest($this->form, $request);
        if (! $this->form->isSubmitted()) {
            $this->form->submit(null, true);
        }

        if (! $this->form->isValid()) {
            throw new MappingErrorException(new MappingResult($this->form, $this->translator), 'Invalid data.');
        }
    }
}
