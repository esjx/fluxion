<?php
namespace Fluxion;

use Psr\Http\Message\{ResponseInterface};

class ResponseCache
{

    public function withCache(ResponseInterface $response, int $seconds = 300): ResponseInterface
    {

        $ts = gmdate('D, d M Y H:i:s', time() + $seconds) . ' GMT';
        $ls = gmdate('D, d M Y H:i:s', time()) . ' GMT';

        return $response->withHeader('Expires', $ts)
            ->withHeader('Last-Modified', $ls)
            ->withHeader('Pragma', 'cache')
            ->withHeader('Cache-Control', 'public, max-age=' . $seconds);

    }

    public function withCachePrevention(ResponseInterface $response): ResponseInterface
    {

        $ls = gmdate('D, d M Y H:i:s', time()) . ' GMT';

        return $response->withHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT')
            ->withHeader('Last-Modified', $ls)
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('Cache-Control', 'no-store, no-cache, must-revalidate');

    }

}
