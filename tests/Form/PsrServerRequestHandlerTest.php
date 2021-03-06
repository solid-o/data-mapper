<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\Form;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;
use Solido\Common\Exception\InvalidArgumentException;
use stdClass;
use Symfony\Component\Form\RequestHandlerInterface;

use function http_build_query;
use function Safe\filesize;

use const UPLOAD_ERR_OK;

abstract class PsrServerRequestHandlerTest extends AbstractRequestHandlerTest
{
    public function testRequestShouldBeInstanceOfRequest(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->requestHandler->handleRequest($this->createForm('name', 'GET'), new stdClass());
    }

    protected function setRequestData($method, $data, $files = []): void
    {
        $request = new ServerRequest($method, 'http://localhost/');
        if ($method === 'GET') {
            $request = $request->withQueryParams($data);
        } else {
            $request = $request
                ->withBody(Stream::create(http_build_query($data)))
                ->withParsedBody($data);
        }

        $this->request = $request->withUploadedFiles($files);
    }

    abstract protected function getRequestHandler(): RequestHandlerInterface;

    protected function getUploadedFile($suffix = ''): UploadedFile
    {
        $fileName = __DIR__ . '/../Fixtures/foo' . $suffix;

        return new UploadedFile($fileName, filesize($fileName), UPLOAD_ERR_OK, 'foo' . $suffix);
    }

    protected function getInvalidFile(): string
    {
        return 'file:///etc/passwd';
    }

    protected function getFailedUploadedFile($errorCode): UploadedFile
    {
        $fileName = __DIR__ . '/../Fixtures/foo';

        return new UploadedFile($fileName, filesize($fileName), $errorCode, 'foo');
    }
}
