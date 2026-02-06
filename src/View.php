<?php
namespace Fluxion;

use AllowDynamicProperties;

#[AllowDynamicProperties]
class View
{

    /**
     * @throws CustomException
     */
    function __construct(protected ?string $filename = null, protected array $vars = [])
    {
        
        if ($this->filename != null) {
            $this->setFileName($filename);
        }

    }

    function __get($var)
    {

        if (isset($this->vars[$var])) {
            return $this->vars[$var];
        }

        return null;

    }

    function __set($var, $value)
    {
        $this->vars[$var] = $value;
    }

    /**
     * @throws CustomException
     */
    public function setFileName($filename): void
    {

		$file_exits = false;
		
        if (file_exists($filename)) {
            $file_exits = true;
            $this->filename = $filename;
        }

        if (!$file_exits) {
            throw new CustomException("Arquivo '$filename' não existe!");
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

    public function number($value): string
    {
        return Format::number($value, 0);
    }

    public function decimal($value): string
    {
        return Format::number($value);
    }

    public function date($date): ?string
    {
        return Format::date($date);
    }

    public function fullDate($date): string
    {
        return Format::fullDate($date, true);
    }

    public function dateTime($datetime): string
    {
        return Format::dateTime($datetime);
    }

    public function time($minutes): string
    {
        return Format::time($minutes);
    }

    public static function extenso(float $value = 0, bool $currency = true, bool $feminine = false): string
    {
        return Format::fullNumber($value, $currency, $feminine);
    }

}
