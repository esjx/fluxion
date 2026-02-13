<?php
namespace Fluxion;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{

    private ?string $class = null;
    private ?string $method = null;
    private ?Model $model = null;

    /**
     * @throws Exception
     */
    public function __construct(public string       $route,
                                public array|string $methods = 'GET',
                                public array        $args = [],
                                public bool         $append = true)
    {

        if (is_string($methods)) {
            $this->methods = [$methods];
        }

        foreach ($this->methods as $method) {

            if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'])) {
                throw new Exception("Método HTTP '$method' não suportado!");
            }

        }

    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    /**
     * @throws Exception
     */
    public function setClassMethod(string $class, string $method): void
    {

        if (!method_exists($class, $method)) {
            throw new Exception("Método '$class:$method()' não existe!");
        }

        $this->class = $class;
        $this->method = $method;

    }

    public function getModel(): ?Model
    {
        return $this->model;
    }

    public function setModel(Model $model): void
    {
        $this->model = $model;
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
