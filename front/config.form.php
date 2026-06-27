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

use GlpiPlugin\Purchaserequest\Config;

Session::checkLoginUser();

if (Plugin::isPluginActive("purchaserequest")) {
   if (Session::haveRight("plugin_purchaserequest_config", READ)) {
      $config = new Config();

      if (isset($_POST["update_config"])) {
         Session::checkRight("plugin_purchaserequest_config", UPDATE);
         $config->update($_POST);
         Html::back();

      } else {
         Html::requireJs('tinymce');
         Html::header(__('Setup'), '', "config", Config::getType());
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
