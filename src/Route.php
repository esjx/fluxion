<?php
namespace Fluxion;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{

    private ?string $class = null;
    private ?string $method = null;

    /**
     * @throws CustomException
     */
    public function __construct(public string $route,
                                public array  $methods = ['GET'],
                                public array  $args = [],
                                public bool   $append = true)
    {

        foreach ($this->methods as $method) {

            if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) {
                throw new CustomException("Method $method not allowed");
            }

        }

    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): void
    {
        $this->class = $class;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function setMethod(string $method): void
    {
        $this->method = $method;
    }

    public function getRegExp($end = true): string
    {

        $end = ($end) ? '$' : '';

        $url = $this->route;

        $url = preg_replace('/{([a-z0-9_]+):int}/i', '(?P<$1>[0-9]+)', $url);
        $url = preg_replace('/{([a-z0-9_]+):string}/i', '(?P<$1>[a-z0-9-_.=%]+)', $url);
        $url = preg_replace('/{([a-z0-9_]+):any}/i', '(?P<$1>[a-z0-9-_.=%/]+)', $url);

        $url = str_replace('/', '\/', $url);

        return '/^' . $url . $end . '/i';

    }

}
