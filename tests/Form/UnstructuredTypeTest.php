<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form;

use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Solido\DataMapper\Form\UnstructuredType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UnstructuredTypeTest extends TestCase
{
    use ProphecyTrait;

    private UnstructuredType $type;

    protected function setUp(): void
    {
        $this->type = new UnstructuredType();
    }

    public function testConfigureOptionsShouldConfigureDefaults(): void
    {
        $resolver = $this->prophesize(OptionsResolver::class);
        $resolver
            ->setDefaults([
                'compound' => false,
                'multiple' => true,
            ])
            ->shouldBeCalled()
            ->willReturn($resolver);

        $this->type->configureOptions($resolver->reveal());
    }
}
