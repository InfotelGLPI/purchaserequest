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
 * Class PluginPurchaserequestServicecatalog
 */
class PluginPurchaserequestServicecatalog extends CommonGLPI {

   static $rightname = 'plugin_purchaserequest_purchaserequest';

   var $dohistory = false;

   /**
    * @return bool
    */
   static function canUse() {
      return Session::haveRight(self::$rightname, UPDATE);
   }

   /**
    * @return string|\translated
    */
   static function getMenuTitle() {

      $btstyle = "";
      $nb      = self::countPurchasesToValidate();
      if ($nb > 0) {
         $btstyle = "style='color: firebrick;'";
      }
//      if (Session::getCurrentInterface() == 'central') {
         return "<span $btstyle>" . __('Validate your purchase requests', 'purchaserequest') . "<span>";
//      } else {
//         return __('Validate your purchase requests', 'purchaserequest');
//      }
   }

   /**
    * @return string|\translated
    */
   static function getAhrefTitle() {

      return __('Validate your purchase requests', 'purchaserequest');
   }

   /**
    * @return string
    * @throws \GlpitestSQLError
    */
   static function getLeftMenuLogoCss() {

      $addstyle = "";
      $nb       = self::countPurchasesToValidate();
      if ($nb > 0) {
         $addstyle = "color:firebrick;";
      }
      return $addstyle;

   }

   /**
    * @return string
    */
   static function getMenuLink() {
      global $CFG_GLPI;

      $options['reset']                     = 'reset';
      $options['criteria'][0]['field']      = 8; // status
      $options['criteria'][0]['searchtype'] = 'equals';
      $options['criteria'][0]['value']      = CommonITILValidation::WAITING;
      $options['criteria'][0]['link']       = 'AND';

      $options['criteria'][1]['field']      = 5; // users_id_validate
      $options['criteria'][1]['searchtype'] = 'equals';
      $options['criteria'][1]['value']      = Session::getLoginUserID();
      $options['criteria'][1]['link']       = 'AND';

      return PLUGIN_PURCHASEREQUEST_WEBDIR . "/front/purchaserequest.php?" . Toolbox::append_params($options, '&');
   }

   /**
    * @return string
    */
   static function getNavBarLink() {
      global $CFG_GLPI;

      $options['reset']                     = 'reset';
      $options['criteria'][0]['field']      = 8; // status
      $options['criteria'][0]['searchtype'] = 'equals';
      $options['criteria'][0]['value']      = CommonITILValidation::WAITING;
      $options['criteria'][0]['link']       = 'AND';

      $options['criteria'][1]['field']      = 5; // users_id_validate
      $options['criteria'][1]['searchtype'] = 'equals';
      $options['criteria'][1]['value']      = Session::getLoginUserID();
      $options['criteria'][1]['link']       = 'AND';

      return PLUGIN_PURCHASEREQUEST_NOTFULL_DIR . "/front/purchaserequest.php?" . Toolbox::append_params($options, '&');
   }

   /**
    * @return string
    * @throws \GlpitestSQLError
    */
   static function countPurchasesToValidate() {
      global $DB;

      $dbu     = new DbUtils();
      $nb      = 0;
      $query   = "SELECT DISTINCT `glpi_plugin_purchaserequest_purchaserequests`.`id`
                      FROM `glpi_plugin_purchaserequest_purchaserequests`
                              WHERE `users_id_validate` = '" . Session::getLoginUserID() . "'
                                    AND `glpi_plugin_purchaserequest_purchaserequests`.`status` = '" . CommonITILValidation::WAITING . "' " .
                 $dbu->getEntitiesRestrictRequest("AND", "glpi_plugin_purchaserequest_purchaserequests");
      $result  = $DB->query($query);
      $numrows = $DB->numrows($result);
      if ($numrows > 0) {
         $nb = $numrows;
      }

      return $nb;

   }

   /**
    * @return string
    * @throws \GlpitestSQLError
    */
   static function getMenuLogoCss() {

      $addstyle = "";
      $nb       = self::countPurchasesToValidate();
      if ($nb > 0) {
         $addstyle = "style='color:firebrick;'";
      }
      return $addstyle;

   }

   /**
    * @return string
    * @throws \GlpitestSQLError
    */
   static function getMenuLogo() {

      return PluginPurchaserequestPurchaseRequest::getIcon();

   }

   /**
    * @return string|\translated
    */
   static function getMenuComment() {

      $nb       = self::countPurchasesToValidate();
      $comments = __('See your purchase requests to validate', 'purchaserequest');
      if ($nb > 0) {
         $comments = "<span style='color:firebrick;'>";
         $comments .= sprintf(_n('You have %d purchase request to validate !', 'You have %d purchase requests to validate !', $nb, 'servicecatalog'), $nb);
         $comments .= "</span>";
      }


      return $comments;

   }

   /**
    * @return string
    */
   static function getLinkList() {
      return "";
   }

   /**
    * @return string
    */
   static function getList() {
      return "";
   }
}
