<?php
namespace Fluxion;

use Attribute;

#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_METHOD | Attribute::TARGET_CLASS)]
class Route
{

    private ?string $class = null;
    private ?string $method = null;

    public function __construct(public string $route,
                                public array $methods = ['GET'],
                                public array $args = [],
                                public bool $full = false)
    {
        // TODO: Validar variáveis
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

}
