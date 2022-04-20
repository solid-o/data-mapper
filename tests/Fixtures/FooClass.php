<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Fixtures;

use Solido\DataTransformers\Exception\TransformationFailedException;
use Symfony\Component\Validator\Constraints as Assert;

class FooClass
{
    /**
     * @Assert\Valid()
     */
    public $notMappedChild;

    /** @var string */
    public $foobar = 'foobar';
    public $file;
    private $transformError = 'transformable_init';

    /**
     * @Assert\NotBlank()
     */
    private $privateBar = 'privateBar';
    private $notAccessibleBar = 'unreachableBar';

    /**
     * @Assert\NotBlank()
     * @Assert\Regex("/[a-zA-Z]+/")
     */
    public $validatable = 'validatable';

    /**
     * @Assert\Valid()
     */
    public $child;

    /**
     * @return string
     */
    public function getTransformError(): string
    {
        return $this->transformError;
    }

    /**
     * @param string $transformError
     */
    public function setTransformError(string $transformError): void
    {
        throw new TransformationFailedException('Cannot transform');
    }

    /**
     * @return string
     */
    public function getPrivateBar(): string
    {
        return $this->privateBar;
    }

    /**
     * @param string $privateBar
     */
    public function setPrivateBar(string $privateBar): void
    {
        $this->privateBar = $privateBar;
    }

    /**
     * @return string
     */
    public function getNotAccessibleBar(): string
    {
        return $this->notAccessibleBar;
    }
}
