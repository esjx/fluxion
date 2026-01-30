<?php
namespace Esj\Core;

abstract class InterfaceBase
{

    public function __construct($dados = null)
    {

        if (is_null($dados)) {
            return;
        }

        elseif (is_object($dados)) {
            $dados = get_object_vars($dados);
        }

        elseif (is_string($dados)) {
            $dados = (array) json_decode($dados);
        }

        foreach ($dados as $key => $value) {

            $key = mb_strtolower($key, 'UTF-8');

            $key = preg_replace('/_$/', '', $key);

            $this->$key = $value;

        }

    }

}
