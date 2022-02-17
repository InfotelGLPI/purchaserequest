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

class PluginPurchaserequestPurchaseRequestState extends CommonDropdown {

   public static function getTypeName($nb = 0) {
      return __("Purchase request status", "purchaserequest");
   }

   public static function install(Migration $migration) {
      global $DB;

      $dbu   = new DbUtils();
      $table = $dbu->getTableForItemType(__CLASS__);
      if (!$DB->tableExists($table)) {
         $migration->displayMessage("Installing $table");

         //Install
         $query = "CREATE TABLE `glpi_plugin_purchaserequest_purchaserequeststates` (
                     `id` int unsigned NOT NULL auto_increment,
                     `name` varchar(255) collate utf8mb4_unicode_ci default NULL,
                     `comment` text collate utf8mb4_unicode_ci,
                     PRIMARY KEY (`id`),
                     KEY `name` (`name`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
         $DB->query($query) or die($DB->error());
      }
   }

   public static function uninstall() {
      global $DB;
      //New table
      $dbu = new DbUtils();
      $DB->query("DROP TABLE IF EXISTS `" . $dbu->getTableForItemType(__CLASS__) . "`") or die ($DB->error());
   }
}
