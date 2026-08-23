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

use CommonDBTM;
use CommonGLPI;
use DbUtils;
use Glpi\Application\View\TemplateRenderer;
use Migration;
use Session;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Threshold
 */
class Threshold extends CommonDBTM
{
    public static $rightname = 'plugin_purchaserequest_purchaserequest';
    public $dohistory = true;


    public static $list_type_allowed = ["ComputerType", "MonitorType", "PeripheralType", "NetworkEquipmentType", "PrinterType",
        "PhoneType", "ConsumableItemType", "CartridgeItemType", "ContractType", "PluginOrderOtherType",
        "SoftwareLicenseType", "CertificateType", "RackType", "PduType",];


    /**
     * @param int $nb
     *
     * @return string|\translated
     */
    public static function getTypeName($nb = 0)
    {
        return _n("Purchase threshold", "Purchase thresholds", $nb, "purchaserequest");
    }


    /**
     * @param array $options
     *
     * @return array
     */
    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);

        return $ong;
    }

    /**
     * @param CommonGLPI $item
     * @param int         $withtemplate
     *
     * @return string|\translated
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        return self::createTabEntry(self::getTypeName(1));

    }

    public static function getIcon()
    {
        return "ti ti-basket";
    }

    /**
     * Constrain itemtype/items_id at write time to the same whitelist already
     * enforced at display time ($list_type_allowed, used by
     * displayTabContentForItem()) so a forged POST cannot create a threshold
     * on an arbitrary itemtype.
     *
     * @param array $input
     *
     * @return array|false
     */
    public function prepareInputForAdd($input)
    {
        return $this->checkThresholdInput($input);
    }

    /**
     * @param array $input
     *
     * @return array|false
     */
    public function prepareInputForUpdate($input)
    {
        return $this->checkThresholdInput($input);
    }

    /**
     * @param array $input
     *
     * @return array|false
     */
    private function checkThresholdInput($input)
    {
        if (isset($input['itemtype']) && !in_array($input['itemtype'], self::$list_type_allowed, true)) {
            Session::addMessageAfterRedirect(
                __('Invalid item type.', 'purchaserequest'),
                false,
                ERROR,
            );
            return false;
        }

        if (isset($input['items_id'])) {
            $input['items_id'] = (int) $input['items_id'];
        }

        return $input;
    }


    /**
     * @param CommonGLPI $item
     * @param int         $tabnum
     * @param int         $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
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
    public function showThresholdForm($ID, $item, $options = [])
    {

        $this->initForm($ID, $options);

        $canedit = $this->can($ID, UPDATE);

        // Data saved in session
        if (isset($_SESSION['glpi_plugin_thresholds_fields'])) {
            foreach ($_SESSION['glpi_plugin_thresholds_fields'] as $key => $value) {
                $this->fields[$key] = $value;
            }
            unset($_SESSION['glpi_plugin_thresholds_fields']);
        }

        TemplateRenderer::getInstance()->display('@purchaserequest/threshold.html.twig', [
            'item'          => $this,
            'canedit'       => $canedit,
            'target'        => Toolbox::getItemTypeFormURL(self::getType()),
            'host_itemtype' => $item->getType(),
            'host_items_id' => $item->getID(),
        ]);

        return true;
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
            $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_purchaserequest_thresholds` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `itemtype` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `items_id` int unsigned NOT NULL DEFAULT '0',
                    `thresholds` int unsigned NOT NULL DEFAULT '0',
                    PRIMARY KEY (`id`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
            // No "or die($DB->error())": the raw MySQL error must not leak to output.
            $DB->doQuery($query);

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


    public static function getObject($type)
    {
        return $type . "Type";
    }

}
