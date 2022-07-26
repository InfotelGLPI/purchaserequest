<?php
/*
 LICENSE

 This file is part of the purchaserequest plugin.

 Purchaserequest plugin is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Purchaserequest plugin is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Purchaserequest. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 @package   purchaserequest
 @author    the purchaserequest plugin team
 @copyright Copyright (c) 2015-2022 Purchaserequest plugin team
 @license   GPLv2+
            http://www.gnu.org/licenses/gpl.txt
 @link      https://github.com/InfotelGLPI/purchaserequest
 @link      http://www.glpi-project.org/
 @since     2009
 ---------------------------------------------------------------------- */

include("../../../inc/includes.php");

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}

$threshold = new PluginPurchaserequestThreshold();

if (isset($_POST["add"])) {
   $threshold->check(-1, CREATE, $_POST);
   $newID = $threshold->add($_POST);
   $url   = Toolbox::getItemTypeFormURL('PluginPurchaserequestPurchaseRequest') . "?id=$newID";
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
