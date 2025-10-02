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

use GlpiPlugin\Purchaserequest\Config;
use GlpiPlugin\Purchaserequest\Profile;
use GlpiPlugin\Purchaserequest\PurchaseRequest;
use GlpiPlugin\Purchaserequest\Threshold;

global $CFG_GLPI;

define('PLUGIN_PURCHASEREQUEST_VERSION', '3.1.1');

if (!defined("PLUGIN_PURCHASEREQUEST_DIR")) {
    define("PLUGIN_PURCHASEREQUEST_DIR", Plugin::getPhpDir("purchaserequest"));
    $root = $CFG_GLPI['root_doc'] . '/plugins/purchaserequest';
    define("PLUGIN_PURCHASEREQUEST_WEBDIR", $root);
}


/**
 * Init hooks of the plugin.
 * REQUIRED
 *
 * @return void
 */
function plugin_init_purchaserequest()
{
    global $PLUGIN_HOOKS, $CFG_GLPI;


    $PLUGIN_HOOKS['csrf_compliant']['purchaserequest'] = true;

   /* Init current profile */
    $PLUGIN_HOOKS['change_profile']['purchaserequest'] = [Profile::class, 'initProfile'];

    if (Plugin::isPluginActive('purchaserequest')) {
        Plugin::registerClass(Profile::class, ['addtabon' => ['Profile']]);

        Plugin::registerClass(PurchaseRequest::class, ['addtabon' => [Threshold::class]]);
        $types = [ComputerType::getType(),
                MonitorType::getType(),
                PeripheralType::getType(),
                NetworkEquipmentType::getType(),
                PrinterType::getType(),
                PhoneType::getType(),
                ConsumableItemType::getType(),
                CartridgeItemType::getType(),
                ContractType::getType(),
                SoftwareLicenseType::getType(),
                CertificateType::getType(),
                RackType::getType(),
                PDUType::getType()];

        if (Plugin::isPluginActive('order')) {
            array_push($types, "PluginOrderOtherType");
        }
        Plugin::registerClass(Threshold::getType(), ['addtabon' => $types]);

       //TODO create right config
        if (Session::haveRight("plugin_purchaserequest_config", READ)) {
            $PLUGIN_HOOKS['config_page']['purchaserequest'] = 'front/config.form.php';
        }

        if (Session::haveRight("plugin_purchaserequest_purchaserequest", READ)
          && !class_exists('GlpiPlugin\Servicecatalog\Main')
        ) {
            $PLUGIN_HOOKS['helpdesk_menu_entry']['purchaserequest'] = PLUGIN_PURCHASEREQUEST_WEBDIR.'/front/purchaserequest.php';
            $PLUGIN_HOOKS['helpdesk_menu_entry_icon']['purchaserequest'] = PurchaseRequest::getIcon();
        }

        if (PurchaseRequest::canView()) {
            Plugin::registerClass(
                PurchaseRequest::class,
                ['notificationtemplates_types' => true,
                'addtabon'                    => ['Ticket',
                'PluginOrderOrder']]
            );
            $PLUGIN_HOOKS['menu_toadd']['purchaserequest']['management'] = PurchaseRequest::class;

            if (Plugin::isPluginActive('servicecatalog')) {
                $PLUGIN_HOOKS['servicecatalog']['purchaserequest'] = [Servicecatalog::class];
            }
        }
    }
}

/**
 * Get the name and the version of the plugin
 * REQUIRED
 *
 * @return array
 */
function plugin_version_purchaserequest()
{
    return ['name'         => _n("Purchase request", "Purchase requests", 1, "purchaserequest"),
           'version'      => PLUGIN_PURCHASEREQUEST_VERSION,
           'author'       => "<a href='https://blogglpi.infotel.com'>Infotel</a>, Xavier CAILLAUD",
           'license'      => 'GPLv2+',
           'requirements' => [
              'glpi' => [
                 'min' => '11.0',
                 'max' => '12.0',
                 'dev' => false
              ]
           ]
    ];
}

/**
 * Check pre-requisites before install
 * OPTIONNAL, but recommanded
 *
 * @return boolean
 */
function plugin_purchaserequest_check_prerequisites()
{
    global $DB;

    if (Plugin::isPluginActive("order")
       && !$DB->tableExists("glpi_plugin_order_orders")) {
        return false;
    }
    return true;
}
