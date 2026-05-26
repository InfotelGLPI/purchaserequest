<?php

/*
 -------------------------------------------------------------------------
 purchaserequest plugin for GLPI
 Copyright (C) 2021-2026 by the purchaserequest Development Team.

 https://github.com/InfotelGLPI/purchaserequest
 -------------------------------------------------------------------------

 LICENSE

 This file is part of purchaserequest.

 purchaserequest is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 purchaserequest is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with purchaserequest. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use GlpiPlugin\Purchaserequest\PurchaseRequest;
use GlpiPlugin\Servicecatalog\Main;

Session::checkLoginUser();

global $DB;

if (Session::getCurrentInterface() == 'central') {
    Html::header(
        PurchaseRequest::getTypeName(2),
        $_SERVER['PHP_SELF'],
        "management",
        PurchaseRequest::class,
        "purchaserequest"
    );
} else {
    if (Plugin::isPluginActive('servicecatalog')) {
        Main::showDefaultHeaderHelpdesk(PurchaseRequest::getTypeName(2));
        echo "<br>";
    } else {
        Html::helpHeader(PurchaseRequest::getTypeName(2));
    }
}

if (Plugin::isPluginActive("order")
    && $DB->tableExists("glpi_plugin_order_orders")) {
    $purchase = new PurchaseRequest();

    if (PurchaseRequest::canView()) {
        Search::show(PurchaseRequest::class);
    } else {
        echo "<div class='alert alert-important alert-warning d-flex'>";
        echo "<b>" . __("Access denied") . "</b></div>";
    }
} else {
    Html::header(__('Setup'), '', "tools", PurchaseRequest::class);
    echo "<div class='alert alert-important alert-warning d-flex'>";
    echo "<b>" . __('Please activate the plugin order', 'purchaserequest') . "</b></div>";
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {
    Main::showNavBarFooter('purchaserequest');
}

if (Session::getCurrentInterface() == 'central') {
    Html::footer();
} else {
    Html::helpFooter();
}
