<?php

/**
 * -------------------------------------------------------------------------
 * purchaserequest plugin for GLPI
 * Copyright (C) 2021-2026 by the purchaserequest Development Team.
 *
 * https://github.com/InfotelGLPI/purchaserequest
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of purchaserequest.
 *
 * purchaserequest is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * purchaserequest is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with purchaserequest. If not, see <http://www.gnu.org/licenses/>.
 * --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Purchaserequest;

use CommonGLPI;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Html;
use ProfileRight;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Profile
 *
 * This class manages the profile rights of the plugin
 */
class Profile extends \Profile
{
    /**
     * @param int $nb
     *
     * @return string
     */
    public static function getTypeName($nb = 0)
    {
        return self::createTabEntry(__('Rights management'));
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == 'Profile') {
            return self::createTabEntry(_n("Purchase request", "Purchase requests", 2, "purchaserequest"));
        }
        return '';
    }

    public static function getIcon()
    {
        return "ti ti-basket";
    }


    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(
        CommonGLPI $item,
        $tabnum = 1,
        $withtemplate = 0
    ) {
        if (!$item instanceof \Profile || !self::canView()) {
            return false;
        }

        $profile = new \Profile();
        $profile->getFromDB($item->getID());

        $rights = self::getAllRights(true);

        $twig = TemplateRenderer::getInstance();
        $twig->display('@purchaserequest/profile.html.twig', [
            'id' => $item->getID(),
            'profile' => $profile,
            'title' => self::getTypeName(Session::getPluralNumber()),
            'rights' => $rights,
        ]);

        return true;
    }



    /**
     * Get all rights
     *
     * @param type $all
     *
     * @return array
     */
    public static function getAllRights($all = false)
    {

        $rights = [
            ['itemtype' => PurchaseRequest::class,
                'label'    => __('Purchase request', 'purchaserequest'),
                'field'    => 'plugin_purchaserequest_purchaserequest',
            ],
        ];
        if ($all) {
            $rights[] = ['itemtype' => PurchaseRequest::class,
                'label'    => __("Purchase request validation", "purchaserequest"),
                'field'    => 'plugin_purchaserequest_validate',
                'rights' => [
                    READ => __('Read'),
                ]];

            $rights[] = ['itemtype' => Config::class,
                'label'    => __("Setup"),
                'field'    => 'plugin_purchaserequest_config',
                'rights' => [
                    READ => __('Read'),
                ]];
        }

        return $rights;
    }

    /**
     * Init profiles
     *
     **/

    public static function translateARight($old_right)
    {
        switch ($old_right) {
            case '':
                return 0;
            case 'r':
                return READ;
            case 'w':
                return UPDATE + PURGE;
            case '0':
            case '1':
                return $old_right;

            default:
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
    public static function migrateOneProfile($profiles_id)
    {
        global $DB;
        //Cannot launch migration if there's nothing to migrate...
        if (!$DB->tableExists('glpi_plugin_purchaserequest_profiles')) {
            return true;
        }

        $it = $DB->request([
            'FROM' => 'glpi_plugin_purchaserequest_profiles',
            'WHERE' => ['profiles_id' => $profiles_id],
        ]);
        foreach ($it as $profile_data) {
            $matching       = ['show_purchaserequest_tab' => 'plugin_purchaserequest_purchaserequest',
                'validation'               => 'plugin_purchaserequest_validate'];
            $current_rights = ProfileRight::getProfileRights($profiles_id, array_values($matching));
            foreach ($matching as $old => $new) {
                if (!isset($current_rights[$old])) {
                    $DB->update('glpi_profilerights', ['rights' => self::translateARight($profile_data[$old])], [
                        'name'        => $new,
                        'profiles_id' => $profiles_id,
                    ]);
                }
            }
        }
    }

    /**
     * Initialize profiles, and migrate it necessary
     */
    public static function initProfile()
    {
        global $DB;
        $profile = new self();
        $dbu     = new DbUtils();
        //Add new rights in glpi_profilerights table
        foreach ($profile->getAllRights(true) as $data) {
            if ($dbu->countElementsInTable(
                "glpi_profilerights",
                ["name" => $data['field']],
            ) == 0) {
                ProfileRight::addProfileRights([$data['field']]);
            }
        }

        //Migration old rights in new ones
        $it = $DB->request([
            'SELECT' => ['id'],
            'FROM' => 'glpi_profiles',
        ]);
        foreach ($it as $prof) {
            self::migrateOneProfile($prof['id']);
        }
        $it = $DB->request([
            'FROM' => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => $_SESSION['glpiactiveprofile']['id'],
                'name' => ['LIKE', '%plugin_purchaserequest%'],
            ],
        ]);
        foreach ($it as $prof) {
            $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
        }
    }

    /**
     * Initialize profiles, and migrate it necessary
     */
    public static function changeProfile()
    {
        global $DB;

        foreach ($DB->request([
            'FROM'  => 'glpi_profilerights',
            'WHERE' => [
                'profiles_id' => (int) $_SESSION['glpiactiveprofile']['id'],
                'name'        => ['LIKE', '%plugin_purchaserequest_purchaserequest%'],
            ],
        ]) as $prof) {
            $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
        }

    }

    /**
     * @param $profiles_id
     */
    public static function createFirstAccess($profiles_id)
    {

        $rights = ['plugin_purchaserequest_purchaserequest' => 127,
            'plugin_purchaserequest_validate'        => 1,
            'plugin_purchaserequest_config'          => 1,
        ];

        self::addDefaultProfileInfos(
            $profiles_id,
            $rights,
            true,
        );

    }

    /**
     * @param $profile
     **/
    public static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false)
    {
        $dbu          = new DbUtils();
        $profileRight = new ProfileRight();
        foreach ($rights as $right => $value) {
            if ($dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id,
                    "name"        => $right],
            ) && $drop_existing) {
                $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
            }
            if (!$dbu->countElementsInTable(
                'glpi_profilerights',
                ["profiles_id" => $profiles_id,
                    "name"        => $right],
            )) {
                $myright['profiles_id'] = $profiles_id;
                $myright['name']        = $right;
                $myright['rights']      = $value;
                $profileRight->add($myright);

                //Add right to the current session
                $_SESSION['glpiactiveprofile'][$right] = $value;
            }
        }
    }

    public static function removeRightsFromSession()
    {
        foreach (self::getAllRights(true) as $right) {
            if (isset($_SESSION['glpiactiveprofile'][$right['field']])) {
                unset($_SESSION['glpiactiveprofile'][$right['field']]);
            }
        }
    }

}
