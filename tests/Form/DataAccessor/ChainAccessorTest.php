<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form\DataAccessor;

use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Solido\DataMapper\Form\DataAccessor\ChainAccessor;
use Solido\DataMapper\Form\DataAccessor\DataAccessorInterface;
use Solido\DataMapper\Form\Exception\AccessException;
use Symfony\Component\Form\FormInterface;

class ChainAccessorTest extends TestCase
{
    use ProphecyTrait;

    public function testGetValueShouldThrowIfNoAccessorCanReadTheValue(): void
    {
        $this->expectException(AccessException::class);
        $this->expectExceptionMessage('Unable to read from the given form data as no accessor in the chain is able to read the data.');

        $accessor = new ChainAccessor([]);
        $accessor->getValue([], $this->prophesize(FormInterface::class)->reveal());
    }

    public function testSetValueShouldThrowIfNoAccessorCanWriteTheValue(): void
    {
        $this->expectException(AccessException::class);
        $this->expectExceptionMessage('Unable to write the given value as no accessor in the chain is able to set the data.');

        $accessor = new ChainAccessor([]);
        $data = [];
        $accessor->setValue($data, 'foobar', $this->prophesize(FormInterface::class)->reveal());
    }

    public function testIsReadableShouldReturnFalseIfNoAccessorIsAvailable(): void
    {
        $accessor1 = $this->prophesize(DataAccessorInterface::class);
        $accessor2 = $this->prophesize(DataAccessorInterface::class);

        $accessor1->isReadable(Argument::cetera())->willReturn(false);
        $accessor2->isReadable(Argument::cetera())->willReturn(false);

        $accessor = new ChainAccessor([
            $accessor1->reveal(),
            $accessor2->reveal(),
        ]);

        $form = $this->prophesize(FormInterface::class);
        self::assertFalse($accessor->isReadable([], $form->reveal()));
    }

    public function testIsWritableShouldReturnFalseIfNoAccessorIsAvailable(): void
    {
        $accessor1 = $this->prophesize(DataAccessorInterface::class);
        $accessor2 = $this->prophesize(DataAccessorInterface::class);

        $accessor1->isWritable(Argument::cetera())->willReturn(false);
        $accessor2->isWritable(Argument::cetera())->willReturn(false);

        $accessor = new ChainAccessor([
            $accessor1->reveal(),
            $accessor2->reveal(),
        ]);

        $form = $this->prophesize(FormInterface::class);
        self::assertFalse($accessor->isWritable([], $form->reveal()));
    }
}
