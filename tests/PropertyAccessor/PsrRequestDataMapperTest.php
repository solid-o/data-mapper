<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\PropertyAccessor;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;

class PsrRequestDataMapperTest extends DataMapperTest
{
    protected function createRequest(string $method, array $data, array $files = []): object
    {
        $request = new ServerRequest($method, 'http://localhost/');
        if ($method === 'GET' || $method === 'HEAD' || $method === 'TRACE') {
            $request = $request->withQueryParams($data);
        } else {
            $f = [];
            foreach ($files as $name => $c) {
                $f[$name] = new UploadedFile(Stream::create('{}'), 2, UPLOAD_ERR_OK, $name);
            }

            $request = $request
                ->withUploadedFiles($f)
                ->withBody(Stream::create(http_build_query($data)))
                ->withParsedBody($data);
        }

        return $request;
    }
}
