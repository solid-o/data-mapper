<?php

declare(strict_types=1);

namespace Solido\DataMapper\Form;

use Psr\Http\Message\ServerRequestInterface;
use Solido\BodyConverter\BodyConverter;
use Solido\BodyConverter\BodyConverterInterface;
use Solido\Common\AdapterFactory;
use Solido\Common\AdapterFactoryInterface;
use Solido\Common\Exception\InvalidArgumentException;
use Solido\Common\Exception\NonExistentFileException;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\RequestHandlerInterface;
use Symfony\Component\Form\Util\ServerParams;
use Symfony\Component\HttpFoundation\Request;

use function class_exists;
use function get_debug_type;
use function is_array;
use function Safe\array_replace_recursive;
use function sprintf;

/** @internal */
final class RequestHandler implements RequestHandlerInterface
{
    private BodyConverterInterface|null $bodyConverter;

    public function __construct(
        private readonly ServerParams $serverParams = new ServerParams(),
        private readonly AdapterFactoryInterface $adapterFactory = new AdapterFactory(),
        BodyConverterInterface|null $bodyConverter = null,
    ) {
        if ($bodyConverter === null && class_exists(BodyConverter::class)) {
            $bodyConverter = new BodyConverter();
        }

        $this->bodyConverter = $bodyConverter;
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $request
     */
    public function handleRequest(FormInterface $form, mixed $request = null): void
    {
        if ($request === null) {
            $request = Request::createFromGlobals();
        } elseif (! $request instanceof Request && ! $request instanceof ServerRequestInterface) {
            throw new InvalidArgumentException(sprintf('Expected argument of type "%s" or "%s", "%s" given', Request::class, ServerRequestInterface::class, get_debug_type($request)));
        }

        $adapter = $this->adapterFactory->createRequestAdapter($request);

        $name = $form->getName();
        $method = $request->getMethod();

        // For request methods that must not have a request body we fetch data
        // from the query string. Otherwise we look for data in the request body.
        if ($method === Request::METHOD_GET || $method === Request::METHOD_HEAD || $method === Request::METHOD_TRACE) {
            if ($name === '') {
                $data = $adapter->getQueryParams();
            } else {
                // Don't submit GET requests if the form's name does not exist
                // in the request
                if (! $adapter->hasQueryParam($name)) {
                    return;
                }

                $data = $adapter->getQueryParam($name);
            }
        } else {
            // Mark the form with an error if the uploaded size was too large
            // This is done here and not in FormValidator because $_POST is
            // empty when that error occurs. Hence the form is never submitted.
            if ($this->serverParams->hasPostMaxSizeBeenExceeded()) {
                // Submit the form, but don't clear the default values
                $form->submit(null, false);

                /** @var callable(): string $maxSizeMessageFn */
                $maxSizeMessageFn = $form->getConfig()->getOption('upload_max_size_message');

                $form->addError(new FormError(
                    $maxSizeMessageFn(),
                    null,
                    ['{{ max }}' => $this->serverParams->getNormalizedIniPostMaxSize()],
                ));

                return;
            }

            $params = $this->bodyConverter?->decode($request) ?? $adapter->getRequestParams();

            if ($name === '') {
                $files = $adapter->getAllFiles();
            } elseif ($adapter->hasRequestParam($name) || $adapter->hasFile($name)) {
                $default = $form->getConfig()->getCompound() ? [] : null;
                $params = $params[$name] ?? $default;

                try {
                    $files = $adapter->getFile($name);
                } catch (NonExistentFileException) {
                    $files = $default;
                }
            } else {
                // Don't submit the form if it is not present in the request
                return;
            }

            if (is_array($params) && is_array($files)) {
                $data = array_replace_recursive($params, $files);
            } else {
                $data = $params ?? $files;
            }
        }

        $form->submit($data, $method !== Request::METHOD_PATCH); /** @phpstan-ignore-line */
    }

    /**
     * {@inheritdoc}
     *
     * @param mixed $data
     */
    public function isFileUpload($data): bool
    {
        return $this->adapterFactory->isFileUpload($data);
    }

    /**
     * Gets the upload file error from data.
     */
    public function getUploadFileError(mixed $data): int|null
    {
        return $this->adapterFactory->getUploadFileError($data);
    }
}
