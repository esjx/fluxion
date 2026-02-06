<?php
namespace Fluxion;

use stdClass;
use Psr\Http\Message\{MessageInterface};
use GuzzleHttp\Psr7\{Utils, Response};

class ResponseFactory
{

    public static function fromText(string $text, int $code = 200): ?MessageInterface
    {

        $response = new Response();

        $stream = Utils::streamFor();

        $stream->write($text);

        return $response->withStatus($code)
            ->withBody($stream);

    }

    public static function fromJson(array|stdClass $json, int $code = 200): MessageInterface
    {

        $response = new Response();

        $stream = Utils::streamFor();

        $stream->write(json_encode($json, JSON_PRETTY_PRINT));

        return $response->withStatus($code)
            ->withBody($stream)
            ->withHeader('Content-Type', 'application/json');

    }

    public static function fromFile(string $file, int $code = 200): MessageInterface
    {

        if (!file_exists($file)) {
            return self::fromText('File not found', 404);
        }

        $response = new Response();

        $fileResource = fopen($file, 'r');

        $stream = Utils::streamFor($fileResource);

        $mime_type = mime_content_type($file);

        $file_size = filesize($file);

        return $response->withStatus($code)
            ->withBody($stream)
            ->withHeader('Content-Type', $mime_type)
            ->withHeader('Content-Length', $file_size);

    }

}
