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

namespace GlpiPlugin\Purchaserequest;

use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Migration;
use Session;
use Toolbox;
use User;

class Config extends CommonDBTM
{
    public static $rightname         = "plugin_purchaserequest_config";
    public $can_be_translated = true;

    /**
     * Config constructor.
     */
    public function __construct() {}

    public static function canView(): bool
    {

        return (Session::haveRight(self::$rightname, READ));
    }

    public static function canCreate(): bool
    {

        return (Session::haveRight(self::$rightname, UPDATE));
    }

    public static function getTypeName($nb = 0)
    {
        return __('Plugin setup', 'purchaserequest');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        return self::createTabEntry(self::getTypeName(1));
    }

    public static function getIcon()
    {
        return "ti ti-basket";
    }

    public static function getMenuContent()
    {

        $menu['title']           = self::getMenuName(2);
        $menu['page']            = self::getSearchURL(false);
        $menu['links']['search'] = self::getSearchURL(false);
        if (self::canCreate()) {
            $menu['links']['add'] = self::getFormURL(false);
        }

        return $menu;
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {


        $item->showForm($item->getID());


        return true;
    }

    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong);
        //      $this->addStandardTab(__CLASS__, $ong, $options);

        return $ong;
    }

    public function showForm($ID, $options = [])
    {
        // Capture the GLPI user dropdown (no direct Twig field macro) and hand it
        // to the template as pre-rendered HTML.
        ob_start();
        User::dropdown([
            'name'   => "id_general_service_manager",
            'value'  => $this->fields["id_general_service_manager"],
            'entity' => -1,
            'right'  => 'plugin_purchaserequest_validate',
        ]);
        $manager_dropdown = ob_get_clean();

        TemplateRenderer::getInstance()->display('@purchaserequest/config.html.twig', [
            'id'               => $this->fields['id'],
            'target'           => Toolbox::getItemTypeFormURL(self::getType()),
            'manager_dropdown' => $manager_dropdown,
        ]);
    }

    /**
     * @param Migration $migration
     */
    public static function install(Migration $migration)
    {
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
            // No "or die($DB->error())": the raw MySQL error must not leak to output.
            $DB->doQuery($query);


            $queryInsert = "INSERT INTO glpi_plugin_purchaserequest_configs VALUES ('1','0')";
            $DB->doQuery($queryInsert);
        } else {
        }
    }

    public static function uninstall()
    {
        global $DB;

        $dbu   = new DbUtils();
        $table = $dbu->getTableForItemType(__CLASS__);
        // No "or die($DB->error())": the raw MySQL error must not leak to output.
        $DB->doQuery("DROP TABLE IF EXISTS`" . $table . "`");
    }
}
