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
 @copyright Copyright (c) 2015-2021 Purchaserequest plugin team
 @license   GPLv2+
            http://www.gnu.org/licenses/gpl.txt
 @link      https://github.com/InfotelGLPI/purchaserequest
 @link      http://www.glpi-project.org/
 @since     2009
 ---------------------------------------------------------------------- */

/**
 * Plugin install process
 *
 * @return boolean
 */
function plugin_purchaserequest_install() {
   foreach (glob(GLPI_ROOT . '/plugins/purchaserequest/inc/*.php') as $file) {
      //Do not load datainjection files (not needed and avoid missing class error message)
      if (!preg_match('/injection.class.php/', $file)) {
         include_once ($file);
      }
   }

   echo "<center>";
   echo "<table class='tab_cadre_fixe'>";
   echo "<tr><th>".__("Plugin installation or upgrade", "purchaserequest")."<th></tr>";

   echo "<tr class='tab_bg_1'>";
   echo "<td align='center'>";

   $migration = new Migration("2.1.1");
   $classes = ['PluginPurchaserequestNotificationTargetPurchaseRequest',
                    'PluginPurchaserequestPurchaseRequest',
                    'PluginPurchaserequestConfig',
                    'PluginPurchaserequestThreshold',
                    'PluginPurchaserequestValidation',
                    'PluginPurchaserequestPurchaseRequestState'];

   foreach ($classes as $class) {
      if ($plug=isPluginItemType($class)) {
         $plugname=strtolower($plug['plugin']);
         $dir=GLPI_ROOT . "/plugins/$plugname/inc/";
         $item=strtolower($plug['class']);
         if (file_exists("$dir$item.class.php")) {
            include_once ("$dir$item.class.php");
            call_user_func([$class,'install'], $migration);
         }
      }
   }

   echo "</td>";
   echo "</tr>";
   echo "</table></center>";

   PluginPurchaserequestProfile::initProfile();
   PluginPurchaserequestProfile::createFirstAccess($_SESSION['glpiactiveprofile']['id']);

   return true;
}

/**
 * Plugin uninstall process
 *
 * @return boolean
 */
function plugin_purchaserequest_uninstall() {
   foreach (glob(GLPI_ROOT . '/plugins/purchaserequest/inc/*.php') as $file) {
      //Do not load datainjection files (not needed and avoid missing class error message)
      if (!preg_match('/injection.class.php/', $file)) {
         include_once ($file);
      }
   }

   $classes = ['PluginPurchaserequestNotificationTargetPurchaseRequest',
                    'PluginPurchaserequestPurchaseRequest',
                    'PluginPurchaserequestPurchaseRequestState'];
   foreach ($classes as $class) {
      call_user_func([$class,'uninstall']);
   }

   //Delete rights associated with the plugin
   $profileRight = new ProfileRight();
   foreach (PluginPurchaserequestProfile::getAllRights() as $right) {
      $profileRight->deleteByCriteria(['name' => $right['field']]);
   }

   return true;
}

/* define dropdown tables to be manage in GLPI : */
function plugin_purchaserequest_getDropdown() {
   /* table => name */
   $plugin = new Plugin();
   if ($plugin->isActivated("purchaserequest")) {
      return ['PluginPurchaserequestPurchaseRequestState' => __("Purchase request status", "purchaserequest")];
   } else {
      return [];
   }
}

/* define dropdown relations */
function plugin_purchaserequest_getDatabaseRelations() {
   $plugin = new Plugin();
   if ($plugin->isActivated("purchaserequest")) {
      return  ["glpi_entities" => ["glpi_plugin_purchaserequest_purchaserequests" => "entities_id"],
         "glpi_profiles"      => ["glpi_plugin_purchaserequest_profiles" => "profiles_id"],
         "glpi_users"         => ["glpi_plugin_purchaserequest_purchaserequests" => "users_id",
                                       "glpi_plugin_purchaserequest_purchaserequests" => "users_id_validate",
                                       "glpi_plugin_purchaserequest_purchaserequests" => "users_id_creator"],
         "glpi_groups"        => ["glpi_plugin_purchaserequest_purchaserequests" => "groups_id"],
         "glpi_tickets"       => ["glpi_plugin_purchaserequest_purchaserequests" => "tickets_id"],
                    "glpi_plugin_purchaserequest_purchaserequeststates" =>  [
                       "glpi_plugin_purchaserequest_purchaserequests" => "plugin_purchaserequest_purchaserequeststates_id"]];
   } else {
      return  [];
   }
}

function plugin_purchaserequest_addSelect($type, $ID, $num) {

   $searchopt = &Search::getOptions($type);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];

   if ($table == "glpi_plugin_purchaserequest_purchaserequests" && $num != 0) {
      return "`$table`.`itemtype`, `$table`.`$field` AS `ITEM_$num`, ";
   } else {
      return "";
   }

}

/* display custom fields in the search */
function plugin_purchaserequest_giveItem($type, $ID, $data, $num) {
   $searchopt = &Search::getOptions($type);
   $table     = $searchopt[$ID]["table"];
   $field     = $searchopt[$ID]["field"];
   $dbu       = new DbUtils();
   switch ($table . '.' . $field) {
      /* display associated items with order */
      case "glpi_plugin_purchaserequest_purchaserequests.types_id" :
         $file="";
         if (isset($data['raw']["itemtype"]) && $data['raw']["itemtype"] == 'PluginOrderOther') {
            $file = GLPI_ROOT . "/plugins/order/inc/othertype.class.php";
         } elseif (isset($data['raw']["itemtype"])) {
            $file = GLPI_ROOT . "/inc/" . strtolower($data['raw']["itemtype"]) . "type.class.php";
         }
         if (file_exists($file)) {
            return Dropdown::getDropdownName($dbu->getTableForItemType($data["itemtype"] . "Type"),
                                             $data['raw']["ITEM_" . $num]);
         } else {
            return " ";
         }
         break;
      case "glpi_plugin_purchaserequest_purchaserequests.plugin_order_orders_id" :
         $order = new PluginOrderOrder();
         if ($order->getFromDB($data['raw']["ITEM_" . $num])) {
            return $order->getLink();
         }

         break;

      case "glpi_plugin_purchaserequest_purchaserequests.tickets_id" :
         $ticket = new Ticket();
         if ($ticket->getFromDB($data['raw']["ITEM_" . $num])) {
            return $ticket->getLink();
         }

         break;
   }
}


function plugin_purchaserequest_getAddSearchOptions($itemtype) {

   $sopt=[];

   if ($itemtype == 'Ticket') {
      if (Session::haveRight('plugin_purchaserequest_purchaserequest', READ)) {
         $sopt[22227]['table']             = 'glpi_plugin_purchaserequest_purchaserequests';
         $sopt[22227]['field']             = 'id';
         $sopt[22227]['name']              = _x('quantity', 'Number of purchase request', 'purchaserequest');
         $sopt[22227]['forcegroupby']      = true;
         $sopt[22227]['usehaving']         = true;
         $sopt[22227]['datatype']          = 'count';
         $sopt[22227]['massiveaction']     = false;
         $sopt[22227]['joinparams']        = ['jointype'  => 'child'];
      }
   }
   return $sopt;
}
