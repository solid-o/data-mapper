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
    public function __construct(
        private readonly FormInterface $form,
        private readonly RequestHandlerInterface $requestHandler = new RequestHandler(),
        private readonly TranslatorInterface|null $translator = null,
    ) {
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
