<?php

class CustomAccountsViewEdit extends ViewEdit {

    function __construct()
    {
        parent::__construct();
        $this->useForSubpanel = true;
        $this->useModuleQuickCreateTemplate = true;
    }

    function preDisplay()
    {
        parent::preDisplay();
    }

    function display()
    {
        parent::display();
        echo '<script src="custom/modules/Accounts/js/editView.js" rel="stylesheet"></script>';
    }
}
