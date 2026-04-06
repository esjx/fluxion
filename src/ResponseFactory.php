<?php
namespace Fluxion;

use stdClass;
use Psr\Http\Message\{MessageInterface};
use GuzzleHttp\Psr7\{Utils, Response};

class ResponseFactory
{

    public static function fromView(View $view, int $code = 200, ?int $max_age = null): ?MessageInterface
    {

        $stream = Utils::streamFor();

        $stream->write($view->load());

        $response = new Response();

        $util = new ResponseCache();

        if (!$max_age) {
            $response = $util->withCachePrevention($response);
        }

        else {
            $response = $util->withCache($response, $max_age);
        }

        return $response->withStatus($code)
            ->withBody($stream);

    }

    public static function fromText(string $text, int $code = 200, ?int $max_age = null): ?MessageInterface
    {

        $stream = Utils::streamFor();

        $stream->write($text);

        $response = new Response();

        $util = new ResponseCache();

        if (!$max_age) {
            $response = $util->withCachePrevention($response);
        }

        else {
            $response = $util->withCache($response, $max_age);
        }

        return $response->withStatus($code)
            ->withBody($stream);

    }

    public static function fromJson(array|stdClass $json, int $code = 200, ?int $max_age = null): MessageInterface
    {

        $stream = Utils::streamFor();

        $stream->write(json_encode($json, JSON_PRETTY_PRINT));

        $response = new Response();

        $util = new ResponseCache();

        if (!$max_age) {
            $response = $util->withCachePrevention($response);
        }

        else {
            $response = $util->withCache($response, $max_age);
        }

        return $response->withStatus($code)
            ->withBody($stream)
            ->withHeader('Content-Type', 'application/json');

    }

    public static function fromFile(string $file, int $code = 200, ?int $max_age = null, ?string $title = null): MessageInterface
    {

        if (!file_exists($file)) {
            return self::fromText('File not found', 404);
        }

        $fileResource = fopen($file, 'r');

        $stream = Utils::streamFor($fileResource);

        $mime_type = mime_content_type($file);

        $file_size = filesize($file);

        $response = new Response();

        $util = new ResponseCache();

        if (!$max_age) {
            $response = $util->withCachePrevention($response);
        }

        else {
            $response = $util->withCache($response, $max_age);
        }

        $message = $response->withStatus($code)
            ->withBody($stream)
            ->withHeader('Content-Type', $mime_type)
            ->withHeader('Content-Length', $file_size);

        if (!empty($title)) {

            $title .= '.' . pathinfo($file, PATHINFO_EXTENSION);

            $message = $message->withHeader('Content-Disposition', 'attachment; filename="' . $title . '"');

        }

        return $message;

    }

}
