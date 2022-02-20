<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Solido\DataMapper\Form\MappingResult;
use Solido\DataMapper\Form\OneWayDataMapper;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\Test\FormIntegrationTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MappingResultTest extends FormIntegrationTestCase
{
    use ProphecyTrait;

    public function testMappingResultShouldWork(): void
    {
        $form = $this->factory->createNamedBuilder('', FormType::class, new DummyPerson('test'), [
            'data_class' => DummyPerson::class,
        ])
            ->setDataMapper(new OneWayDataMapper())
            ->add('name')
            ->add('birthDate')
            ->add('extraData')
            ->add('age')
            ->getForm();

        $form->addError(new FormError('Extra data', 'Extra data {{ value }}', ['{{ value }}' => 'foobar']));
        $form->get('name')->addError(new FormError('invalid'));
        $form->get('age')->addError(new FormError('invalid age', 'invalid age', [], 12));

        $translator = $this->prophesize(TranslatorInterface::class);
        $translator->trans('Extra data {{ value }}', ['{{ value }}' => 'foobar'], 'validators')->willReturn('Translated foobar');
        $translator->trans('invalid age', ['%count%' => 12], 'validators')->willReturn('Translated age');
        $translator->trans(Argument::cetera())->willReturnArgument(0);

        $result = new MappingResult($form, $translator->reveal());
        self::assertSame(['Translated foobar'], $result->getErrors());
        self::assertCount(4, $result->getChildren());
        self::assertSame(['invalid'], $result->getChildren()[0]->getErrors());
        self::assertSame(['Translated age'], $result->getChildren()[3]->getErrors());
    }
}
