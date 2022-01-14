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
 * Class PluginPurchaserequestProfile
 *
 * This class manages the profile rights of the plugin
 */
class PluginPurchaserequestProfile extends Profile {

   /**
    * @param int $nb
    *
    * @return translated
    */
   static function getTypeName($nb = 0) {
      return __('Rights management');
   }

   /**
    * Get tab name for item
    *
    * @param CommonGLPI $item
    * @param type       $withtemplate
    *
    * @return string
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == 'Profile'
         //          && $item->fields['interface'] == 'central'
      ) {
         return _n("Purchase request", "Purchase requests", 2, "purchaserequest");
      }
      return '';
   }

   /**
    * display tab content for item
    *
    * @param CommonGLPI $item
    * @param type       $tabnum
    * @param type       $withtemplate
    *
    * @return boolean
    * @global type      $CFG_GLPI
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == 'Profile') {
         $ID   = $item->getID();
         $prof = new self();

         self::addDefaultProfileInfos($ID,
                                      ['plugin_purchaserequest_purchaserequest' => 0,
                                       'plugin_purchaserequest_validate'        => 0,
                                       'plugin_purchaserequest_config'          => 0,
                                      ]);
         $prof->showForm($ID);

      }

      return true;
   }

   /**
    * show profile form
    *
    * @param type $ID
    * @param type $options
    *
    * @return boolean
    */
   function showForm($profiles_id = 0, $openform = true, $closeform = true) {

      echo "<div class='firstbloc'>";
      if (($canedit = Session::haveRightsOr(self::$rightname, [UPDATE, PURGE]))
          && $openform) {
         $profile = new Profile();
         echo "<form method='post' action='" . $profile->getFormURL() . "'>";
      }

      $profile = new Profile();
      $profile->getFromDB($profiles_id);

      $rights = $this->getAllRights();
      $profile->displayRightsChoiceMatrix($rights, ['canedit'       => $canedit,
                                                    'default_class' => 'tab_bg_2',
                                                    'title'         => __('General')]);

      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr class='tab_bg_1'><th colspan='4'>" . __('Helpdesk') . "</th></tr>\n";

      $effective_rights = ProfileRight::getProfileRights($profiles_id, ['plugin_purchaserequest_validate']);
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>" . __("Purchase request validation", "purchaserequest") . "</td>";
      echo "<td colspan='5'>";
      Html::showCheckbox(['name'    => '_plugin_purchaserequest_validate',
                          'checked' => $effective_rights['plugin_purchaserequest_validate']]);
      echo "</td></tr>\n";
      $effective_rights = ProfileRight::getProfileRights($profiles_id, ['plugin_purchaserequest_config']);
      echo "<tr class='tab_bg_2'>";
      echo "<td width='20%'>" . __("Setup") . "</td>";
      echo "<td colspan='5'>";
      Html::showCheckbox(['name'    => '_plugin_purchaserequest_config',
                          'checked' => $effective_rights['plugin_purchaserequest_config']]);
      echo "</td></tr>\n";
      echo "</table>";
      if ($canedit
          && $closeform) {
         echo "<div class='center'>";
         echo Html::hidden('id', ['value' => $profiles_id]);
         echo Html::submit(_sx('button', 'Save'), ['name' => 'update', 'class' => 'btn btn-primary']);
         echo "</div>\n";
         Html::closeForm();
      }
      echo "</div>";

      $this->showLegend();
   }

   /**
    * Get all rights
    *
    * @param type $all
    *
    * @return array
    */
   static function getAllRights($all = false) {

      $rights = [
         ['itemtype' => 'PluginPurchaserequestPurchaserequest',
          'label'    => __('Purchase request', 'purchaserequest'),
          'field'    => 'plugin_purchaserequest_purchaserequest'
         ]
      ];
      if ($all) {
         $rights[] = ['itemtype' => 'PluginPurchaserequestPurchaserequest',
                      'label'    => __("Purchase request validation", "purchaserequest"),
                      'field'    => 'plugin_purchaserequest_validate'];
         $rights[] = ['itemtype' => 'PluginPurchaserequestConfig',
                      'label'    => __("Setup"),
                      'field'    => 'plugin_purchaserequest_config'];
      }

      return $rights;
   }

   /**
    * Init profiles
    *
    **/

   static function translateARight($old_right) {
      switch ($old_right) {
         case '':
            return 0;
         case 'r' :
            return READ;
         case 'w':
            return UPDATE + PURGE;
         case '0':
         case '1':
            return $old_right;

         default :
            return 0;
      }
   }


   /**
    * @param $profiles_id the profile ID
    *
    * @return bool
    * @since 0.85
    * Migration rights from old system to the new one for one profile
    */
   static function migrateOneProfile($profiles_id) {
      global $DB;
      //Cannot launch migration if there's nothing to migrate...
      if (!$DB->tableExists('glpi_plugin_purchaserequest_profiles')) {
         return true;
      }

      foreach ($DB->request('glpi_plugin_purchaserequest_profiles',
                            "`profiles_id`='$profiles_id'") as $profile_data) {

         $matching       = ['show_purchaserequest_tab' => 'plugin_purchaserequest_purchaserequest',
                            'validation'               => 'plugin_purchaserequest_validate'];
         $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
         foreach ($matching as $old => $new) {
            if (!isset($current_rights[$old])) {
               $query = "UPDATE `glpi_profilerights` 
                         SET `rights`='" . self::translateARight($profile_data[$old]) . "' 
                         WHERE `name`='$new' AND `profiles_id`='$profiles_id'";
               $DB->query($query);
            }
         }
      }
   }

   /**
    * Initialize profiles, and migrate it necessary
    */
   static function initProfile() {
      global $DB;
      $profile = new self();
      $dbu     = new DbUtils();
      //Add new rights in glpi_profilerights table
      foreach ($profile->getAllRights(true) as $data) {
         if ($dbu->countElementsInTable("glpi_profilerights",
                                        ["name" => $data['field']]) == 0) {
            ProfileRight::addProfileRights([$data['field']]);
         }
      }

      //Migration old rights in new ones
      foreach ($DB->request("SELECT `id` FROM `glpi_profiles`") as $prof) {
         self::migrateOneProfile($prof['id']);
      }
      foreach ($DB->request("SELECT *
                           FROM `glpi_profilerights` 
                           WHERE `profiles_id`='" . $_SESSION['glpiactiveprofile']['id'] . "' 
                              AND `name` LIKE '%plugin_purchaserequest_purchaserequest%'") as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }
   }

   /**
    * Initialize profiles, and migrate it necessary
    */
   static function changeProfile() {
      global $DB;

      foreach ($DB->request("SELECT *
                           FROM `glpi_profilerights` 
                           WHERE `profiles_id`='" . $_SESSION['glpiactiveprofile']['id'] . "' 
                              AND `name` LIKE '%plugin_purchaserequest_purchaserequest%'") as $prof) {
         $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
      }

   }

   /**
    * @param $profiles_id
    */
   static function createFirstAccess($profiles_id) {

      $rights = ['plugin_purchaserequest_purchaserequest' => 127,
                 'plugin_purchaserequest_validate'        => 1,
                 'plugin_purchaserequest_config'          => 1,
      ];

      self::addDefaultProfileInfos($profiles_id,
                                   $rights, true);

   }

   /**
    * @param $profile
    **/
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {
      $dbu          = new DbUtils();
      $profileRight = new ProfileRight();
      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable('glpi_profilerights',
                                        ["profiles_id" => $profiles_id,
                                         "name"        => $right]) && $drop_existing) {
            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
         }
         if (!$dbu->countElementsInTable('glpi_profilerights',
                                         ["profiles_id" => $profiles_id,
                                          "name"        => $right])) {
            $myright['profiles_id'] = $profiles_id;
            $myright['name']        = $right;
            $myright['rights']      = $value;
            $profileRight->add($myright);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   static function removeRightsFromSession() {
      foreach (self::getAllRights(true) as $right) {
         if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
            unset($_SESSION['glpiactiveprofile'][$right['field']]);
         }
      }
   }

}

