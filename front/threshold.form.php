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
use GlpiPlugin\Purchaserequest\Threshold;

Session::checkLoginUser();

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}

$threshold = new Threshold();

if (isset($_POST["add"])) {
   $threshold->check(-1, CREATE, $_POST);
   $newID = $threshold->add($_POST);
   $url   = Toolbox::getItemTypeFormURL(PurchaseRequest::class) . "?id=$newID";
   Html::back();

} else if (isset($_POST["add_tickets"])) {
   $threshold->check(-1, CREATE, $_POST);
   $newID = $threshold->add($_POST);
   Html::back();

   /* delete purchaserequest */
} else if (isset($_POST["delete"])) {

   $threshold->check($_POST['id'], DELETE);
   $threshold->delete($_POST);
   Html::back();
} else if (isset($_POST["restore"])) {

   $threshold->check($_POST['id'], DELETE);
   $threshold->restore($_POST);
   Html::back();

} else if (isset($_POST["purge"])) {
   $threshold->check($_POST['id'], PURGE);
   $threshold->delete($_POST, 1);
   Html::back();

   /* update purchaserequest */
} else if (isset($_POST["update"]) || (isset($_POST['update_status']))) {

   $threshold->check($_POST['id'], UPDATE);
   $threshold->update($_POST);
   Html::back();
}


Html::back();
