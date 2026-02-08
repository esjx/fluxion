<?php
namespace Fluxion\Exception;

use Fluxion\Exception;

class MaskException extends Exception
{

    public function __construct(string $label, string $value, string $message, $log = true)
    {
        parent::__construct(
            message: '{{label:b}}: Valor informado ({{value:b}}) não atende ao padrão ({{message:b}})!',
            data: [
                'label' => $label,
                'value' => $value,
                'message' => $message,
            ],
            log: $log
        );
    }

}
