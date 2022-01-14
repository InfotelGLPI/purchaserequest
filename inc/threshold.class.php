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

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class PluginPurchaserequestPurchaseRequest
 */
class PluginPurchaserequestThreshold extends CommonDBTM {
   public static $rightname = 'plugin_purchaserequest_purchaserequest';
   public        $dohistory = true;


   static $list_type_allowed = ["ComputerType", "MonitorType", "PeripheralType", "NetworkEquipmentType", "PrinterType",
                                "PhoneType", "ConsumableItemType", "CartridgeItemType", "ContractType", "PluginOrderOtherType",
                                "SoftwareLicenseType", "CertificateType", "RackType", "PduType",];


   /**
    * @param int $nb
    *
    * @return string|\translated
    */
   public static function getTypeName($nb = 0) {
      return _n("Purchase threshold", "Purchase thresholds", $nb, "purchaserequest");
   }


   /**
    * @param array $options
    *
    * @return array
    */
   function defineTabs($options = []) {
      $ong = [];
      $this->addDefaultFormTab($ong);

      return $ong;
   }

   /**
    * @param \CommonGLPI $item
    * @param int         $withtemplate
    *
    * @return string|\translated
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      return $this->getTypeName(1);

   }


   /**
    * @param \CommonGLPI $item
    * @param int         $tabnum
    * @param int         $withtemplate
    *
    * @return bool
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {
      $type = $item->getType();

      if (in_array($item->getType(), self::$list_type_allowed)) {
         $threshold = new self();
         $threshold->getEmpty();
         $threshold->getFromDBByCrit(["itemtype" => $item->getType(),
                                      "items_id" => $item->getID()]);
         $threshold->showThresholdForm($threshold->getID(), $item);
      }

      return true;
   }


   /**
    * @param       $ID
    * @param array $options
    * @param item  $item
    *
    * @return bool
    */
   public function showThresholdForm($ID, $item, $options = []) {

      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      $canedit            = $this->can($ID, UPDATE);
      $options['canedit'] = $canedit;

      // Data saved in session
      if (isset($_SESSION['glpi_plugin_thresholds_fields'])) {
         foreach ($_SESSION['glpi_plugin_thresholds_fields'] as $key => $value) {
            $this->fields[$key] = $value;
         }
         unset($_SESSION['glpi_plugin_thresholds_fields']);
      }

      /* title */
      echo "<tr class='tab_bg_1'>";
      echo "<td colspan='2'>" . $this->getTypeName(1) . "</td><td>";
      if ($canedit) {
         echo Html::input('thresholds', ['value' => $this->fields['thresholds'], 'size' => 40]);
      } else {
         echo $this->fields["thresholds"];
      }
      echo "</td></tr>";

      echo Html::hidden('itemtype', ['value' => $item->getType()]);
      echo Html::hidden('items_id', ['value' => $item->getID()]);
      echo Html::hidden('id', ['value' => $ID]);

      if ($canedit) {
         $this->showFormButtons($options);
      } else {
         echo "</table></div>";
         Html::closeForm();
      }

      return true;
   }


   /**
    * @param \Migration $migration
    */
   public static function install(Migration $migration) {
      global $DB;

      $dbu   = new DbUtils();
      $table = $dbu->getTableForItemType(__CLASS__);

      if (!$DB->tableExists($table)) {
         $migration->displayMessage("Installing $table");
         $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_purchaserequest_thresholds` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `itemtype` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `items_id` int unsigned NOT NULL DEFAULT '0',
                    `thresholds` int unsigned NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die ($DB->error());

      } else {

      }

   }

   public static function uninstall() {
      global $DB;

      $dbu   = new DbUtils();
      $table = $dbu->getTableForItemType(__CLASS__);
      $DB->query("DROP TABLE IF EXISTS`" . $table . "`") or die ($DB->error());
   }


   public static function getObject($type) {
      return $type . "Type";
   }

}
