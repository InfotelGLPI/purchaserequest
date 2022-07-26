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
      PluginServicecatalogMain::showDefaultHeaderHelpdesk(PluginPurchaserequestPurchaseRequest::getTypeName(2));
      echo "<br>";
   } else {
      Html::helpHeader(PluginPurchaserequestPurchaseRequest::getTypeName(2));
   }
}

if (Plugin::isPluginActive("order")
    && $DB->tableExists("glpi_plugin_order_orders")) {

   $purchase = new PluginPurchaserequestPurchaseRequest();

   if (PluginPurchaserequestPurchaseRequest::canView()) {
      Search::show("PluginPurchaserequestPurchaseRequest");
   } else {
      echo "<div class='alert alert-important alert-warning d-flex'>";
      echo "<b>" . __("Access denied") . "</b></div>";
   }
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
