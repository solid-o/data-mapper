<?php

declare(strict_types=1);

namespace Solido\DataMapper\Form;

use Solido\DataMapper\MappingResultInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function assert;

class MappingResult implements MappingResultInterface
{
    /** @var self[] */
    private array $children;
    /** @var string[] */
    private array $errors;

    public function __construct(
        private readonly FormInterface $form,
        private readonly TranslatorInterface|null $translator = null,
    ) {
    }

    public function getName(): string
    {
        return $this->form->getName();
    }

    /** @inheritDoc */
    public function getChildren(): array
    {
        if (isset($this->children)) {
            return $this->children;
        }

        $this->children = [];
        foreach ($this->form->all() as $child) {
            $this->children[] = new self($child, $this->translator);
        }

        return $this->children;
    }

    /** @inheritDoc */
    public function getErrors(): array
    {
        if (isset($this->errors)) {
            return $this->errors;
        }

        $this->errors = [];
        foreach ($this->form->getErrors(false, false) as $error) {
            assert($error instanceof FormError);
            $this->errors[] = $this->getErrorMessage($error);
        }

        return $this->errors;
    }

    private function getErrorMessage(FormError $error): string
    {
        if ($this->translator === null) {
            return $error->getMessage();
        }

        if ($error->getMessagePluralization() !== null) {
            return $this->translator->trans(
                $error->getMessageTemplate(),
                ['%count%' => $error->getMessagePluralization()] + $error->getMessageParameters(),
                'validators',
            );
        }

        return $this->translator->trans($error->getMessageTemplate(), $error->getMessageParameters(), 'validators');
    }
}
