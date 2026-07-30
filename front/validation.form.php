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
 the Free Software Foundation; either version 3 of the License, or
 (at your option) any later version.

 purchaserequest is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with purchaserequest. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

use Glpi\Exception\Http\AccessDeniedHttpException;
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
        if (isset($_POST['update_status'])) {
            // Approval action (accept/refuse): authorized by being the designated
            // approver, not by the generic UPDATE right. A validator profile usually
            // holds plugin_purchaserequest_validate at READ only, so requiring UPDATE
            // here locks the real approver out. Require READ (global right + entity
            // scope), then let the row's own approver through — anyone else still
            // needs UPDATE. Validation::prepareInputForUpdate() remains the final
            // gate: only users_id_validate may actually change the status.
            $validation->check((int) $_POST['id'], READ);
            if ((int) $validation->fields['users_id_validate'] !== Session::getLoginUserID()
                && !$validation->can((int) $_POST['id'], UPDATE)) {
                throw new AccessDeniedHttpException();
            }
        } else {
            // Plain edit of the validation form fields keeps requiring UPDATE.
            $validation->check((int) $_POST['id'], UPDATE);
        }
        $validation->update($_POST);
        Html::back();
    }
    Html::back();
} else {
    Html::header(__('Setup'), '', "tools", PurchaseRequest::class, "config");
    echo "<div class='alert  alert-warning d-flex'>";
    echo "<b>" . __('Please activate the plugin order', 'purchaserequest') . "</b></div>";
}

Html::footer();
