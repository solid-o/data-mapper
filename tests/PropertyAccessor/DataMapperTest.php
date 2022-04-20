<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\PropertyAccessor;

use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Solido\Common\AdapterFactoryInterface;
use Solido\Common\RequestAdapter\RequestAdapterInterface;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\DataMapper\PropertyAccessor\DataMapper;
use PHPUnit\Framework\TestCase;
use Solido\DataMapper\Tests\Fixtures\ChildClass;
use Solido\DataMapper\Tests\Fixtures\FooClass;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class DataMapperTest extends TestCase
{
    use ProphecyTrait;

    private FooClass $target;
    private DataMapper $mapper;

    /** @var string[] */
    private array $fields;

    protected function setUp(): void
    {
        $this->fields = ['foobar', 'file', 'transform_error', 'private_bar', 'not_accessible_bar', 'validatable', 'child'];
        $this->mapper = new DataMapper($this->target = new FooClass(), $this->fields);
    }

    public function testShouldReportExtraFieldsError(): void
    {
        $result = null;
        $data = ['extra_foobar' => 'test1', 'foobar' => 'test', 'extra2' => 'test2'];

        try {
            $this->mapper->map($this->createRequest('POST', $data));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals('', $result->getName());
        self::assertEquals(['The request should not contain extra fields.'], $result->getErrors());
        self::assertEquals('test', $this->target->foobar);
    }

    public function provideSafeMethods(): iterable
    {
        yield ['GET'];
        yield ['HEAD'];
        yield ['TRACE'];
    }

    /**
     * @dataProvider provideSafeMethods
     */
    public function testShouldNotReportExtraFieldsOnGetRequests(string $method): void
    {
        $data = ['extra_foobar' => 'test1', 'foobar' => 'test', 'extra2' => 'test2'];

        $this->mapper->map($this->createRequest($method, $data));
        self::assertEquals('test', $this->target->foobar);
    }

    public function testShouldReportTransformationErrorAsInvalid(): void
    {
        $result = null;
        $data = ['transform_error' => 'test', 'foobar' => 'test'];

        try {
            $this->mapper->map($this->createRequest('POST', $data));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['This value is not valid.'], $result->getChildren()[0]->getErrors());
        self::assertEquals('test', $this->target->foobar);
    }

    public function testShouldIgnoreNonWritableProperties(): void
    {
        $data = ['not_accessible_bar' => 'skip', 'foobar' => 'test', 'private_bar' => 'test_private'];
        $this->mapper->map($this->createRequest('POST', $data));

        self::assertEquals('test', $this->target->foobar);
        self::assertEquals('test_private', $this->target->getPrivateBar());
        self::assertEquals('unreachableBar', $this->target->getNotAccessibleBar());
    }

    public function testShouldWork(): void
    {
        $data = ['foobar' => 'test', 'private_bar' => 'test_private'];
        $this->mapper->map($this->createRequest('POST', $data));

        self::assertEquals('test', $this->target->foobar);
        self::assertEquals('test_private', $this->target->getPrivateBar());
    }

    public function testShouldReportValidationErrors(): void
    {
        $result = null;
        $data = ['validatable' => ''];

        try {
            $this->mapper->map($this->createRequest('POST', $data));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['This value should not be blank.'], $result->getChildren()[0]->getErrors());
    }

    public function testShouldReportValidationErrorsForChildren(): void
    {
        $result = null;
        $this->target->child = new ChildClass(true);
        $this->target->child->child = new ChildClass(false);
        $data = [];

        try {
            $this->mapper->map($this->createRequest('POST', $data));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['This is not valid'], $result->getChildren()[0]->getChildren()[0]->getErrors());
        self::assertEquals('child', $result->getChildren()[0]->getChildren()[0]->getName());
    }

    public function testShouldReportValidationErrorsForUnmappedChildren(): void
    {
        $result = null;
        $this->target->notMappedChild = new ChildClass(false);
        $data = ['private_bar' => ''];

        try {
            $this->mapper->map($this->createRequest('POST', $data));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['This is not valid'], $result->getErrors());
        self::assertEquals('private_bar', $result->getChildren()[0]->getName());
        self::assertEquals(['This value should not be blank.'], $result->getChildren()[0]->getErrors());
    }

    public function testShouldUseProvidedValidator(): void
    {
        $validator = $this->prophesize(ValidatorInterface::class);
        $this->mapper = new DataMapper($this->target = new FooClass(), $this->fields, null, null, null, $validator->reveal());

        $validator->validate($this->target)
            ->shouldBeCalledOnce()
            ->willReturn(new ConstraintViolationList());

        $this->mapper->map($this->createRequest('POST', []));
    }

    public function testShouldUseProvidedTranslator(): void
    {
        $result = null;
        $validator = $this->prophesize(ValidatorInterface::class);
        $this->mapper = new DataMapper($this->target = new FooClass(), $this->fields, null, null, null, $validator->reveal());

        $validator->validate($this->target)
            ->shouldBeCalledOnce()
            ->willReturn($list = new ConstraintViolationList());

        $list->add(new ConstraintViolation('message', 'templ', [], $this->target, 'foobar', ''));

        try {
            $this->mapper->map($this->createRequest('POST', []));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['message'], $result->getChildren()[0]->getErrors());
    }

    public function testShouldUseProvidedTranslatorInCreatedValidator(): void
    {
        $result = null;
        $translator = $this->prophesize(TranslatorInterface::class);
        $this->mapper = new DataMapper($this->target = new FooClass(), $this->fields);
        $this->mapper->setTranslator($translator->reveal());
        $this->target->notMappedChild = new ChildClass(false);

        $translator->trans('This is not valid', [], 'validators')
            ->shouldBeCalledOnce()
            ->willReturn('Translated error!');

        try {
            $this->mapper->map($this->createRequest('POST', []));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['Translated error!'], $result->getErrors());
    }

    public function testShouldUseProvidedPropertyAccessor(): void
    {
        $accessor = $this->prophesize(PropertyAccessorInterface::class);
        $this->mapper = new DataMapper($this->target = new FooClass(), $this->fields, null, null, $accessor->reveal());

        $accessor->isWritable($this->target, 'foobar')->willReturn(true);
        $accessor
            ->setValue($this->target, 'foobar', 'test')
            ->shouldBeCalledOnce();

        $this->mapper->map($this->createRequest('POST', ['foobar' => 'test']));
    }

    public function testShouldUseProvidedAdapterFactory(): void
    {
        $adapter = $this->prophesize(AdapterFactoryInterface::class);
        $adapter->createRequestAdapter(Argument::any())
            ->shouldBeCalled()
            ->willReturn($request = $this->prophesize(RequestAdapterInterface::class));

        $request->getRequestMethod()->willReturn('POST');
        $request->getRequestContentLength()->willReturn(15);
        $request->getAllFiles()->willReturn([]);

        $this->mapper = new DataMapper($this->target = new FooClass(), $this->fields, $adapter->reveal());
        $this->mapper->map($this->createRequest('POST', ['foobar' => 'test']));
    }

    public function testShouldUseThrowExceptionIfPostBodyIsTooLarge(): void
    {
        $result = null;
        $adapter = $this->prophesize(AdapterFactoryInterface::class);
        $adapter->createRequestAdapter(Argument::any())
            ->shouldBeCalled()
            ->willReturn($request = $this->prophesize(RequestAdapterInterface::class));

        $request->getRequestMethod()->willReturn('POST');
        $request->getRequestContentLength()->willReturn(15 * 1024 * 1024 * 1024);

        $this->mapper = new DataMapper($this->target = new FooClass(), $this->fields, $adapter->reveal());
        try {
            $this->mapper->map($this->createRequest('POST', ['foobar' => 'test']));
        } catch (MappingErrorException $e) {
            $result = $e->getResult();
        }

        self::assertNotNull($result);
        self::assertEquals(['The uploaded file was too large. Please try to upload a smaller file.'], $result->getErrors());
    }

    public function testShouldMapFiles(): void
    {
        $this->mapper->map($this->createRequest('POST', ['foobar' => 'test'], ['file' => '']));

        self::assertNotNull($this->target->file);
    }

    public function testGetDataCouldBeOverridden(): void
    {
        $this->mapper = new class ($this->target = new FooClass(), $this->fields) extends DataMapper {
            protected function getData(object $request): array
            {
                return ['foobar' => 'testtest'];
            }
        };

        $this->mapper->map($this->createRequest('POST', []));
        self::assertEquals('testtest', $this->target->foobar);
    }

    abstract protected function createRequest(string $method, array $data, array $files = []): object;
}
