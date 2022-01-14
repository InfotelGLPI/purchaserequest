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

class PluginPurchaserequestMenu extends CommonGLPI {

   public static function getTypeName($nb = 0) {
      return _n("Purchase request", "Purchase requests", $nb, "purchaserequest");
   }

   static function getMenuContent() {

      $menu          = [];
      $menu['title'] = PluginPurchaserequestPurchaseRequest::getTypeName(2);
      $menu['page']  = PluginPurchaserequestPurchaseRequest::getSearchURL(false);

      if (PluginPurchaserequestPurchaseRequest::canView()) {
         $menu['options']['purchaserequest']['title']           = PluginPurchaserequestPurchaseRequest::getTypeName(2);
         $menu['options']['purchaserequest']['page']            = PluginPurchaserequestPurchaseRequest::getSearchURL(false);
         $menu['options']['purchaserequest']['links']['search'] = PluginPurchaserequestPurchaseRequest::getSearchURL(false);
         if (PluginPurchaserequestPurchaseRequest::canCreate()) {
            $menu['options']['purchaserequest']['links']['add'] = PluginPurchaserequestPurchaseRequest::getFormURL(false);
         }
      }

      $menu['icon'] = PluginPurchaserequestPurchaseRequest::getIcon();

      return $menu;
   }

   function install() {

   }

}
