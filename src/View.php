<?php
namespace Fluxion;

use AllowDynamicProperties;
use Fluxion\Exception\{FileNotExistException};

#[AllowDynamicProperties]
abstract class View
{

    use Format;

    /**
     * @throws Exception
     */
    function __construct(protected string $filename)
    {

        if (!file_exists($filename)) {
            throw new FileNotExistException($this->filename);
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
