<?php
namespace Fluxion\Database\Field;

use Attribute;
use Fluxion\Exception;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AutoDateTimeField extends DateTimeField
{

    protected string $_type = self::TYPE_DATETIME;

    /**
     * @throws Exception
     */
    public function update(): void
    {

        if ($this->_model->isChanged()) {
            $this->setValue(date($this->date_format));
        }

    }

}
