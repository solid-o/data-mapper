<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\DataMapper\Form\DataMapper;
use stdClass;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;

class DataMapperTest extends TestCase
{
    use ProphecyTrait;

    /** @var ObjectProphecy|FormInterface */
    private ObjectProphecy $form;

    /** @var ObjectProphecy|RequestHandlerInterface */
    private ObjectProphecy $requestHandler;
    private DataMapper $mapper;

    protected function setUp(): void
    {
        $this->form = $this->prophesize(FormInterface::class);
        $this->requestHandler = $this->prophesize(RequestHandlerInterface::class);

        $this->mapper = new DataMapper($this->form->reveal(), $this->requestHandler->reveal(), null);
    }

    public function testShouldCallRequestHandler(): void
    {
        $request = new stdClass();
        $this->requestHandler->handleRequest($this->form, $request)->shouldBeCalled();
        $this->form->isSubmitted()->shouldBeCalled()->willReturn(true);
        $this->form->isValid()->shouldBeCalled()->willReturn(true);

        $this->mapper->map($request);
    }

    public function testShouldCallSubmitIfNotSubmitted(): void
    {
        $request = new stdClass();
        $this->requestHandler->handleRequest($this->form, $request)->shouldBeCalled();
        $this->form->isSubmitted()->shouldBeCalled()->willReturn(false);
        $this->form->submit(null, true)->shouldBeCalled()->willReturn($this->form);
        $this->form->isValid()->shouldBeCalled()->willReturn(true);

        $this->mapper->map($request);
    }

    public function testShouldThrowIfNotValid(): void
    {
        $request = new stdClass();
        $this->requestHandler->handleRequest($this->form, $request)->shouldBeCalled();
        $this->form->getName()->willReturn('');
        $this->form->isSubmitted()->shouldBeCalled()->willReturn(true);
        $this->form->isValid()->shouldBeCalled()->willReturn(false);

        try {
            $this->mapper->map($request);
            self::fail();
        } catch (MappingErrorException $e) {
            self::assertEquals('Invalid data.', $e->getMessage());
            self::assertEquals('', $e->getResult()->getName());
        }
    }
}
