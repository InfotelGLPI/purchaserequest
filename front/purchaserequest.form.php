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

if (Plugin::isPluginActive("order")
    && $DB->tableExists("glpi_plugin_order_orders")) {
   $purchase = new PluginPurchaserequestPurchaseRequest();

   if (isset($_POST["add"])) {
      $purchase->check(-1, CREATE, $_POST);
      $newID = $purchase->add($_POST);
      $url   = Toolbox::getItemTypeFormURL('PluginPurchaserequestPurchaseRequest') . "?id=$newID";
      if ($_SESSION['glpibackcreated']) {
         Html::redirect($purchase->getFormURL() . "?id=" . $newID);
      } else {
         Html::redirect($url);
      }

   } else if (isset($_POST["add_tickets"])) {
      $purchase->check(-1, CREATE, $_POST);
      $newID = $purchase->add($_POST);
      Html::back();

      /* delete purchaserequest */
   } else if (isset($_POST["delete"])) {

      $purchase->check($_POST['id'], DELETE);
      $purchase->delete($_POST);
      $purchase->redirectToList();
   } else if (isset($_POST["restore"])) {

      $purchase->check($_POST['id'], DELETE);
      $purchase->restore($_POST);
      $purchase->redirectToList();

   } else if (isset($_POST["purge"])) {
      $purchase->check($_POST['id'], PURGE);
      $purchase->delete($_POST, 1);
      $purchase->redirectToList();

      /* update purchaserequest */
   } else if (isset($_POST["update"]) || (isset($_POST['update_status']))) {

      $purchase->check($_POST['id'], UPDATE);
      $purchase->update($_POST);
      Html::back();
   }

   if (isset($_POST['action'])) {
      // Retrieve configuration for generate assets feature

      $purchase_request = new PluginPurchaserequestPurchaseRequest();
      switch ($_POST['chooseAction']) {
         case 'delete_link':
            if (isset ($_POST["item"])) {
               foreach ($_POST["item"] as $key => $val) {
                  if ($val == 1) {
                     $tmp['id']                     = $key;
                     $tmp['plugin_order_orders_id'] = 0;
                     $purchase_request->update($tmp);

                  }
               }
            }
            break;
      }
      Html::back();
   }

   if (Session::getCurrentInterface() == 'central') {
      Html::header(
         PluginPurchaserequestPurchaseRequest::getTypeName(2),
         $_SERVER['PHP_SELF'],
         "management",
         "PluginPurchaserequestPurchaseRequest",
         "purchaserequest"
      );
   } else {
      if (Plugin::isPluginActive('servicecatalog')) {
         PluginServicecatalogMain::showDefaultHeaderHelpdesk(PluginPurchaserequestPurchaseRequest::getTypeName(2), true);
         echo "<br>";
      } else {
         Html::helpHeader(PluginPurchaserequestPurchaseRequest::getTypeName(2));
      }
   }

   Html::requireJs('tinymce');
   $purchase->display($_GET);
} else {
   Html::header(__('Setup'), '', "tools", "pluginpurchaserequestpurchaserequest", "pluginpurchaserequestpurchaserequest");
   echo "<div class='alert alert-important alert-warning d-flex'>";
   echo "<b>" . __('Please activate the plugin order', 'purchaserequest') . "</b></div>";
}

if (Session::getCurrentInterface() != 'central'
    && Plugin::isPluginActive('servicecatalog')) {

   PluginServicecatalogMain::showNavBarFooter('purchaserequest');
}

if (Session::getCurrentInterface() == 'central') {
   Html::footer();
} else {
   Html::helpFooter();
}
