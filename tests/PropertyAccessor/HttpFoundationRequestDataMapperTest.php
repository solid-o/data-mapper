<?php

declare(strict_types=1);

namespace Solido\DataMapper\Tests\PropertyAccessor;

use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

class HttpFoundationRequestDataMapperTest extends DataMapperTest
{
    protected function createRequest(string $method, array $data, array $files = []): object
    {
        $f = [];
        foreach ($files as $name => $c) {
            $f[$name] = new UploadedFile(__FILE__, $name, null, UPLOAD_ERR_OK, true);
        }

        return Request::create('http://localhost', $method, $data, [], $f, [], http_build_query($data));
    }
}
