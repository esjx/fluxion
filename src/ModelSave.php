<?php
namespace Fluxion;

use Fluxion\Database\{AuditTrail};

trait ModelSave
{

    protected ?AuditTrail $_audit_trail = null;

    public function getAuditTrail(): ?AuditTrail
    {
        return $this->_audit_trail;
    }

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
     * @throws FluxionException
     */
    public function save(): bool
    {

        $class = get_class($this);

        $this->changeState(State::SAVE);

        if ($this->onSave() && Config::getConnector()->save($this)) {

            $this->getAuditTrail()?->registerUpdate($this);

            $this->saved = true;

            $this->onSaved();

            $key = $class . "___loadById___" . $this->id();

            Cache::setValue($key, $this);

        }

        return false;

    }

    /**
     * @throws FluxionException
     */
    public function delete(): void
    {

        $this::findById($this->id())->delete();

        $this->getAuditTrail()?->registerDelete($this);

    }

}
