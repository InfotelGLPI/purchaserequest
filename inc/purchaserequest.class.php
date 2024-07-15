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
 * Class PluginPurchaserequestPurchaseRequest
 */
class PluginPurchaserequestPurchaseRequest extends CommonDBTM
{
    public static $rightname = 'plugin_purchaserequest_purchaserequest';
    public $dohistory = true;

    const HISTORY_ADDLINK = 50;
    const HISTORY_DELLINK = 51;

    /**
     * @param int $nb
     *
     * @return string|\translated
     */
    public static function getTypeName($nb = 0)
    {
        return _n("Purchase request", "Purchase requests", $nb, "purchaserequest");
    }

    static function getIcon()
    {
        return "fas fa-basket-shopping";
    }

    /**
     * @return bool
     */
    public static function canValidation()
    {
        return Session::haveRight("plugin_purchaserequest_validate", 1);
    }

    /**
     * @param array $options
     *
     * @return array
     */
    function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong);
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab('Document_Item', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * @param \CommonGLPI $item
     * @param int $withtemplate
     *
     * @return string|\translated
     */
    function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == "PluginPurchaserequestPurchaseRequest") {
            return __('Approval');
        } elseif ($item->getType() == "Ticket" && Session::getCurrentInterface() == 'central') {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(2), self::countForTicket($item));
            }
            return self::getTypeName();
        } elseif ($item->getType() == "PluginOrderOrder"
            && Session::haveRight(self::$rightname, READ)) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(2), self::countForPluginOrderOrder($item));
            }
            return self::getTypeName();
        }

        return '';
    }

    static function countForTicket(Ticket $item)
    {
        $dbu = new DbUtils();
        $restrict = ["tickets_id" => $item->getField('id')];
        $nb = $dbu->countElementsInTable(['glpi_plugin_purchaserequest_purchaserequests'], $restrict);

        return $nb;
    }

    static function countForPluginOrderOrder(PluginOrderOrder $item)
    {
        $dbu = new DbUtils();
        $restrict = ["plugin_order_orders_id" => $item->getField('id')];
        $nb = $dbu->countElementsInTable(['glpi_plugin_purchaserequest_purchaserequests'], $restrict);

        return $nb;
    }

    /**
     * @param \CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        if (!Plugin::isPluginActive('order')) {
            echo "<div class='alert alert-important alert-warning d-flex'>";
            echo "<b>" . __('Please activate the plugin order', 'purchaserequest') . "</b></div>";
            return false;
        }
        if ($item->getType() == "PluginPurchaserequestPurchaseRequest") {
            PluginPurchaserequestValidation::showValidation($item);
        } elseif ($item->getType() == "Ticket") {
            self::showForTicket($item);
        } elseif ($item->getType() == "PluginOrderOrder"
            && Session::haveRight(self::$rightname, READ)) {
            self::showForOrder($item);
        }

        return true;
    }

    /**
     * @param array|\datas $input
     *
     * @return array|bool|\datas
     */
    public function prepareInputForAdd($input)
    {
        if (!$this->checkMandatoryFields($input)) {
            return false;
        }

        $input['status'] = CommonITILValidation::WAITING;

        return $input;
    }

    /**
     * Prepare input datas for updating the item
     *
     * @param $input datas used to update the item
     *
     * @return the modified $input array
     **/
    public function prepareInputForUpdate($input)
    {
        global $CFG_GLPI;

        if (isset($input['refuse_purchaserequest']) && $input['refuse_purchaserequest'] == 1) {
            $input['status'] = CommonITILValidation::REFUSED;
        }

        if (isset($input['accept_purchaserequest']) && $input['accept_purchaserequest'] == 1) {
            $input['status'] = CommonITILValidation::ACCEPTED;
        }

        if (isset($input['update_status'])) {
            //         if ($CFG_GLPI["notifications_mailing"]) {
            //            $purchase_request = new PluginPurchaserequestPurchaseRequest();
            //            $purchase_request->getFromDB($input['id']);
            //            $purchase_request->fields['status']             = $input['status'];
            //            $purchase_request->fields['comment_validation'] = $input['comment_validation'];
            //
            //            if (isset($input['status'])
            //                && $input['status'] == CommonITILValidation::ACCEPTED) {
            //               NotificationEvent::raiseEvent('validation_purchaserequest', $purchase_request);
            //            } else if (isset($input['status'])
            //                       && $input['status'] == CommonITILValidation::REFUSED) {
            //               NotificationEvent::raiseEvent('no_validation_purchaserequest', $purchase_request);
            //            }
            //         }

        } else {
            if (!$this->checkMandatoryFields($input)) {
                return false;
            }
        }

        if (isset($input['is_process']) && $input['is_process']) {
            $input['processing_date'] = date('Y-m-d H:i:s');
        }

        return $input;
    }

    /**
     * Actions done after the ADD of the item in the database
     *
     * @return nothing
     **/
    public function post_addItem()
    {
        global $CFG_GLPI;
        $list = PluginPurchaserequestThreshold::$list_type_allowed;

        //      if ($CFG_GLPI["notifications_mailing"]) {
        //         NotificationEvent::raiseEvent('ask_purchaserequest', $this);
        //      }

        if (isset($this->input["users_id_validate"])) {
            $validation = new PluginPurchaserequestValidation();
            $input = [];
            $input["entities_id"] = $this->fields["entities_id"];
            $input["users_id"] = $this->fields["users_id_creator"];
            $input["plugin_purchaserequest_purchaserequests_id"] = $this->fields["id"];
            $input["users_id_validate"] = $this->fields["users_id_validate"];
            $input["comment_validation"] = "";
            $input["submission_date"] = $_SESSION["glpi_currenttime"];
            $input["first"] = true;
            $input["status"] = CommonITILValidation::WAITING;
            $validation->add($input);
        }
        $threshold = new PluginPurchaserequestThreshold();
        $itemtype = PluginPurchaserequestThreshold::getObject($this->fields["itemtype"]);
        if ($threshold->getFromDBByCrit([
            "itemtype" => $itemtype,
            "items_id" => $this->fields["types_id"]
        ])) {
            $th = intval($threshold->fields["thresholds"]);
            if ($th != -1) {
                $config = new PluginPurchaserequestConfig();
                $config->getFromDB(1);

                if ($th < intval($this->fields["amount"])
                    && $config->fields["id_general_service_manager"] > 0) {
                    $validation = new PluginPurchaserequestValidation();
                    $input = [];
                    $input["entities_id"] = $this->fields["entities_id"];
                    $input["users_id"] = $this->fields["users_id_creator"];
                    $input["plugin_purchaserequest_purchaserequests_id"] = $this->fields["id"];
                    $input["users_id_validate"] = $config->fields["id_general_service_manager"];
                    $input["comment_validation"] = "";
                    $input["submission_date"] = $_SESSION["glpi_currenttime"];
                    $input["status"] = CommonITILValidation::WAITING;
                    $input["first"] = false;
                    $validation->add($input);
                }
            }
        }

        if (isset($this->fields['tickets_id'])) {
            $changes[0] = 0;
            $changes[1] = '';
            $changes[2] = $this->fields["id"];
            Log::history(
                $this->input['tickets_id'],
                'Ticket',
                $changes,
                __CLASS__,
                Log::HISTORY_PLUGIN + self::HISTORY_ADDLINK
            );
        }
    }

    /**
     * Actions done after the UPDATE of the item in the database
     *
     * @return nothing
     **/
    function post_updateItem($history = 1)
    {
        if (isset($this->oldvalues['tickets_id'])) {
            if ($this->oldvalues['tickets_id'] != 0) {
                $changes[0] = 0;
                $changes[1] = $this->input['id'];
                $changes[2] = '';
                Log::history(
                    $this->oldvalues['tickets_id'],
                    'Ticket',
                    $changes,
                    __CLASS__,
                    Log::HISTORY_PLUGIN + self::HISTORY_DELLINK
                );
            }
            if (!empty($this->fields['tickets_id'])) {
                {
                    $changes[0] = 0;
                    $changes[1] = '';
                    $changes[2] = $this->fields["id"];
                    Log::history(
                        $this->fields['tickets_id'],
                        'Ticket',
                        $changes,
                        __CLASS__,
                        Log::HISTORY_PLUGIN + self::HISTORY_ADDLINK
                    );
                }
            }
        }

        if (isset($this->oldvalues['users_id_validate'])) {
            $validation = new PluginPurchaserequestValidation();
            $validation->deleteByCriteria(
                [
                    "users_id_validate" => $this->oldvalues['users_id_validate'],
                    "plugin_purchaserequest_purchaserequests_id" => $this->fields["id"]
                ]
            );
            $input = [];
            $input["entities_id"] = $this->fields["entities_id"];
            $input["users_id"] = $this->fields["users_id_creator"];
            $input["plugin_purchaserequest_purchaserequests_id"] = $this->fields["id"];
            $input["users_id_validate"] = $this->fields["users_id_validate"];
            $input["comment_validation"] = "";
            $input["submission_date"] = $_SESSION["glpi_currenttime"];
            $input["status"] = CommonITILValidation::WAITING;
            $validation->add($input);
        }
    }


    /**
     * @param $input
     *
     * @return bool
     */
    function checkMandatoryFields($input)
    {
        $msg = [];
        $checkKo = false;

        $mandatory_fields = [
            'users_id' => __('Requester'),
            'comment' => __('Description'),
            'itemtype' => __('Item type'),
            'types_id' => __('Type'),
            'amount' => __("Amount", "purchaserequest"),
            'users_id_validate' => __('To be validated by', 'purchaserequest')
        ];

        foreach ($input as $key => $value) {
            if (array_key_exists($key, $mandatory_fields)) {
                if (empty($value)) {
                    if (($key == 'item' && $input['type'] == 'dropdown')
                        || ($key == 'label2' && $input['type'] == 'datetime_interval')) {
                        $msg[] = $mandatory_fields[$key];
                        $checkKo = true;
                    } elseif ($key != 'item' && $key != 'label2') {
                        $msg[] = $mandatory_fields[$key];
                        $checkKo = true;
                    }
                }
            }
            $_SESSION['glpi_plugin_purchaserequests_fields'][$key] = $value;
        }

        if ($checkKo) {
            Session::addMessageAfterRedirect(
                sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)),
                false,
                ERROR
            );
            return false;
        }
        return true;
    }

    /**
     * Get the Search options for the given Type
     *
     * This should be overloaded in Class
     *
     * @return an array of search options
     * More information on https://forge.indepnet.net/wiki/glpi/SearchEngine
     **/
    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id' => 'common',
            'name' => self::getTypeName()
        ];

        $tab[] = [
            'id' => '1',
            'table' => $this->getTable(),
            'field' => 'name',
            'name' => __('Name'),
            'datatype' => 'itemlink',
            'itemlink_type' => $this->getType()
        ];

        $tab[] = [
            'id' => 2,
            'table' => getTableForItemType('User'),
            'field' => 'name',
            'name' => __("Requester"),
            'linkfield' => 'users_id',
            'datatype' => 'dropdown'
        ];

        $tab = array_merge($tab, Location::rawSearchOptionsToAdd());

        $tab[] = [
            'id' => 4,
            'table' => $this->getTable(),
            'field' => 'itemtype',
            'name' => __("Item type"),
            'datatype' => 'specific',
            'massiveaction' => false,
            'itemtype_list' => 'plugin_order_types',
            'checktype' => 'itemtype',
            'searchtype' => ['equals'],
            'injectable' => true
        ];

        $tab[] = [
            'id' => 5,
            'table' => getTableForItemType('User'),
            'field' => 'name',
            'linkfield' => 'users_id_validate',
            'name' => __("Approver"),
            'datatype' => 'dropdown',
            'right' => 'plugin_purchaserequest_validate'
        ];

        $tab[] = [
            'id' => 6,
            'table' => $this->getTable(),
            'field' => 'due_date',
            'massiveaction' => false,
            'name' => __("Due date", "purchaserequest"),
            'datatype' => 'datetime'
        ];

        $tab[] = [
            'id' => 7,
            'table' => $this->getTable(),
            'field' => 'types_id',
            'name' => __("Type"),
            'massiveaction' => false,
            'checktype' => 'text',
            'searchtype' => ['equals'],
            'nosearch' => true
        ];

        $tab[] = [
            'id' => 8,
            'table' => $this->getTable(),
            'field' => 'status',
            'name' => __('Approval status'),
            'searchtype' => 'equals',
            'datatype' => 'specific'
        ];

        $tab[] = [
            'id' => 9,
            'table' => $this->getTable(),
            'field' => 'plugin_order_orders_id',
            'datatype' => 'itemlink',
            'massiveaction' => false,
            'name' => PluginOrderOrder::getTypeName()
        ];

        $tab[] = [
            'id' => 10,
            'table' => getTableForItemType('Ticket'),
            'field' => 'name',
            'datatype' => 'itemlink',
            'massiveaction' => false,
            'name' => Ticket::getTypeName(),
            'linkfield' => 'tickets_id',
        ];

        $tab[] = [
            'id' => 11,
            'table' => getTableForItemType('Group'),
            'field' => 'name',
            'name' => __("Requester group"),
            'linkfield' => 'groups_id',
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id' => 12,
            'table' => getTableForItemType('PluginPurchaserequestPurchaseRequestState'),
            'field' => 'name',
            'name' => __("Status"),
            'linkfield' => 'plugin_purchaserequest_purchaserequeststates_id',
            'datatype' => 'dropdown'
        ];

        $tab[] = [
            'id' => 13,
            'table' => $this->getTable(),
            'field' => 'date_mod',
            'name' => __('Last update'),
            'datatype' => 'datetime',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 14,
            'table' => $this->getTable(),
            'field' => 'date_creation',
            'name' => __('Creation date'),
            'datatype' => 'datetime',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => 15,
            'table' => $this->getTable(),
            'field' => 'processing_date',
            'name' => __('Treated on', "purchaserequest"),
            'datatype' => 'datetime',
            'massiveaction' => false
        ];

        /* comments */
        $tab[] = [
            'id' => 16,
            'table' => $this->getTable(),
            'field' => 'comment',
            'name' => __("Description"),
            'datatype' => 'text'
        ];
        /* amount */
        $tab[] = [
            'id' => 17,
            'table' => $this->getTable(),
            'field' => 'amount',
            'name' => __("Amount", "purchaserequest"),
            'datatype' => 'decimal'
        ];

        /* rebill */
        $tab[] = [
            'id' => 18,
            'table' => $this->getTable(),
            'field' => 'invoice_customer',
            'name' => __("To be rebilled to the customer", "purchaserequest"),
            'datatype' => 'bool'
        ];
        /* ID */
        $tab[] = [
            'id' => 30,
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __("ID"),
            'datatype' => 'number'
        ];

        /* entity */
        $tab[] = [
            'id' => 80,
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __("Entity"),
            'datatype' => 'dropdown'
        ];

        /* entity */
        $tab[] = [
            'id' => 86,
            'table' => $this->getTable(),
            'field' => 'is_recursive',
            'name' => __("Child entities"),
            'datatype' => 'bool',
            'massiveaction' => false
        ];

        $tab[] = [
            'id' => '59',
            'table' => 'glpi_users',
            'field' => 'name',
            'linkfield' => 'users_id_validate',
            'name' => __('Approver'),
            'datatype' => 'itemlink',

            'forcegroupby' => true,
            'massiveaction' => false,
            'joinparams' => [
                'beforejoin' => [
                    'table' => PluginPurchaserequestValidation::getTable(),
                    'joinparams' => [
                        'jointype' => 'child'
                    ]
                ]
            ]
        ];

        return $tab;
    }

    /**
     * @param $field
     * @param $values
     * @param $options   array
     **/
    static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'status':
                return CommonITILValidation::getStatus($values[$field]);
            case 'itemtype' :
                $item = new $values['itemtype']();
                return $item->getTypeName();
                break;
        }
        return parent::getSpecificValueToDisplay($field, $values, $options);
    }


    /**
     * @param $field
     * @param $name (default '')
     * @param $values (default '')
     * @param $options   array
     **/
    static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'status' :
                $options['value'] = $values[$field];
                return CommonITILValidation::dropdownStatus($name, $options);
            case 'itemtype':
                $types = PluginOrderOrder_Item::getClasses();
                $itemtype = [];
                foreach ($types as $key => $type) {
                    $item = new $type();
                    $itemtype[$type] = $item->getTypeName();
                }
                $options['display'] = false;
                return Dropdown::showFromArray($name, $itemtype, $options);
        }
        return parent::getSpecificValueToSelect($field, $name, $values, $options);
    }

    /**
     * @param       $ID
     * @param array $options
     *
     * @return bool
     */
    public function showForm($ID, $options = [])
    {
        global $CFG_GLPI;

        $dbu = new DbUtils();
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        $canedit = $this->can($ID, UPDATE);
        $options['canedit'] = $canedit;

        // Data saved in session
        if (isset($_SESSION['glpi_plugin_purchaserequests_fields'])) {
            foreach ($_SESSION['glpi_plugin_purchaserequests_fields'] as $key => $value) {
                if ($key == "comment") {
                    $this->fields[$key] = Glpi\RichText\RichText::getEnhancedHtml($value);
                } else {
                    $this->fields[$key] = $value;
                }

            }
            unset($_SESSION['glpi_plugin_purchaserequests_fields']);
        }

        /* title */
        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Name") . "</td><td>";
        if ($canedit) {
            echo Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
        } else {
            echo $this->fields["name"];
        }
        echo "</td><td colspan='2'></td></tr>";

        echo "</td></tr>";
        /* requester */
        echo "<tr class='tab_bg_1'><td>" . __("Requester") . "&nbsp;<span style='color:red;'>*</span></td><td>";
        if ($canedit) {
            $rand_user = User::dropdown([
                'name' => "users_id",
                'value' => $this->fields["users_id"],
                'entity' => $this->fields["entities_id"],
                'on_change' => "pluginPurchaserequestLoadGroups();",
                'right' => 'all'
            ]);
        } else {
            echo Dropdown::getDropdownName($dbu->getTableForItemType('User'), $this->fields["users_id"]);
        }
        echo "</td>";

        /* requester group */
        echo "<td>" . __("Requester group");
        echo "</td><td id='plugin_purchaserequest_group'>";

        if ($canedit) {
            if ($this->fields['users_id']) {
                self::displayGroup($this->fields['users_id']);
            }

            $JS = "function pluginPurchaserequestLoadGroups(){";
            $params = [
                'users_id' => '__VALUE__',
                'entity' => $this->fields["entities_id"]
            ];
            $JS .= Ajax::updateItemJsCode(
                "plugin_purchaserequest_group",
                PLUGIN_PURCHASEREQUEST_WEBDIR . "/ajax/dropdownGroup.php",
                $params,
                'dropdown_users_id' . $rand_user,
                false
            );
            $JS .= "}";
            echo Html::scriptBlock($JS);
        } else {
            echo Dropdown::getDropdownName($dbu->getTableForItemType('Group'), $this->fields["groups_id"]);
        }
        echo "</td></tr>";

        /* location */
        echo "<tr class='tab_bg_1'><td>" . __("Location") . "&nbsp;</td>";
        echo "<td>";
        Dropdown::show('Location', [
            'value' => $this->fields["locations_id"],
            'entity' => $this->fields["entities_id"]
        ]);
        echo "</td>";
        echo "<td>" . __("Status") . "&nbsp;</td>";
        echo "<td>";
        Dropdown::show(
            'PluginPurchaserequestPurchaseRequestState',
            [
                'value' => $this->fields["plugin_purchaserequest_purchaserequeststates_id"],
                'entity' => $this->fields["entities_id"]
            ]
        );
        echo "</td></tr>";

        /* description */
        echo "<tr class='tab_bg_1'><td>" . __("Description") . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td colspan='3'>";
        $opt = ["id"              => "comment",
            "name"            => "comment",
            "row"             => 4,
            "cols"            => 100,
            "enable_richtext" => true,
            "value"           => html_entity_decode(stripslashes($this->fields['comment']))];
        Html::textarea($opt);
        echo "</td></tr>";

        /* type */
        $reference = new PluginOrderReference();
        echo "<tr class='tab_bg_1'><td>" . __("Item type");
        echo "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";
        $params = [
            'myname' => 'itemtype',
            'value' => $this->fields["itemtype"],
            'entity' => $_SESSION["glpiactive_entity"],
            'ajax_page' => Plugin::getWebDir('order') . '/ajax/referencespecifications.php',
            'class' => __CLASS__,
        ];
        if (Session::getCurrentInterface() == 'central') {
            $reference->dropdownAllItems($params);
        } else {
            if ($item = new $this->fields["itemtype"]()) {
                echo $item->getTypeName();
            }
        }
        echo "</td>";

        echo "<td>" . __("Type") . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";
        echo "<span id='show_types_id'>";

        if ($this->fields['itemtype']) {
            if ($this->fields['itemtype'] == 'PluginOrderOther') {
                $file = 'other';
            } else {
                $file = $this->fields['itemtype'];
            }
            $core_typefilename = GLPI_ROOT . "/src/" . $file . "Type.php";
            $plugin_typefilename = Plugin::getWebDir('order') . "/inc/" . strtolower($file) . "type.class.php";
            $itemtypeclass = $this->fields['itemtype'] . "Type";

            if (file_exists($core_typefilename)
                || file_exists($plugin_typefilename)) {
                Dropdown::show(
                    $itemtypeclass,
                    [
                        'name' => "types_id",
                        'value' => $this->fields["types_id"],
                    ]
                );
            }
        }
        echo "</span>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __("Due date", "purchaserequest") . "</td>";
        echo "<td>";
        Html::showDateField("due_date", ['value' => $this->fields["due_date"]]);
        echo "</td>";

        echo "<td>" . __("To be validated by", "purchaserequest") . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";
        User::dropdown([
            'name' => "users_id_validate",
            'value' => $this->fields["users_id_validate"],
            'entity' => $this->fields["entities_id"],
            'right' => 'plugin_purchaserequest_validate'
        ]);
        echo "</td></tr>";
        echo "<tr class='tab_bg_1'><td>" . __(
                "Amount",
                "purchaserequest"
            ) . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";

        $amount = $this->fields['amount'] ?? number_format($this->fields['amount'], 2, '.', ' ');
        $params = [
            'type' => 'text',
            'value' => $amount
        ];
        echo Html::input('amount', $params);
        echo "</td>";

        echo "<td>" . __("To be rebilled to the customer", "purchaserequest") . "&nbsp;</td>";
        echo "<td>";
        Html::showCheckbox([
            'name' => "invoice_customer",
            'checked' => $this->fields["invoice_customer"]
        ]);

        echo "</td>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'>";
        $order = new PluginOrderOrder();
        $hidden = false;
        if ($this->fields["status"] != CommonITILValidation::ACCEPTED) {
            $hidden = "hidden";
        }
        echo "<td $hidden>" . __("Linked to the order", "purchaserequest") . "</td>";
        echo "<td $hidden>";

        $options = [];
        if ($order->getFromDB($this->fields['plugin_order_orders_id'])) {
            $options['value'] = $this->fields['plugin_order_orders_id'];
        }
        PluginOrderOrder::dropdown($options);
        echo "</td>";
        $ticket = new Ticket();
        echo "<td>" . __("Linked to ticket", "purchaserequest") . "</td>";
        echo "<td>";
        $options = [];
        if ($ticket->getFromDB($this->fields['tickets_id'])) {
            $options['value'] = $this->fields['tickets_id'];
        }
        $options['entity'] = $this->fields["entities_id"];
        Ticket::dropdown($options);
        echo "</td>";
        if ($hidden != false) {
            echo "<td colspan='2'></td>";
        }
        echo "</tr>";

        if ($ID > 0) {
            echo "<tr class='tab_bg_1'>";

            if ($this->fields['processing_date'] == null) {
                echo "<td>" . __("Treated", "purchaserequest") . "</td>";
                echo "<td>";
                Html::showCheckbox(['name' => 'is_process']);
                echo "</td>";
                echo "<td colspan='2'></td>";
            } else {
                echo "<th colspan='4'>" . __("Treated on", "purchaserequest");
                echo " " . Html::convDateTime($this->fields['processing_date']);
                echo "</th>";
            }
            echo "</tr>";
        }

        echo Html::hidden('users_id_creator', ['value' => $_SESSION['glpiID']]);

        if ($canedit) {
            $this->showFormButtons($options);
        } else {
            echo "</table></div>";
            Html::closeForm();
        }

        return true;
    }


    /**
     * @param $item
     */
    static function showForTicket($item)
    {
        $purchaserequest = new self();

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        if (isset($_REQUEST["start"])) {
            $start = $_REQUEST["start"];
        } else {
            $start = 0;
        }

        $datas = $purchaserequest->getItems($item->fields['id'], ['start' => $start, 'addLimit' => true]);
        $rows = count($purchaserequest->getItems($item->fields['id'], ['addLimit' => false]));

        //form
        if ($canedit) {
            $purchaserequest = new PluginPurchaserequestPurchaseRequest();
            $purchaserequest->showFormPurchase($item->fields['id']);
        }

        //Purchase request linked to the ticket
        if (!empty($datas) || count($datas) > 0) {
            if (Plugin::isPluginActive('order')) {
                $purchaserequest->listItems($datas, $canedit, $start, $rows);
            }
        } else {
            echo __('No item to display');
        }
    }

    /**
     * @param $tickets_id
     */
    static function showFormPurchase($tickets_id)
    {
        global $CFG_GLPI;

        $dbu = new DbUtils();
        $purchaserequest = new self();
        $purchaserequest->getEmpty();

        $ticket = new Ticket();
        $ticket->getFromDB($tickets_id);

        $purchaserequest->fields['entities_id'] = $ticket->fields['entities_id'];

        $actor = new Ticket_User();
        $actors = $actor->getActors($tickets_id);
        $count = 0;
        if (isset($actors[CommonITILActor::REQUESTER])) {
            $count = count($actors[CommonITILActor::REQUESTER]);
        }
        if ($count == 1 && $actor->getFromDBByCrit(
                ["`tickets_id` = $tickets_id AND `type` = " . CommonITILActor::REQUESTER]
            )) {
            $purchaserequest->fields['users_id'] = $actor->fields['users_id'];
        }

        echo "<form name='form' method='post' action='" . Toolbox::getItemTypeFormURL(
                'PluginPurchaserequestPurchaseRequest'
            ) . "'>";

        echo "<div align='center'><table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='4'>" . __('Add a purchase request', 'purchaserequest') . "</th></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Name") . "</td><td>";
        echo Html::input('name', ['value' => $purchaserequest->fields['name'], 'size' => 40]);

        //Ticket validator
        $ticket_validation = new TicketValidation();
        $ticket_validations = $ticket_validation->find([
            'tickets_id' => $tickets_id,
            'status' => CommonITILValidation::ACCEPTED
        ]);
        $users_validations = [];
        foreach ($ticket_validations as $validation) {
            $users_validations[] = $dbu->getUserName($validation['users_id_validate']);
        }

        echo "</td><td>" . __("Validated by", "purchaserequest") . "</td><td>";
        echo implode(', ', $users_validations);
        echo "</td></tr>";

        /* requester */
        echo "<tr class='tab_bg_1'><td>" . __("Requester") . "&nbsp;<span style='color:red;'>*</span></td><td>";
        $rand_user = User::dropdown([
            'name' => "users_id",
            'value' => $purchaserequest->fields["users_id"],
            'entity' => $purchaserequest->fields["entities_id"],
            'on_change' => "pluginPurchaserequestLoadGroups();",
            'right' => 'all'
        ]);

        echo "</td>";

        /* requester group */
        echo "<td>" . __("Requester group");
        echo "</td><td id='plugin_purchaserequest_group'>";

        if ($purchaserequest->fields['users_id']) {
            self::displayGroup($purchaserequest->fields['users_id']);
        }

        $JS = "function pluginPurchaserequestLoadGroups(){";
        $params = [
            'users_id' => '__VALUE__',
            'entity' => $purchaserequest->fields["entities_id"]
        ];
        $JS .= Ajax::updateItemJsCode(
            "plugin_purchaserequest_group",
            PLUGIN_PURCHASEREQUEST_WEBDIR . "/ajax/dropdownGroup.php",
            $params,
            'dropdown_users_id' . $rand_user,
            false
        );
        $JS .= "}";
        echo Html::scriptBlock($JS);

        echo "</td></tr>";

        /* location */
        echo "<tr class='tab_bg_1'><td>" . __("Location") . "&nbsp;</td>";
        echo "<td>";
        Dropdown::show('Location', [
            'value' => $purchaserequest->fields["locations_id"],
            'entity' => $purchaserequest->fields["entities_id"]
        ]);
        echo "</td>";
        echo "<td>" . __("Status") . "&nbsp;</td>";
        echo "<td>";
        Dropdown::show(
            'PluginPurchaserequestPurchaseRequestState',
            [
                'value' => $purchaserequest->fields["plugin_purchaserequest_purchaserequeststates_id"],
                'entity' => $purchaserequest->fields["entities_id"]
            ]
        );
        echo "</td></tr>";

        /* description */
        echo "<tr class='tab_bg_1'><td>" . __("Description") . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td colspan='3'>";
        $opt = ["id"              => "comment",
            "name"            => "comment",
            "row"             => 4,
            "cols"            => 100,
            "enable_richtext" => true,
            "value"           => html_entity_decode(stripslashes($purchaserequest->fields['comment']))];
        Html::textarea($opt);
        echo "</td></tr>";

        /* type */
        echo "<tr class='tab_bg_1'><td>" . __("Item type");
        echo "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";
        if (Plugin::isPluginActive('order')) {
            $reference = new PluginOrderReference();
            $params = [
                'myname' => 'itemtype',
                'value' => $purchaserequest->fields["itemtype"],
                'entity' => $_SESSION["glpiactive_entity"],
                'ajax_page' => Plugin::getWebDir('order') . '/ajax/referencespecifications.php',
                'class' => __CLASS__,
            ];

            $reference->dropdownAllItems($params);
        }
        echo "</td>";

        echo "<td>" . __("Type") . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";
        echo "<span id='show_types_id'>";
        if ($purchaserequest->fields['itemtype']) {
            if ($purchaserequest->fields['itemtype'] == 'PluginOrderOther') {
                $file = 'other';
            } else {
                $file = $purchaserequest->fields['itemtype'];
            }
            $core_typefilename = GLPI_ROOT . "/src/" . $file . "Type.php";
            $plugin_typefilename = Plugin::getWebDir('order') . "/inc/" . strtolower($file) . "type.class.php";
            $itemtypeclass = $purchaserequest->fields['itemtype'] . "Type";

            if (file_exists($core_typefilename)
                || file_exists($plugin_typefilename)
            ) {
                Dropdown::show(
                    $itemtypeclass,
                    [
                        'name' => "types_id",
                        'value' => $purchaserequest->fields["types_id"],
                    ]
                );
            }
        }
        echo "</span>";
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'><td>" . __("Due date", "purchaserequest") . "</td>";
        echo "<td>";
        Html::showDateField("due_date", ['value' => $purchaserequest->fields["due_date"]]);
        echo "</td>";

        echo "<td>" . __("To be validated by", "purchaserequest") . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";
        User::dropdown([
            'name' => "users_id_validate",
            'value' => $purchaserequest->fields["users_id_validate"],
            'entity' => $purchaserequest->fields["entities_id"],
            'right' => 'plugin_purchaserequest_validate'
        ]);
        echo "</td>";
        echo "</tr>";
        echo "</tr>";
        echo "<tr class='tab_bg_1'><td>" . __(
                "Amount",
                "purchaserequest"
            ) . "&nbsp;<span style='color:red;'>*</span></td>";
        echo "<td>";
        $amount = $purchaserequest->fields['amount'] ?? number_format($purchaserequest->fields['amount'], 2, '.', ' ');
        $params = [
            'type' => 'text',
            'value' => $amount
        ];
        echo Html::input('amount', $params);
        echo "</td>";

        echo "<td>" . __("To be rebilled to the customer", "purchaserequest") . "&nbsp;</td>";
        echo "<td>";
        Html::showCheckbox([
            'name' => "invoice_customer",

        ]);

        echo "</td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td class='tab_bg_2 center' colspan='4'>";
        echo Html::submit(_sx('button', 'Add'), ['name' => 'add_tickets', 'class' => 'btn btn-primary']);
        echo Html::hidden('tickets_id', ['value' => $tickets_id]);
        echo Html::hidden('entities_id', ['value' => $purchaserequest->fields['entities_id']]);
        echo Html::hidden('users_id_creator', ['value' => $_SESSION['glpiID']]);
        echo "</td>";
        echo "</tr>";
        echo "</table>";
        Html::closeForm();
    }

    /**
     * listItems
     *
     * @param array $data
     * @param bool $canedit
     * @param int $start
     */
    private function listItems($data, $canedit, $start, $rows)
    {
        $rand = mt_rand();
        $dbu = new DbUtils();

        Html::printAjaxPager(PluginPurchaserequestPurchaseRequest::getTypeName(2), $start, $rows);

        echo "<div class='left'>";
        if ($canedit) {
            Html::openMassiveActionsForm('mass' . __CLASS__ . $rand);
            $massiveactionparams = ['item' => __CLASS__, 'container' => 'mass' . __CLASS__ . $rand];
            Html::showMassiveActions($massiveactionparams);
        }
        echo "</div>";
        echo "<div class='center'>";

        echo "<table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_1'>";
        echo "<th width='10'>";
        if ($canedit) {
            echo Html::getCheckAllAsCheckbox('mass' . __CLASS__ . $rand);
        }
        echo "</th>";
        echo "<th>" . __('Name') . "</th>";
        echo "<th>" . __('Requester') . "</th>";
        echo "<th>" . __('Requester group') . "</th>";
        echo "<th>" . __('Location') . "</th>";
        echo "<th>" . __('Status') . "</th>";
        echo "<th>" . __('Item type') . "</th>";
        echo "<th>" . __('Type') . "</th>";
        echo "<th>" . __('Due date', 'purchaserequest') . "</th>";
        echo "<th>" . __('Treated on', 'purchaserequest') . "</th>";
        echo "<th>" . __('Amount', 'purchaserequest') . "</th>";
        echo "<th>" . __('To be rebilled to the customer', 'purchaserequest') . "</th>";
        echo "<th>" . __('Approver') . "</th>";
        echo "<th>" . __('Approval status') . "</th>";
        echo "<th>" . PluginOrderOrder::getTypeName() . "</th>";
        echo "</tr>";

        foreach ($data as $field) {
            echo "<tr class='tab_bg_1'>";
            echo "<td width='10'>";
            if ($canedit) {
                Html::showMassiveActionCheckBox(__CLASS__, $field['id']);
            }
            echo "</td>";
            // Name
            $purchase_request = new PluginPurchaserequestPurchaseRequest();
            $purchase_request->getFromDB($field['id']);
            echo "<td>" . $purchase_request->getLink() . "</td>";
            // requester
            echo "<td>" . $dbu->getUserName($field['users_id']) . "</td>";
            // requester group
            echo "<td>" . Dropdown::getDropdownName('glpi_groups', $field['groups_id']) . "</td>";
            // location
            echo "<td>" . Dropdown::getDropdownName('glpi_locations', $field['locations_id']) . "</td>";
            // state
            echo "<td>" . Dropdown::getDropdownName(
                    'glpi_plugin_purchaserequest_purchaserequeststates',
                    $field['plugin_purchaserequest_purchaserequeststates_id']
                ) . "</td>";
            // item type
            $item = new $field["itemtype"]();
            echo "<td>" . $item->getTypeName() . "</td>";
            // Model name
            $itemtypeclass = $field['itemtype'] . "Type";
            echo "<td>" . Dropdown::getDropdownName(
                    $dbu->getTableForItemType($itemtypeclass),
                    $field["types_id"]
                ) . "</td>";
            //due date
            echo "<td>" . Html::convDate($field['due_date']) . "</td>";
            //traited
            echo "<td>" . Html::convDate($field['processing_date']) . "</td>";
            //amount
            echo "<td>" . number_format($field['amount'], 2, '.', ' ') . " €</td>";
            //rebill
            echo "<td>" . Dropdown::getYesNo($field['invoice_customer']) . "</td>";
            // validation
            echo "<td>" . $dbu->getUserName($field['users_id_validate']) . "</td>";
            //status validation
            echo "<td>" . CommonITILValidation::getStatus($field['status']) . "</td>";
            //link with order
            $order = new PluginOrderOrder();
            $order->getFromDB($field['plugin_order_orders_id']);
            echo "<td>" . $order->getLink() . "</td>";
            echo "</tr>";
        }

        echo "</table>";
        echo "</div>";
        echo "<div class='left'>";
        if ($canedit) {
            $massiveactionparams['ontop'] = false;
            Html::showMassiveActions($massiveactionparams);
            Html::closeForm();
        }
        echo "</div>";
    }

    /**
     * @param int $tickets_id
     * @param array $options
     *
     * @return \all
     */
    function getItems($tickets_id = 0, $options = [])
    {
        global $DB;

        $params['start'] = 0;
        $params['limit'] = $_SESSION['glpilist_limit'];
        $params['addLimit'] = true;

        if (!empty($options)) {
            foreach ($options as $key => $val) {
                $params[$key] = $val;
            }
        }

        $output = [];

        $query = "SELECT *
          FROM " . $this->getTable() . "
          WHERE !`is_deleted` AND `" . $this->getTable() . "`.`tickets_id` = $tickets_id";

        if ($params['addLimit']) {
            $query .= " LIMIT " . intval($params['start']) . "," . intval($params['limit']);
        }

        $result = $DB->query($query);
        if ($DB->numrows($result)) {
            while ($data = $DB->fetchAssoc($result)) {
                $output[$data['id']] = $data;
            }
        }

        return $output;
    }

    /**
     * Display list of purchase request linked to the order
     *
     * @param $item
     */
    static function showForOrder($item)
    {
        global $CFG_GLPI;

        $dbu = new DbUtils();
        if (isset($_REQUEST["start"])) {
            $start = $_REQUEST["start"];
        } else {
            $start = 0;
        }

        $purchase_request = new PluginPurchaserequestPurchaseRequest();
        $data = $purchase_request->find(['plugin_order_orders_id' => $item->fields['id']]);

        $rows = count($data);

        $canread = Session::haveRight(self::$rightname, READ);

        if (!$rows || !$canread) {
            echo __('No item to display');
        } else {
            $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
            $rand = mt_rand();

            echo "<div class='center'>";

            echo "<form method='post' name='purchaseresquet_form$rand' id='purchaseresquet_form$rand'  " .
                "action='" . Toolbox::getItemTypeFormURL('PluginPurchaserequestPurchaseRequest') . "'>";

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr class='tab_bg_1 center'>";
            echo "<th colspan='13'>" . PluginPurchaserequestPurchaseRequest::getTypeName(2) . "</th>";
            echo "</tr>";
            echo "<tr class='tab_bg_1'>";

            if ($canedit) {
                echo "<th></th>";
            }
            echo "<th>" . __('Name') . "</th>";
            echo "<th>" . __('Requester') . "</th>";
            echo "<th>" . __('Requester group') . "</th>";
            echo "<th>" . __('Location') . "</th>";
            echo "<th>" . __('Status') . "</th>";
            echo "<th>" . __('Item type') . "</th>";
            echo "<th>" . __('Type') . "</th>";
            echo "<th>" . __('Due date', 'purchaserequest') . "</th>";
            echo "<th>" . __('Treated on', 'purchaserequest') . "</th>";
            echo "<th>" . __('Approver') . "</th>";
            echo "<th>" . __('Approval status') . "</th>";
            echo "<th>" . PluginOrderOrder::getTypeName() . "</th>";
            echo "</tr>";

            foreach ($data as $field) {
                echo "<tr class='tab_bg_1'>";
                if ($canedit) {
                    echo "<td width='10'>";
                    $sel = "";
                    if (isset($_GET["select"]) && $_GET["select"] == "all") {
                        $sel = "checked";
                    }
                    echo "<input type='checkbox' name='item[" . $field["id"] . "]' value='1' $sel>";
                    echo Html::hidden('plugin_order_orders_id', ['value' => $item->getID()]);
                    echo "</td>";
                }
                // Name
                $purchase_request = new PluginPurchaserequestPurchaseRequest();
                $purchase_request->getFromDB($field['id']);
                echo "<td>" . $purchase_request->getLink() . "</td>";
                // requester
                echo "<td>" . $dbu->getUserName($field['users_id']) . "</td>";
                // requester group
                echo "<td>" . Dropdown::getDropdownName('glpi_groups', $field['groups_id']) . "</td>";
                // location
                echo "<td>" . Dropdown::getDropdownName('glpi_locations', $field['locations_id']) . "</td>";
                // state
                echo "<td>" . Dropdown::getDropdownName(
                        'glpi_plugin_purchaserequest_purchaserequeststates',
                        $field['plugin_purchaserequest_purchaserequeststates_id']
                    ) . "</td>";
                // item type
                $item = new $field["itemtype"]();
                echo "<td>" . $item->getTypeName() . "</td>";
                // Model name
                $itemtypeclass = $field['itemtype'] . "Type";
                echo "<td>" . Dropdown::getDropdownName(
                        $dbu->getTableForItemType($itemtypeclass),
                        $field["types_id"]
                    ) . "</td>";
                //due date
                echo "<td>" . Html::convDate($field['due_date']) . "</td>";
                //traited
                echo "<td>" . Html::convDate($field['processing_date']) . "</td>";
                // validation
                echo "<td>" . $dbu->getUserName($field['users_id_validate']) . "</td>";
                //status
                echo "<td>" . CommonITILValidation::getStatus($field['status']) . "</td>";
                //link with order
                $order = new PluginOrderOrder();
                $order->getFromDB($field['plugin_order_orders_id']);
                echo "<td>" . $order->getLink() . "</td>";
                echo "</tr>";
            }

            echo "</table>";
            if ($canedit) {
                echo "<div class='left'>";
                echo "<table width='950px' class='left'>";
                echo "<tr><td><img src=\"" . $CFG_GLPI["root_doc"]
                    . "/pics/arrow-left.png\" alt=''></td><td class='center'>";
                echo "<a onclick= \"if ( markCheckboxes('purchaseresquet_form$rand') ) "
                    . "return false;\" href='#'>" . __("Check all") . "</a></td>";

                echo "<td>/</td><td class='center'>";
                echo "<a onclick= \"if ( unMarkCheckboxes('purchaseresquet_form$rand') ) "
                    . "return false;\" href='#'>" . __("Uncheck all") . "</a>";
                echo "</td><td align='left' width='80%'>";
                echo Html::hidden('plugin_order_orders_id', ['value' => $item->getID()]);
                $purchase_request->dropdownPurchaseRequestItemsActions();
                echo "&nbsp;";
                echo Html::submit(_sx('button', 'Post'), ['name' => 'action', 'class' => 'btn btn-primary']);
                echo "</td>";
                echo "</table>";
            }
            Html::closeForm();
            echo "</div>";
        }
    }

    /**
     *
     */
    public function dropdownPurchaseRequestItemsActions()
    {
        $action['delete_link'] = __("Delete link with order", "purchaserequest");
        Dropdown::showFromArray('chooseAction', $action);
    }

    /**
     * @param $item
     */
    static function showValidation($item)
    {
        $dbu = new DbUtils();
        $validator = ($item->fields["users_id_validate"] == Session::getLoginUserID());

        echo "<form name='form' id='formvalidation' method='post' action='" . Toolbox::getItemTypeFormURL(
                'PluginPurchaserequestPurchaseRequest'
            ) . "'>";

        echo "<div align='center'><table class='tab_cadre_fixe'>";
        echo "<tr class='tab_bg_2'>";
        echo "<th colspan='3'>" . __('Do you approve this purchase request ?', 'purchaserequest') . "</th>";
        echo "</tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td colspan='2'>" . __('Approval requester') . "</td>";
        echo "<td class='center'>" . $dbu->getUserName($item->fields["users_id_creator"]) . "</td></tr>";

        echo "<tr class='tab_bg_1'><td colspan='2'>" . __('Approver') . "</td>";
        echo "<td class='center'>" . $dbu->getUserName($item->fields["users_id_validate"]) . "</td></tr>";
        echo "</td></tr>";

        if ($validator && $item->fields["status"] == CommonITILValidation::WAITING) {
            //         echo "<tr class='tab_bg_1'>";
            //         echo "<td>" . __('Status of my validation') . "</td>";
            //         echo "<td>";
            //         CommonITILValidation::dropdownStatus("status", ['value' => $item->fields["status"]]);
            //         echo "</td></tr>";

            //         echo "<tr class='tab_bg_2'>";
            //         echo "<th colspan='4'>" . __('Do you approve this purchase request ?', 'purchaserequest') . "</th>";
            //         echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Status of the approval request') . "</td>";
            echo "<td class='center'>";
            echo "<div style='color:forestgreen'><i id='accept_purchaserequest' class='question far fa-check-circle fa-3x'>";
            echo "</i><br>" . __('Accept purchase request', 'purchaserequest') . "</div>";
            echo Html::hidden('accept_purchaserequest', ['value' => 0]);
            echo "</td>";
            echo "<td class='center'>";
            echo "<div style='color:darkred'><i id='refuse_purchaserequest' class='question far fa-times-circle fa-3x'>";
            echo "</i><br>" . __('Refuse purchase request', 'purchaserequest') . "</div>";
            echo Html::hidden('refuse_purchaserequest', ['value' => 0]);
            echo "</td>";
            echo "</tr>";

            echo Html::scriptBlock(
                '$( "#accept_purchaserequest" ).click(function() {
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'accept_purchaserequest\' value=\'1\' />");
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'update_status\' value=\'1\' />");
                                $( "#formvalidation" ).submit();
                              });
                              $( "#refuse_purchaserequest" ).click(function() {
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'refuse_purchaserequest\' value=\'1\' />");
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'update_status\' value=\'1\' />");
                                $( "#formvalidation" ).submit();
                              });'
            );
            echo "</td>";
            echo "</tr>";

            echo "<tr class='tab_bg_1'>";
            echo "<td>" . __('Approval comments') . "</td>";
            echo "<td colspan='2'>";
            Html::textarea([
                'name' => 'comment_validation',
                'value' => $item->fields["comment_validation"],
                'enable_richtext' => false,
                'cols' => '90',
                'rows' => '3'
            ]);
            echo Html::hidden('id', ['value' => $item->fields['id']]);
            echo "</td></tr>";
        } else {
            echo "<tr class='tab_bg_1'>";
            echo "<td colspan='2'>" . __('Status of the approval request') . "</td>";
            //         $bgcolor = CommonITILValidation::getStatusColor($item->fields['status']);
            echo "<td class='center'>";
            $status = CommonITILValidation::getStatus($item->fields["status"]);
            if ($item->fields['status'] == CommonITILValidation::ACCEPTED) {
                echo "<div style='color:forestgreen'><i class='far fa-check-circle fa-3x'></i><br>" . $status . "</div>";
            } elseif ($item->fields['status'] == CommonITILValidation::REFUSED) {
                echo "<div style='color:darkred'><i class='far fa-times-circle fa-3x'></i><br>" . $status . "</div>";
            } else {
                echo "<div style='color:orange'><i class='far fa-question-circle fa-3x'></i><br>" . $status . "</div>";
            }
            echo "</td></tr>";

            $status = [CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED];
            if (in_array($item->fields["status"], $status) && !empty($item->fields["comment_validation"])) {
                echo "<tr class='tab_bg_1'>";
                echo "<td colspan='2'>" . __('Approval comments') . "</td>";
                echo "<td>" . $item->fields["comment_validation"] . "</td></tr>";
            }
        }
        echo "</table></div>";
        Html::closeForm();
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
     **/
    static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'link':
                PluginOrderOrder::dropdown();
                echo "&nbsp;" .
                    Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;

            case 'delete_link':
                echo "&nbsp;" .
                    Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;

            case 'validate':
                CommonITILValidation::dropdownStatus('status');
                echo "</br>" .
                    Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;
        }
        return "";
    }

    /**
     * Get the specific massive actions
     *
     * @param $checkitem link item to check right   (default NULL)
     *
     * @return an array of massive actions
     **@since version 0.84
     *
     * This should be overloaded in Class
     *
     */
    function getSpecificMassiveActions($checkitem = null)
    {
        $actions['PluginPurchaserequestPurchaseRequest:link'] = __("Link to an order", "purchaserequest");
        $actions['PluginPurchaserequestPurchaseRequest:delete_link'] = __("Delete link to order", "purchaserequest");
        if (self::canValidation()) {
            $actions['PluginPurchaserequestPurchaseRequest:validate'] = __(
                "Validate purchasing requests",
                "purchaserequest"
            );
        }

        $isadmin = static::canUpdate();
        if ($isadmin) {
            if (Session::haveRight('transfer', READ)
                && Session::isMultiEntitiesMode()) {
                $actions['PluginOrderOrder:transfert'] = __('Transfer');
            }
        }

        return $actions;
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::processMassiveActionsForOneItemtype()
     **/
    static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case "link":
                $input = $ma->getInput();
                $order_id = $input['plugin_order_orders_id'];

                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        //Possible connection with an order if purchase request is validated
                        if ($item->fields['status'] == CommonITILValidation::ACCEPTED) {
                            $item->update([
                                "id" => $id,
                                "plugin_order_orders_id" => $order_id,
                                "update" => __('Update'),
                            ]);
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        }
                    }
                }
                return;
                break;

            case "delete_link":

                foreach ($ids as $id) {
                    if ($item->getFromDB($id)) {
                        $item->update([
                            "id" => $id,
                            "plugin_order_orders_id" => 0,
                            "update" => __('Update'),
                        ]);
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    }
                }
                return;
                break;
            case "validate":
                if (self::canValidation()) {
                    $input = $ma->getInput();
                    $validation = $input['status'];
                    foreach ($ids as $id) {
                        if ($item->getFromDB($id)) {
                            if ($item->fields['users_id_validate'] == Session::getLoginUserID()) {
                                $item->update([
                                    "id" => $id,
                                    "update_status" => true,
                                    "status" => $validation,
                                    "comment_validation" => "",
                                    "update" => __('Update'),
                                ]);
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            }
                        }
                    }
                } else {
                    $ma->itemDone($item->getType(), 0, MassiveAction::ACTION_NORIGHT);
                }
                break;
        }
        return;
    }

    /**
     * Users groups dropdown list
     *
     * @param $users_id
     */
    static function displayGroup($users_id)
    {
        //list of groups
        $group_users = Group_User::getUserGroups($users_id);
        $groups = [];

        foreach ($group_users as $item) {
            $groups[] = $item['id'];
        }

        if (count($groups) > 0) {
            $condition['condition'] = ['id' => $groups];
            Group::dropdown($condition);
        } else {
            echo __('No groups for this user', 'purchaserequest');
        }
    }

    /**
     * Get an history entry message
     *
     * @param $data Array from glpi_logs table
     *
     * @return string
     **/
    static function getHistoryEntry($data)
    {
        switch ($data['linked_action'] - Log::HISTORY_PLUGIN) {
            case self::HISTORY_ADDLINK :
                return sprintf(
                    __('%1$s: %2$s'),
                    __('Add a link with an item'),
                    $data["new_value"]
                );

            case self::HISTORY_DELLINK :
                return sprintf(
                    __('%1$s: %2$s'),
                    __('Delete a link with an item'),
                    $data["old_value"]
                );
        }
        return '';
    }

    static function transfer($ID, $entity)
    {
        global $DB;

        if ($ID > 0) {
            // Not already transfer
            // Search init item
            $query = "SELECT *
                   FROM `glpi_plugin_purchaserequest_purchaserequests`
                   WHERE `id` = '$ID'";

            if ($result = $DB->query($query)) {
                if ($DB->numrows($result)) {
                    $data = $DB->fetchAssoc($result);
                    $data = Toolbox::addslashes_deep($data);
                    $input['name'] = $data['name'];
                    $input['entities_id'] = $entity;
                    $temp = new self();
                    $newID = $temp->getID($input);

                    if ($newID < 0) {
                        $newID = $temp->import($input);
                    }

                    return $newID;
                }
            }
        }
        return 0;
    }

    /**
     * @param \Migration $migration
     */
    public static function install(Migration $migration)
    {
        global $DB;

        $dbu = new DbUtils();
        $table = $dbu->getTableForItemType(__CLASS__);

        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_purchaserequest_purchaserequests` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `entities_id` int unsigned NOT NULL DEFAULT '0',
                    `is_recursive` int unsigned NOT NULL DEFAULT '0',
                    `name` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                    `users_id` int unsigned NOT NULL DEFAULT '0',
                    `groups_id` int unsigned NOT NULL DEFAULT '0',
                    `comment` TEXT COLLATE utf8mb4_unicode_ci,
                    `itemtype` VARCHAR(255) NOT NULL,
                    `types_id` int unsigned NOT NULL DEFAULT '0',
                    `due_date` timestamp NULL DEFAULT NULL,
                    `users_id_validate` int unsigned NOT NULL DEFAULT '0',
                    `users_id_creator` int unsigned NOT NULL DEFAULT '0',
                    `status` int unsigned NOT NULL DEFAULT '0',
                    `comment_validation` TEXT COLLATE utf8mb4_unicode_ci,
                    `tickets_id` int unsigned NOT NULL DEFAULT '0',
                    `plugin_order_orders_id` int unsigned NOT NULL DEFAULT '0',
                    `date_mod` timestamp NULL DEFAULT NULL,
                    `date_creation` timestamp NULL DEFAULT NULL,
                    `is_deleted` tinyint NOT NULL DEFAULT '0',
                    `locations_id` int unsigned NOT NULL DEFAULT '0',
                    `plugin_purchaserequest_purchaserequeststates_id` int unsigned NOT NULL DEFAULT '0',
                    `processing_date` timestamp NULL DEFAULT NULL,
                    `invoice_customer` tinyint NOT NULL DEFAULT '0',
                    `amount` decimal(20, 4) NOT NULL DEFAULT '0.0000',
                    PRIMARY KEY (`id`),
                    KEY `users_id` (`users_id`),
                    KEY `groups_id` (`groups_id`),
                    KEY `users_id_validate` (`users_id_validate`),
                    KEY `users_id_creator` (`users_id_creator`),
                    KEY `tickets_id` (`tickets_id`),
                    KEY `is_deleted` (`is_deleted`),
                    KEY `date_mod` (`date_mod`)
                  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die ($DB->error());
        } else {
            if (!$DB->fieldExists($table, 'locations_id')) {
                $DB->query(
                    "ALTER TABLE `glpi_plugin_purchaserequest_purchaserequests`
                     ADD `locations_id` int unsigned NOT NULL DEFAULT '0';"
                );
            }
            if (!$DB->fieldExists($table, 'plugin_purchaserequest_purchaserequeststates_id')) {
                $DB->query(
                    "ALTER TABLE `glpi_plugin_purchaserequest_purchaserequests`
                     ADD `plugin_purchaserequest_purchaserequeststates_id` int unsigned NOT NULL DEFAULT '0';"
                );

                $DB->query(
                    "ALTER TABLE `glpi_plugin_purchaserequest_purchaserequests`
                     ADD `is_deleted` tinyint NOT NULL DEFAULT '0';"
                );
            }

            if (!$DB->fieldExists($table, 'processing_date')) {
                $DB->query(
                    "ALTER TABLE `glpi_plugin_purchaserequest_purchaserequests`
                     ADD `processing_date` timestamp NULL DEFAULT NULL;"
                );
            }

            if (!$DB->fieldExists($table, 'invoice_customer')) {
                $DB->query(
                    "ALTER TABLE `glpi_plugin_purchaserequest_purchaserequests`
                     ADD `invoice_customer` tinyint NOT NULL DEFAULT '0';"
                );
                $DB->query(
                    "ALTER TABLE `glpi_plugin_purchaserequest_purchaserequests`
                     ADD `amount` int unsigned NOT NULL DEFAULT '0';"
                );
            }

            $DB->query(
                "ALTER TABLE `glpi_plugin_purchaserequest_purchaserequests`
                   CHANGE `amount` `amount` decimal(20, 4) NOT NULL DEFAULT '0.0000';"
            );
        }
    }

    public static function uninstall()
    {
        global $DB;

        $dbu = new DbUtils();
        $table = $dbu->getTableForItemType(__CLASS__);
        foreach (["displaypreferences", "documents_items", "savedsearches", "logs"] as $t) {
            $query = "DELETE FROM `glpi_$t` WHERE `itemtype` = '" . __CLASS__ . "'";
            $DB->query($query);
        }
        $DB->query("DROP TABLE IF EXISTS`" . $table . "`") or die ($DB->error());
    }

    //static function getMenuContent() {

    //   $menu                    = [];
    //   $menu['title']           = self::getMenuName();
    //   $menu['page']            = self::getSearchURL(false);
    //   $menu['links']['search'] = self::getSearchURL(false);
    //   if (self::canCreate()) {
    //      $menu['links']['add'] = self::getFormURL(false);
    //   }
    //   $menu['icon']    = self::getIcon();
    //Entry icon in breadcrumb
    //    $menu['links']['config']                      = PluginPurchaserequestConfig::getFormURL(false);
    //Link to config page in admin plugins list
    //    $menu['config_page']                          = PluginPurchaserequestConfig::getFormURL(false);

    //   return $menu;
    //}

}
