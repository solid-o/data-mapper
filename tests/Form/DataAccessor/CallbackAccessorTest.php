<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form\DataAccessor;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Solido\DataMapper\Form\DataAccessor\CallbackAccessor;
use Solido\DataMapper\Form\Exception\AccessException;
use stdClass;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormInterface;

class CallbackAccessorTest extends TestCase
{
    use ProphecyTrait;

    private CallbackAccessor $accessor;

    protected function setUp(): void
    {
        $this->accessor = new CallbackAccessor();
    }

    public function testGetValueShouldThrowIfGetterIsNotDefined(): void
    {
        $form = $this->prophesize(FormInterface::class);
        $form->getConfig()->willReturn($config = $this->prophesize(FormConfigInterface::class));
        $config->getOption('getter')->willReturn(null);

        $this->expectException(AccessException::class);
        $this->expectExceptionMessage('Unable to read from the given form data as no getter is defined.');

        $this->accessor->getValue([], $form->reveal());
    }

    public function testSetValueShouldThrowIfSetterIsNotDefined(): void
    {
        $form = $this->prophesize(FormInterface::class);
        $form->getConfig()->willReturn($config = $this->prophesize(FormConfigInterface::class));
        $config->getOption('setter')->willReturn(null);

        $this->expectException(AccessException::class);
        $this->expectExceptionMessage('Unable to write the given value as no setter is defined.');

        $obj = new stdClass();
        $this->accessor->setValue($obj, 'asd', $form->reveal());
    }
}
