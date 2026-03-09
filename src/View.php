<?php
namespace Fluxion;

use Fluxion\Exception\{FileNotExistFluxionException};

abstract class View
{

    use Format;

    /**
     * @throws FluxionException
     */
    function __construct(protected string $filename)
    {

        if (!file_exists($filename)) {
            throw new FileNotExistFluxionException($this->filename);
        }

    }

    public function load(): string
    {

        ob_start();

        if (isset($this->filename) && file_exists($this->filename)) {
            require $this->filename;
        }

        $contents = ob_get_contents();

        ob_end_clean();

        return $contents;

    }

}
