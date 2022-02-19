<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Solido\DataMapper\DataMapperFactory;
use PHPUnit\Framework\TestCase;
use Solido\DataMapper\Form\DataMapper as FormDataMapper;
use Solido\DataMapper\Form\OneWayDataMapper;
use stdClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;

class DataMapperFactoryTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy|FormFactoryInterface
     */
    private ObjectProphecy $formFactory;

    /**
     * @var ObjectProphecy|RequestHandlerInterface
     */
    private ObjectProphecy $requestHandler;

    /**
     * @var ObjectProphecy|TranslatorInterface
     */
    private ObjectProphecy $translator;
    private DataMapperFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new DataMapperFactory();
        $this->factory->setFormFactory(($this->formFactory = $this->prophesize(FormFactoryInterface::class))->reveal());
        $this->factory->setFormRequestHandler(($this->requestHandler = $this->prophesize(RequestHandlerInterface::class))->reveal());
        $this->factory->setTranslator(($this->translator = $this->prophesize(TranslatorInterface::class))->reveal());
    }

    public function testCreateFormDataMapper(): void
    {
        $form = $this->prophesize(FormInterface::class);

        $dataMapper = $this->factory->createFormMapper($form->reveal());
        self::assertInstanceOf(FormDataMapper::class, $dataMapper);
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
}
