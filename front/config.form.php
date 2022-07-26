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

include('../../../inc/includes.php');
Session::checkLoginUser();

if (Plugin::isPluginActive("purchaserequest")) {
   if (Session::haveRight("plugin_purchaserequest_config", READ)) {
      $config = new PluginPurchaserequestConfig();

      if (isset($_POST["update_config"])) {
         Session::checkRight("plugin_purchaserequest_config", READ);
         $config->update($_POST);
         Html::back();

      } else {
         $_SESSION['glpi_js_toload']["tinymce"][] = 'lib/tiny_mce/lib/tinymce.js';
         Html::header(__('Setup'), '', "config", PluginPurchaserequestConfig::getType());
         $config->GetFromDB(1);
         $config->display($_GET);
         //         $config->showForm();

         Html::footer();
      }

   } else {
      Html::header(__('Setup'), '', "config", Plugin::getType());
      echo "<div class='alert alert-important alert-warning d-flex'>";
      echo "<b>" . __("You don't have permission to perform this action.") . "</b></div>";
      Html::footer();
   }

} else {
   Html::header(__('Setup'), '', "config", Plugin::getType());
   echo "<div class='alert alert-important alert-warning d-flex'>";
   echo "<b>" . __('Please activate the plugin', 'purchaserequest') . "</b></div>";
   Html::footer();
}
