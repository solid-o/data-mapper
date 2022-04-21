<?php

declare(strict_types=1);

namespace Solido\DataMapper\PropertyAccessor;

use Doctrine\Common\Annotations\AnnotationReader;
use Safe\Exceptions\InfoException;
use Solido\BodyConverter\BodyConverter;
use Solido\BodyConverter\BodyConverterInterface;
use Solido\Common\AdapterFactory;
use Solido\Common\AdapterFactoryInterface;
use Solido\Common\RequestAdapter\RequestAdapterInterface;
use Solido\DataMapper\DataMapperInterface;
use Solido\DataMapper\Exception\MappingErrorException;
use Solido\DataMapper\MappingResult;
use Solido\DataTransformers\Exception\TransformationFailedException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_fill;
use function array_key_first;
use function array_map;
use function array_pop;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function explode;
use function intval;
use function lcfirst;
use function ltrim;
use function Safe\array_combine;
use function Safe\array_replace_recursive;
use function Safe\ini_get;
use function Safe\preg_replace;
use function Safe\substr;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;
use function ucwords;

class DataMapper implements DataMapperInterface
{
    protected bool $allowExtraFields = false;
    private object $target;
    /** @var array<string, bool> */
    private array $fields;
    /** @var array<string, bool> */
    private array $camelizedFields;
    private AdapterFactoryInterface $adapterFactory;
    private PropertyAccessorInterface $propertyAccessor;
    private ?ValidatorInterface $validator;
    private ?BodyConverterInterface $bodyConverter;
    private ?TranslatorInterface $translator;

    /**
     * @param string[] $fields
     */
    public function __construct(
        object $target,
        array $fields,
        ?AdapterFactoryInterface $adapterFactory = null,
        ?BodyConverterInterface $bodyConverter = null,
        ?PropertyAccessorInterface $propertyAccessor = null,
        ?ValidatorInterface $validator = null
    ) {
        if ($bodyConverter === null && class_exists(BodyConverter::class)) {
            $bodyConverter = new BodyConverter();
        }

        $this->target = $target;
        $this->fields = array_combine($fields, array_fill(0, count($fields), true));
        $this->camelizedFields = array_combine(array_map([$this, 'camelize'], $fields), $fields);
        $this->adapterFactory = $adapterFactory ?? new AdapterFactory();
        $this->bodyConverter = $bodyConverter;
        $this->propertyAccessor = $propertyAccessor ?? PropertyAccess::createPropertyAccessor();
        $this->validator = $validator;
    }

    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    public function map(object $request): void
    {
        $hasError = false;
        $errors = ['name' => '', 'children' => [], 'errors' => []];

        $extraData = [];

        foreach ($this->getData($request) as $key => $propertyValue) {
            if (! isset($this->fields[$key])) {
                $extraData[$key] = $propertyValue;
                continue;
            }

            if (! $this->propertyAccessor->isWritable($this->target, $key)) {
                continue;
            }

            try {
                $this->propertyAccessor->setValue($this->target, $key, $propertyValue);
            } catch (TransformationFailedException $e) { /* @phpstan-ignore-line */
                $errors['children'][$key]['name'] = $key;
                $errors['children'][$key]['children'] = [];
                $errors['children'][$key]['errors'][] = 'This value is not valid.';
                $hasError = true;
            }
        }

        $violationList = $this->getValidator()->validate($this->target);
        foreach ($violationList as $violation) {
            assert($violation instanceof ConstraintViolationInterface);
            $pathComponents = explode('.', $violation->getPropertyPath());
            $last = array_pop($pathComponents);

            $first = empty($pathComponents) ? $last : $pathComponents[array_key_first($pathComponents)];
            if (! isset($this->fields[$first]) && ! isset($this->camelizedFields[$first])) {
                $errors['errors'][] = $violation->getMessage();
                $hasError = true;
                continue;
            }

            $er = &$errors;
            foreach ($pathComponents as $component) {
                $component = $this->snakeCase($component);
                $er['children'][$component]['children'] ??= []; /* @phpstan-ignore-line */
                $er['children'][$component]['errors'] ??= []; /* @phpstan-ignore-line */
                $er['children'][$component]['name'] = $component;
                $er = &$er['children'][$component];
            }

            $last = $this->snakeCase($last);
            $er['children'][$last]['name'] = $last;
            $er['children'][$last]['children'] ??= []; /* @phpstan-ignore-line */
            $er['children'][$last]['errors'][] = $violation->getMessage();
            $hasError = true;
        }

        if (! empty($extraData) && ! $this->allowExtraFields) {
            $errors['errors'][] = 'The request should not contain extra fields.';
            $hasError = true;
        }

        if ($hasError) {
            $builder = static function (array $errors) use (&$builder): MappingResult {
                return new MappingResult($errors['name'], array_values(array_map($builder, $errors['children'])), $errors['errors']);
            };

            throw new MappingErrorException($builder($errors), 'Invalid data.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(object $request): array
    {
        $adapter = $this->adapterFactory->createRequestAdapter($request);
        $method = $adapter->getRequestMethod();

        // For request methods that must not have a request body we fetch data
        // from the query string. Otherwise, we look for data in the request body.
        if ($method === 'GET' || $method === 'HEAD' || $method === 'TRACE') {
            $this->allowExtraFields = true;
            $data = $adapter->getQueryParams();
        } else {
            $this->allowExtraFields = false;
            if ($this->hasPostMaxSizeBeenExceeded($adapter)) {
                $message = 'The uploaded file was too large. Please try to upload a smaller file.';
                if (isset($this->translator)) {
                    $message = $this->translator->trans($message, [], 'validators');
                }

                throw new MappingErrorException(new MappingResult('', [], [$message]), 'Invalid data.');
            }

            $params = $this->bodyConverter !== null ? $this->bodyConverter->decode($request) : $adapter->getRequestParams();
            $files = $adapter->getAllFiles();

            $data = array_replace_recursive($params, $files);
        }

        return $data;
    }

    /**
     * Returns true if the POST max size has been exceeded in the request.
     *
     * @infection-ignore-all
     */
    private function hasPostMaxSizeBeenExceeded(RequestAdapterInterface $request): bool
    {
        $contentLength = $request->getRequestContentLength();
        $maxContentLength = self::getPostMaxSize();

        return $maxContentLength && $contentLength > $maxContentLength;
    }

    /**
     * Returns maximum post size in bytes.
     *
     * @infection-ignore-all
     */
    private static function getPostMaxSize(): ?int
    {
        try {
            $iniMax = strtolower(trim(ini_get('post_max_size')));
        } catch (InfoException $e) {
            return null;
        }

        if ($iniMax === '') {
            return null;
        }

        $max = ltrim($iniMax, '+');
        if (str_starts_with($max, '0x')) {
            $max = intval($max, 16);
        } elseif (str_starts_with($max, '0')) {
            $max = intval($max, 8);
        } else {
            $max = (int) $max;
        }

        switch (substr($iniMax, -1)) {
            case 't':
                $max *= 1024;
            // no break
            case 'g':
                $max *= 1024;
            // no break
            case 'm':
                $max *= 1024;
            // no break
            case 'k':
                $max *= 1024;
        }

        return $max;
    }

    private function getValidator(): ValidatorInterface
    {
        return $this->validator ??= (function (): ValidatorInterface {
            $builder = Validation::createValidatorBuilder();

            if (class_exists(AnnotationReader::class)) {
                $builder
                    ->enableAnnotationMapping()
                    ->addDefaultDoctrineAnnotationReader();
            }

            if (isset($this->translator)) {
                $builder->setTranslator($this->translator)
                    ->setTranslationDomain('validators');
            }

            return $builder->getValidator();
        })();
    }

    /**
     * Camelizes a given string.
     *
     * @infection-ignore-all
     */
    private function camelize(string $string): string
    {
        return lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $string))));
    }

    /**
     * Camelizes a given string.
     *
     * @infection-ignore-all
     */
    private function snakeCase(string $string): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]++/', '_', preg_replace('/(?<=\\w)([A-Z])/u', '_$1', $string)));
    }
}
