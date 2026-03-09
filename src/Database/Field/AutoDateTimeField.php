<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\FluxionException;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AutoDateTimeField extends DateTimeField
{

    protected string $_type = self::TYPE_DATETIME;
    protected ?bool $_needs_audit = false;

    /**
     * @throws FluxionException
     */
    public function update(): void
    {

        if ($this->_model->isChanged()) {
            $this->setValue(date($this->date_format));
        }

    }

}
