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

class PluginPurchaserequestConfig extends CommonDBTM {
   static $rightname         = "plugin_purchaserequest_config";
   var    $can_be_translated = true;

   /**
    * PluginPurchaserequestConfig constructor.
    */
   public function __construct() {

   }

   static function canView() {

      return (Session::haveRight(self::$rightname, READ));
   }

   static function canCreate() {

      return (Session::haveRight(self::$rightname, READ));
   }

   static function getTypeName($nb = 0) {

      return __('Plugin setup', 'purchaserequest');
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      return '';
   }

   static function getMenuContent() {

      $menu['title']           = self::getMenuName(2);
      $menu['page']            = self::getSearchURL(false);
      $menu['links']['search'] = self::getSearchURL(false);
      if (self::canCreate()) {
         $menu['links']['add'] = self::getFormURL(false);
      }

      return $menu;
   }

   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {


      $item->showForm($item->getID());


      return true;
   }

   function defineTabs($options = []) {

      $ong = [];
      $this->addDefaultFormTab($ong);
      //      $this->addStandardTab(__CLASS__, $ong, $options);

      return $ong;
   }

   function showForm($ID, $options = []) {

      echo "<form name='form' method='post' action='" .
           Toolbox::getItemTypeFormURL(self::getType()) . "'>";
      echo Html::hidden('id', ['value' => $this->fields['id']]);
      echo "<div align='center'><table class='tab_cadre_fixe'  cellspacing='2' cellpadding='2'>";
      echo "<tr><th colspan='2'>" . __('Configuration purchase request', 'purchaserequest') . "</th></tr>";


      echo "<tr class='tab_bg_1 top'><td>" . __('General Services Manager', 'purchaserequest') . "</td>";
      echo "<td>";
      User::dropdown(['name'   => "id_general_service_manager",
                      'value'  => $this->fields["id_general_service_manager"],
                      'entity' => -1,
                      'right'  => 'plugin_purchaserequest_validate']);

      echo "</td></tr>";


      echo "<tr class='tab_bg_2 center'><td colspan='2'>";
      echo Html::submit(_sx('button', 'Save'), ['name' => 'update_config', 'class' => 'btn btn-primary']);
      echo "</td></tr>";

      echo "</table></div>";
      Html::closeForm();
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
         $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_purchaserequest_configs` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `id_general_service_manager` int unsigned NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die ($DB->error());


         $queryInsert = "INSERT INTO glpi_plugin_purchaserequest_configs VALUES ('1','0')";
         $DB->query($queryInsert) or die ($DB->error());
      } else {

      }

   }

   public static function uninstall() {
      global $DB;

      $dbu   = new DbUtils();
      $table = $dbu->getTableForItemType(__CLASS__);
      $DB->query("DROP TABLE IF EXISTS`" . $table . "`") or die ($DB->error());
   }


}
