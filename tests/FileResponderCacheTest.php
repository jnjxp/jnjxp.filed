<?php

declare(strict_types=1);

namespace Jnjxp\Filed;

use Fig\Http\Message\StatusCodeInterface as Code;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Lmc\HttpConstants\Header;
use Micheh\Cache\CacheUtil;
use PHPUnit\Framework\TestCase;
use SplFileInfo;

class FileResponderCacheTest extends TestCase
{
    protected $responder;
    protected $filePath;
    protected $file;
    protected $range = 1;

    public function setup() : void
    {
        $this->responder = new FileResponder(new ResponseFactory(), new StreamFactory(), new CacheUtil());
        $this->filePath = __DIR__ . '/fake-file';
        $this->file = new SplFileInfo($this->filePath);
    }

    public function testHasHeaders()
    {
        $response = $this->responder->respondWithFile($this->file);

        $this->assertEquals(Code::STATUS_OK, $response->getStatusCode());

        $headers = $response->getHeaders();
        $this->assertArrayHasKey(Header::CACHE_CONTROL, $headers);
        $this->assertArrayHasKey(Header::ETAG, $headers);
    }

    public function testDisableCache()
    {
        $this->responder->setCacheEnabled(false);
        $response = $this->responder->respondWithFile($this->file);

        $this->assertEquals(Code::STATUS_OK, $response->getStatusCode());

        $headers = $response->getHeaders();
        $this->assertArrayNotHasKey(Header::CACHE_CONTROL, $headers);
        $this->assertArrayNotHasKey(Header::ETAG, $headers);
    }

    public function testEtagNotModified()
    {
        $request = ServerRequestFactory::fromGlobals()
            ->withHeader(Header::IF_NONE_MATCH, $this->etag());

        $response = $this->responder->respondWithFile($this->file, $request);

        $this->assertEquals(Code::STATUS_NOT_MODIFIED, $response->getStatusCode());
    }

    private function etag() : string
    {
        return '"' . md5($this->file->getMTime() . (string) $this->file) . '"';
    }

    public function testNotModifiedSince()
    {
        $request = ServerRequestFactory::fromGlobals()
            ->withHeader(Header::IF_MODIFIED_SINCE, $this->since());

        $response = $this->responder->respondWithFile($this->file, $request);

        $this->assertEquals(Code::STATUS_NOT_MODIFIED, $response->getStatusCode());
    }

    private function since()
    {
        $stamp = $this->file->getMTime() + 1;
        return gmdate('D, d M Y H:i:s T', $stamp);
    }

    public function testNotFoundNoCache()
    {
        $response = $this->responder->fileNotFound();
        $this->assertEquals(Code::STATUS_NOT_FOUND, $response->getStatusCode());
        $headers = $response->getHeaders();
        $this->assertArrayHasKey(Header::CACHE_CONTROL, $headers);
    }

    protected function expectedResponse() : Response
    {
        $response = new Response();
        $file = $this->file;

        $headers = [
            Header::LAST_MODIFIED  => gmdate('D, d M Y H:i:s T', $file->getMTime()),
            Header::CONTENT_LENGTH => (string) $file->getSize(),
            Header::CONTENT_TYPE => mime_content_type((string) $file),
            Header::ACCEPT_RANGES  => 'bytes'
        ];

        foreach ($headers as $header => $value) {
            $response = $response->withHeader($header, $value);
        }

        return $response;
    }
}
