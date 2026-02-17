<?php
namespace Fluxion;

class SetupView extends View
{

    public function __construct()
    {
        parent::__construct(__DIR__ . '/SetupView.phtml');
    }

    public ?string $content = null;

}
