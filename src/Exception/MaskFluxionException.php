<?php
namespace Fluxion\Exception;

use Fluxion\FluxionException;
use Psr\Log\LogLevel;

class MaskFluxionException extends FluxionException
{

    public function __construct(string $label, string $value, string $message, string $log_level = LogLevel::NOTICE)
    {
        parent::__construct(
            message: '{{label:b}}: Valor informado ({{value:b}}) não atende ao padrão ({{message:b}})!',
            data: [
                'label' => $label,
                'value' => $value,
                'message' => $message,
            ],
            log_level: $log_level
        );
    }

}
