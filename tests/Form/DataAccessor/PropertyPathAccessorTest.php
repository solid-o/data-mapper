<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form\DataAccessor;

use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Solido\DataMapper\Form\DataAccessor\PropertyPathAccessor;
use Solido\DataMapper\Form\Exception\AccessException;
use stdClass;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyAccess\Exception\AccessException as PropertyAccessException;
use Symfony\Component\PropertyAccess\Exception\UninitializedPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyAccess\PropertyPath;

class PropertyPathAccessorTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy|PropertyAccessorInterface */
    private ObjectProphecy $propertyAccessor;
    private PropertyPathAccessor $accessor;

    protected function setUp(): void
    {
        $this->propertyAccessor = $this->prophesize(PropertyAccessorInterface::class);
        $this->accessor = new PropertyPathAccessor($this->propertyAccessor->reveal());
    }

    public function testGetValueShouldThrowIfFormPropertyPathIsNull(): void
    {
        $this->expectException(AccessException::class);
        $this->expectExceptionMessage('Unable to read from the given form data as no property path is defined.');

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn(null);

        $this->accessor->getValue([], $form->reveal());
    }

    public function testGetValueShouldReturnNullIfPropertyIsUninitialized(): void
    {
        $obj = new stdClass();

        $this->propertyAccessor->getValue($obj, 'path')
            ->shouldBeCalled()
            ->willThrow(UninitializedPropertyException::class);

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn(new PropertyPath('path'));

        self::assertNull($this->accessor->getValue($obj, $form->reveal()));
    }

    public function testGetValueShouldRethrowAccessException(): void
    {
        $obj = new stdClass();

        $this->propertyAccessor->getValue($obj, 'path')
            ->shouldBeCalled()
            ->willThrow(PropertyAccessException::class);

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn(new PropertyPath('path'));

        $this->expectException(PropertyAccessException::class);
        $this->accessor->getValue($obj, $form->reveal());
    }

    public function testGetValueShouldReturnPropertyValue(): void
    {
        $obj = new stdClass();

        $this->propertyAccessor->getValue($obj, $path = new PropertyPath('path'))
            ->shouldBeCalled()
            ->willReturn('test');

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn($path);

        self::assertEquals('test', $this->accessor->getValue($obj, $form->reveal()));
    }

    public function testSetValueShouldThrowIfFormPropertyPathIsNull(): void
    {
        $this->expectException(AccessException::class);
        $this->expectExceptionMessage('Unable to write the given value as no property path is defined.');

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn(null);

        $obj = [];
        $this->accessor->setValue($obj, 'val', $form->reveal());
    }

    public function testSetValueShouldNotModifyObjectIfDateTimeIsIdentical(): void
    {
        $obj = new stdClass();
        $value = new DateTimeImmutable('2022-02-09T22:30:00Z');

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn($path = new PropertyPath('path'));

        $this->propertyAccessor->getValue($obj, $path)
            ->shouldBeCalled()
            ->willReturn(new DateTime('2022-02-09T22:30:00Z'));

        $this->propertyAccessor->setValue($obj, Argument::cetera())->shouldNotBeCalled();
        $this->accessor->setValue($obj, $value, $form->reveal());
    }

    public function testSetValueShouldNotModifyObjectIfReferenceIsIdentical(): void
    {
        $obj = new stdClass();
        $value = new stdClass();
        $ref = &$value;

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn($path = new PropertyPath('path'));
        $form->getConfig()->willReturn($config = $this->prophesize(FormConfigInterface::class));
        $config->getByReference()->willReturn(true);

        $this->propertyAccessor->getValue($obj, $path)
            ->shouldBeCalled()
            ->willReturn($ref);

        $this->propertyAccessor->setValue($obj, Argument::cetera())->shouldNotBeCalled();
        $this->accessor->setValue($obj, $value, $form->reveal());
    }

    public function testSetValueShouldCallSetValue(): void
    {
        $obj = new stdClass();
        $value = 'foobar';

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn($path = new PropertyPath('path'));
        $form->getConfig()->willReturn($config = $this->prophesize(FormConfigInterface::class));
        $config->getByReference()->willReturn(false);

        $this->propertyAccessor->setValue($obj, $path, $value)->shouldBeCalled();
        $this->accessor->setValue($obj, $value, $form->reveal());
    }

    public function testSetValueShouldCallSetValueWithDateTime(): void
    {
        $obj = new stdClass();
        $value = new DateTimeImmutable('2022-02-09T22:30:00Z');

        $form = $this->prophesize(FormInterface::class);
        $form->getPropertyPath()->willReturn($path = new PropertyPath('path'));
        $form->getConfig()->willReturn($config = $this->prophesize(FormConfigInterface::class));
        $config->getByReference()->willReturn(false);

        $this->propertyAccessor->getValue($obj, $path)
            ->shouldBeCalled()
            ->willReturn(null);

        $this->propertyAccessor->setValue($obj, $path, $value)->shouldBeCalled();
        $this->accessor->setValue($obj, $value, $form->reveal());
    }
}
