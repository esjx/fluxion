<?php
namespace Fluxion;

trait ModelSave
{

    protected bool $saved = false;

    public function isSaved(): bool
    {
        return $this->saved;
    }

    public function setSaved(bool $saved): void
    {
        $this->saved = $saved;
    }

    public function onSave(): bool
    {
        return true;
    }

    public function onSaved(): void {}

    /**
     * @throws Exception
     */
    public function save(): bool
    {

        $this->changeState(State::SAVE);

        if ($this->onSave() && Config::getConnector()->save($this)) {

            $this->saved = true;

            $this->onSaved();

        }

        return false;

    }

}
