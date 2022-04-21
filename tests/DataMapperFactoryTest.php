<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Solido\BodyConverter\BodyConverterInterface;
use Solido\Common\AdapterFactoryInterface;
use Solido\DataMapper\DataMapperFactory;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\DataMapper\Form\DataMapper as FormDataMapper;
use Solido\DataMapper\Form\OneWayDataMapper;
use Solido\DataMapper\PropertyAccessor\DataMapper as PropertyAccessorMapper;
use stdClass;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormErrorIterator;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataMapperFactoryTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy|FormFactoryInterface */
    private ObjectProphecy $formFactory;

    /** @var ObjectProphecy|RequestHandlerInterface */
    private ObjectProphecy $requestHandler;

    /** @var ObjectProphecy|TranslatorInterface */
    private ObjectProphecy $translator;
    private DataMapperFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DataMapperFactory();
        $this->factory->setFormFactory(($this->formFactory = $this->prophesize(FormFactoryInterface::class))->reveal());
        $this->factory->setFormRequestHandler(($this->requestHandler = $this->prophesize(RequestHandlerInterface::class))->reveal());
        $this->factory->setTranslator(($this->translator = $this->prophesize(TranslatorInterface::class))->reveal());
        $this->factory->setAdapterFactory(($this->adapterFactory = $this->prophesize(AdapterFactoryInterface::class))->reveal());
        $this->factory->setBodyConverter(($this->bodyConverter = $this->prophesize(BodyConverterInterface::class))->reveal());
        $this->factory->setPropertyAccessor(($this->propertyAccessor = $this->prophesize(PropertyAccessorInterface::class))->reveal());
        $this->factory->setValidator(($this->validator = $this->prophesize(ValidatorInterface::class))->reveal());
    }

    public function testCreateFormDataMapper(): void
    {
        $form = $this->prophesize(FormInterface::class);
        $form->isSubmitted()->willReturn(true);
        $form->isValid()->willReturn(false);
        $form->getErrors(false, false)->willReturn(new FormErrorIterator($form->reveal(), [new FormError('error')]));

        $this->translator
            ->trans('error', Argument::cetera())
            ->shouldBeCalled()
            ->willReturn('translated error');

        $dataMapper = $this->factory->createFormMapper($form->reveal());
        self::assertInstanceOf(FormDataMapper::class, $dataMapper);

        $this->requestHandler
            ->handleRequest(Argument::any(), $request = new Request())
            ->shouldBeCalled();

        $result = null;
        try {
            $dataMapper->map($request);
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['translated error'], $result->getErrors());
    }

    public function testCreateFormBuilderDataMapper(): void
    {
        $obj = new stdClass();
        $this->formFactory->createNamedBuilder('', FormType::class, $obj, [])
            ->shouldBeCalled()
            ->willReturn($builder = $this->prophesize(FormBuilderInterface::class));

        $builder->setDataMapper(Argument::type(OneWayDataMapper::class))->shouldBeCalled()->willReturn($builder);
        $builder->getForm()->willReturn($this->prophesize(FormInterface::class));

        $dataMapper = $this->factory->createFormBuilderMapper(FormType::class, $obj);
        self::assertInstanceOf(FormDataMapper::class, $dataMapper);
    }

    public function testCreatePropertyAccessorMapper(): void
    {
        $obj = new stdClass();
        $dataMapper = $this->factory->createPropertyAccessorMapper($obj, []);
        self::assertInstanceOf(PropertyAccessorMapper::class, $dataMapper);
    }
}

