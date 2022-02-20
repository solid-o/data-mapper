<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form;

use ArrayIterator;
use DateTime;
use DateTimeImmutable;
use LogicException;
use Prophecy\PhpUnit\ProphecyTrait;
use Solido\DataMapper\Form\CollectionType;
use Solido\DataMapper\Form\DataAccessor\DataAccessorInterface;
use Solido\DataMapper\Form\OneWayDataMapper;
use Solido\DataTransformers\Exception\TransformationFailedException;
use stdClass;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigBuilder;
use Symfony\Component\Form\Test\Traits\ValidatorExtensionTrait;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\PropertyAccess\PropertyPath;

use function get_class;

class OneWayDataMapperTest extends TypeTestCase
{
    use ProphecyTrait;
    use ValidatorExtensionTrait;

    private OneWayDataMapper $mapper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mapper = new OneWayDataMapper();
    }

    protected function getTypeExtensions(): array
    {
        return [
            new class extends AbstractTypeExtension {
                public static function getExtendedTypes(): iterable
                {
                    yield FormType::class;
                }

                public function buildForm(FormBuilderInterface $builder, array $options): void
                {
                    $builder->setDataMapper(new OneWayDataMapper());
                }
            },
        ];
    }

    public function testShouldMapDataToFormsShouldSetDefaultDataIfViewDataIsEmpty(): void
    {
        $config = new FormConfigBuilder('name', null, $this->dispatcher, []);
        $config->setData('foo');

        $form = new SubmittedForm($config);
        $this->mapper->mapDataToForms(null, new ArrayIterator([$form]));

        self::assertEquals('foo', $form->getData());
    }

    public function testShouldMapDataToFormsShouldSetDefaultDataIfNotMapped(): void
    {
        $config = new FormConfigBuilder('name', null, $this->dispatcher, []);
        $config->setData('foo');
        $config->setMapped(false);

        $form = new SubmittedForm($config);
        $this->mapper->mapDataToForms(['path' => 'bar'], new ArrayIterator([$form]));

        self::assertEquals('foo', $form->getData());
    }

    public function testShouldMapDataToFormsShouldSetDataIfFormIsCompound(): void
    {
        $config = new FormConfigBuilder('name', null, $this->dispatcher, []);
        $config->setData('foo');
        $config->setPropertyPath(new PropertyPath('[path]'));
        $config->setCompound(true);
        $config->setDataMapper($this->mapper);

        $form = new SubmittedForm($config);
        $this->mapper->mapDataToForms(['path' => 'bar'], new ArrayIterator([$form]));

        self::assertEquals('bar', $form->getData());
    }

    public function testShouldNotSetDataToNonCompoundForm(): void
    {
        $form = $this->factory
            ->createNamedBuilder('', FormType::class, ['foo' => 'bar', 'bar' => 'foo'])
            ->add('foo')
            ->add('bar')
            ->getForm();

        self::assertNull($form->get('foo')->getData());
        self::assertNull($form->get('bar')->getData());
    }

    public function testShouldNotSetDataToCompoundForm(): void
    {
        $form = $this->factory
            ->createNamedBuilder('', FormType::class, [
                'foo' => 'bar',
                'bar' => 'foo',
                'bbuz' => [
                    'foobar',
                    'barbar',
                ],
                'baz' => ['barbaz' => 0],
            ])
            ->add('foo')
            ->add('bar')
            ->add('bbuz', CollectionType::class, [
                'entry_type' => TextType::class,
            ])
            ->add($this->builder->create('baz', FormType::class)->add('barbaz'))
            ->getForm();

        self::assertNull($form->get('foo')->getData());
        self::assertNull($form->get('bar')->getData());
        self::assertEquals(['barbaz' => 0], $form->get('baz')->getData());
        self::assertNull($form->get('baz')->get('barbaz')->getData());
        self::assertEquals([
            'foobar',
            'barbar',
        ], $form->get('bbuz')->getData());
    }

    public function testMapFormsToDataShouldThrowIfNotArrayOrObject(): void
    {
        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $form = new SubmittedForm($config);

        $this->expectException(UnexpectedTypeException::class);
        $this->expectExceptionMessage('Expected argument of type "object, array or empty", "string" given');

        $data = 'foo';
        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $data);
    }

    public function testMapFormsToDataWritesBackIfNotByReference(): void
    {
        $car = new stdClass();
        $car->engine = new stdClass();
        $engine = new stdClass();
        $engine->brand = 'Rolls-Royce';
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(false);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertEquals($engine, $car->engine);
        self::assertNotSame($engine, $car->engine);
    }

    public function testMapFormsToDataWritesBackIfByReferenceButNoReference(): void
    {
        $car = new stdClass();
        $car->engine = new stdClass();
        $engine = new stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame($engine, $car->engine);
    }

    public function testMapFormsToDataWritesBackIfByReferenceAndReference(): void
    {
        $car = new stdClass();
        $car->engine = 'BMW';
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('engine', null, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData('Rolls-Royce');
        $form = new SubmittedForm($config);

        $car->engine = 'Rolls-Royce';

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame('Rolls-Royce', $car->engine);
    }

    public function testMapFormsToDataIgnoresUnmapped(): void
    {
        $initialEngine = new stdClass();
        $car = new stdClass();
        $car->engine = $initialEngine;
        $engine = new stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $config->setMapped(false);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresUnsubmittedForms(): void
    {
        $initialEngine = new stdClass();
        $car = new stdClass();
        $car->engine = $initialEngine;
        $engine = new stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new Form($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresEmptyData(): void
    {
        $initialEngine = new stdClass();
        $car = new stdClass();
        $car->engine = $initialEngine;
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData(null);
        $form = new Form($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresUnsynchronized(): void
    {
        $initialEngine = new stdClass();
        $car = new stdClass();
        $car->engine = $initialEngine;
        $engine = new stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $form = new NotSynchronizedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToDataIgnoresDisabled(): void
    {
        $initialEngine = new stdClass();
        $car = new stdClass();
        $car->engine = $initialEngine;
        $engine = new stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $config->setDisabled(true);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame($initialEngine, $car->engine);
    }

    public function testMapFormsToData(): void
    {
        $initialEngine = new stdClass();
        $builtAt = new DateTimeImmutable('2022-02-22T22:30:00Z');
        $car = new stdClass();
        $car->engine = $initialEngine;
        $car->builtAt = $builtAt;
        $car->soldAt = null;
        $car->constructor = null;
        $engine = new stdClass();
        $propertyPath = new PropertyPath('engine');

        $config = new FormConfigBuilder('name', stdClass::class, $this->dispatcher);
        $config->setByReference(true);
        $config->setPropertyPath($propertyPath);
        $config->setData($engine);
        $config->setDisabled(true);
        $disabled = new SubmittedForm($config);

        $config = new FormConfigBuilder('constructor', null, $this->dispatcher);
        $config->setPropertyPath(new PropertyPath('constructor'));
        $config->setData('BMW');
        $constructor = new SubmittedForm($config);

        $config = new FormConfigBuilder('builtAt', null, $this->dispatcher);
        $config->setPropertyPath(new PropertyPath('builtAt'));
        $config->setData(new DateTime('2022-02-22T22:30:00Z'));
        $builtAtForm = new SubmittedForm($config);

        $config = new FormConfigBuilder('soldAt', null, $this->dispatcher);
        $config->setPropertyPath(new PropertyPath('soldAt'));
        $config->setData(new DateTime('2022-02-23T11:30:00Z'));
        $soldAtForm = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$disabled, $builtAtForm, $soldAtForm, $constructor]), $car);

        self::assertSame($initialEngine, $car->engine);
        self::assertSame('BMW', $car->constructor);
        self::assertSame($builtAt, $car->builtAt);
        self::assertEquals(new DateTime('2022-02-23T11:30:00Z'), $car->soldAt);
    }

    public function testMapFormsToUninitializedProperties(): void
    {
        $car = new TypehintedPropertiesCar();
        $config = new FormConfigBuilder('engine', null, $this->dispatcher);
        $config->setData('BMW');
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $car);

        self::assertSame('BMW', $car->engine);
    }

    /**
     * @dataProvider provideDate
     */
    public function testMapFormsToDataDoesNotChangeEqualDateTimeInstance($date): void
    {
        $article = [];
        $publishedAt = $date;
        $publishedAtValue = clone $publishedAt;
        $article['publishedAt'] = $publishedAtValue;
        $propertyPath = new PropertyPath('[publishedAt]');

        $config = new FormConfigBuilder('publishedAt', get_class($publishedAt), $this->dispatcher);
        $config->setByReference(false);
        $config->setPropertyPath($propertyPath);
        $config->setData($publishedAt);
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $article);

        self::assertSame($publishedAtValue, $article['publishedAt']);
    }

    public function provideDate(): array
    {
        return [
            [new DateTime()],
            [new DateTimeImmutable()],
        ];
    }

    public function testMapDataToFormsUsingGetCallbackOptionOnCompoundForm(): void
    {
        $person = new DummyPerson('John Doe');
        $config = new FormConfigBuilder('person', null, $this->dispatcher, [
            'getter' => static function () {
                return ['name' => 'Jack Dow'];
            },
        ]);

        $config->setCompound(true);
        $config->setDataMapper(new OneWayDataMapper());

        $form = new Form($config);
        $this->mapper->mapDataToForms($person, new ArrayIterator([$form]));

        self::assertSame('Jack Dow', $form->getData()['name']);
    }

    public function testMapDataToFormsShouldIgnoreGettersOnNonCompoundForms(): void
    {
        $person = new DummyPerson('John Doe');
        $config = new FormConfigBuilder('person', null, $this->dispatcher, [
            'getter' => static function () {
                return ['name' => 'Jack Dow'];
            },
        ]);

        $form = new Form($config);
        $this->mapper->mapDataToForms($person, new ArrayIterator([$form]));

        self::assertNull($form->getData());
    }

    public function testMapFormsToDataUsingSetCallbackOption(): void
    {
        $person = new DummyPerson('John Doe');

        $config = new FormConfigBuilder('name', null, $this->dispatcher, [
            'getter' => static function (): void {
                throw new LogicException('This should not be called');
            },
            'setter' => static function (DummyPerson $person, $name): void {
                $person->rename($name);
            },
        ]);
        $config->setData('Jane Doe');
        $form = new SubmittedForm($config);

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $person);

        self::assertSame('Jane Doe', $person->myName());
    }

    public function testShouldUseGivenDataMapper(): void
    {
        $person = new DummyPerson('John Doe');

        $config = new FormConfigBuilder('name', null, $this->dispatcher, []);
        $config->setData('Jane Doe');
        $form = new SubmittedForm($config);

        $this->mapper = new OneWayDataMapper(($accessor = $this->prophesize(DataAccessorInterface::class))->reveal());
        $accessor->isWritable($person, $form)->willReturn(true);
        $accessor->setValue($person, 'Jane Doe', $form)->shouldBeCalled();

        $this->mapper->mapFormsToData(new ArrayIterator([$form]), $person);
    }

    public function testShouldMapTransformationExceptionToTheRightForm(): void
    {
        $person = new DummyPerson('John Doe');

        $getter = static fn () => null;
        $setter = static function (): void {
            throw new TransformationFailedException();
        };
        $form = $this->factory->createNamedBuilder('', FormType::class, $person, [
            'data_class' => DummyPerson::class,
        ])
            ->add('name', null, ['setter' => $setter])
            ->add('birthDate', null, ['setter' => $setter, 'getter' => $getter])
            ->add('extraData', null, ['setter' => $setter, 'getter' => $getter])
            ->add('enabled', null, ['setter' => $setter, 'getter' => $getter, 'invalid_message_parameters' => ['foo' => 'bar']])
            ->getForm();
        $form->submit(['name' => 'Jane Doe', 'enabled' => true, 'birthDate' => new DateTime(), 'extraData' => new stdClass()]);

        self::assertCount(0, $form->getErrors());

        $errors = $form->get('name')->getErrors();
        self::assertCount(1, $errors);
        self::assertSame('This value is not valid.', $errors[0]->getMessage());
        self::assertSame('This value is not valid.', $errors[0]->getMessageTemplate());
        self::assertSame(['{{ value }}' => 'Jane Doe'], $errors[0]->getMessageParameters());
        self::assertSame(['foo' => 'bar', '{{ value }}' => '1'], $form->get('enabled')->getErrors()[0]->getMessageParameters());
        self::assertSame(['{{ value }}' => 'DateTime'], $form->get('birthDate')->getErrors()[0]->getMessageParameters());
        self::assertSame(['{{ value }}' => 'stdClass'], $form->get('extraData')->getErrors()[0]->getMessageParameters());
    }
}

class SubmittedForm extends Form
{
    public function isSubmitted(): bool
    {
        return true;
    }
}

class NotSynchronizedForm extends SubmittedForm
{
    public function isSynchronized(): bool
    {
        return false;
    }
}

class DummyPerson
{
    private $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function myName(): string
    {
        return $this->name;
    }

    public function rename($name): void
    {
        $this->name = $name;
    }
}

class TypehintedPropertiesCar
{
    public ?string $engine;
    public ?string $color;
}
