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
use GlpiPlugin\Purchaserequest\Validation;

Session::checkLoginUser();

if (!isset($_GET["id"])) {
    $_GET["id"] = "";
}

global $DB;

if (Plugin::isPluginActive("order")
    && $DB->tableExists("glpi_plugin_order_orders")) {
    $validation = new Validation();

    if (isset($_POST["add"])) {
        $validation->check(-1, CREATE, $_POST);
        $newID = $validation->add($_POST);
        Html::back();
    } elseif (isset($_POST["delete"])) {
        $validation->check($_POST['id'], DELETE);
        $validation->delete($_POST);
        $validation->redirectToList();
    } elseif (isset($_POST["restore"])) {
        $validation->check($_POST['id'], DELETE);
        $validation->restore($_POST);
        $validation->redirectToList();
    } elseif (isset($_POST["purge"])) {
        $validation->check($_POST['id'], PURGE);
        $validation->delete($_POST, 1);
        $validation->redirectToList();

        /* update purchaserequest */
    } elseif (isset($_POST["update"]) || (isset($_POST['update_status']))) {
        $validation->check($_POST['id'], READ);
        $validation->update($_POST);
        Html::back();
    }
    Html::back();
} else {
    Html::header(__('Setup'), '', "tools", PurchaseRequest::class, "config");
    echo "<div class='alert alert-important alert-warning d-flex'>";
    echo "<b>" . __('Please activate the plugin order', 'purchaserequest') . "</b></div>";
}

Html::footer();
