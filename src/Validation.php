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

use Ajax;
use CommonDBTM;
use CommonGLPI;
use CommonITILValidation;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;
use Html;
use Log;
use Migration;
use NotificationEvent;
use PluginOrderOrder;
use PluginOrderReference;
use Session;
use Ticket;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class Validation
 */
class Validation extends CommonDBTM
{
    public static $rightname = 'plugin_purchaserequest_validate';
    public $dohistory = true;

    public const HISTORY_ADDLINK = 50;
    public const HISTORY_DELLINK = 51;

    /**
     * @param int $nb
     *
     * @return string|\translated
     */
    public static function getTypeName($nb = 0)
    {
        return _n("Validation", "Validations", $nb, "purchaserequest");
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

        if ($item->getType() == PurchaseRequest::class) {
            return self::createTabEntry(__('Approval'));
        }

        return '';
    }

    public static function getIcon()
    {
        return "ti ti-check";
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

        if ($item->getType() == PurchaseRequest::class) {
            self::showValidation($item);
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

        // Harden against mass assignment: these columns are managed by GLPI core
        // or fixed at creation and must never be mutated through a generic update.
        // The designated approver (users_id_validate) is set once when the
        // validation is created and has no legitimate reassignment flow here.
        unset(
            $input['is_deleted'],
            $input['is_recursive'],
            $input['users_id_validate'],
            $input['date_creation'],
            $input['date_mod']
        );

        // Server-side enforcement of the approval workflow: only the designated
        // approver (users_id_validate) may accept/refuse or change the status.
        // front/validation.form.php only checks the generic UPDATE right, and the
        // validator check in showValidation() is UI-only, so without this gate any
        // user holding UPDATE could self-approve by POSTing the accept/refuse/
        // update_status flags against an arbitrary validation id.
        $is_validation_action = isset($input['refuse_purchaserequest'])
            || isset($input['accept_purchaserequest'])
            || isset($input['update_status']);
        $is_validator = (int) ($this->fields['users_id_validate'] ?? 0) === (int) Session::getLoginUserID();
        if ($is_validation_action && !$is_validator) {
            unset(
                $input['refuse_purchaserequest'],
                $input['accept_purchaserequest'],
                $input['update_status'],
                $input['status']
            );
            Session::addMessageAfterRedirect(
                __('You are not allowed to approve or refuse this purchase request.', 'purchaserequest'),
                false,
                ERROR
            );
            return $input;
        }

        if (isset($input['refuse_purchaserequest']) && $input['refuse_purchaserequest'] == 1) {
            $input['status'] = CommonITILValidation::REFUSED;
        }

        if (isset($input['accept_purchaserequest']) && $input['accept_purchaserequest'] == 1) {
            $input['status'] = CommonITILValidation::ACCEPTED;
        }

        if (isset($input['update_status'])) {
            $input['validation_date'] = $_SESSION["glpi_currenttime"];
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

        if ($CFG_GLPI["notifications_mailing"]) {
            if (isset($this->input["first"])
                && $this->input["first"] == true) {
                $purchase_request = new PurchaseRequest();
                $purchase_request->getFromDB($this->fields["plugin_purchaserequest_purchaserequests_id"]);
                $options = ['validation_id'     => $this->fields["id"],
                    'validation_status' => $this->fields["status"]];
                NotificationEvent::raiseEvent('ask_purchaserequest', $purchase_request, $options);
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
    public function post_updateItem($history = 1)
    {
        global $CFG_GLPI;
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
        if (isset($this->input["update_status"])) {
            $purchase_request = new PurchaseRequest();
            if (isset($this->input['status'])
                && $this->input['status'] == CommonITILValidation::REFUSED) {
                $input["status"] = CommonITILValidation::REFUSED;
                $input["id"]     = $this->fields["plugin_purchaserequest_purchaserequests_id"];
                $purchase_request->update($input);
            } elseif (isset($this->input['status'])
                       && $this->input['status'] == CommonITILValidation::ACCEPTED) {
                $input["id"] = $this->fields["plugin_purchaserequest_purchaserequests_id"];
                $items       = $this->find(["plugin_purchaserequest_purchaserequests_id" => $this->fields["plugin_purchaserequest_purchaserequests_id"]]);
                $validation  = true;
                foreach ($items as $item) {
                    if ($item["status"] != CommonITILValidation::ACCEPTED) {
                        $validation = false;
                    }
                }

                if ($validation == true) {
                    $input["status"] = CommonITILValidation::ACCEPTED;
                    $purchase_request->update($input);
                }
            }
            if ($CFG_GLPI["notifications_mailing"]) {
                $purchase_request->getFromDB($this->fields["plugin_purchaserequest_purchaserequests_id"]);

                if (count($this->updates)) {
                    $options = ['validation_id'     => $this->fields["id"],
                        'validation_status' => $this->fields["status"]];
                    //               NotificationEvent::raiseEvent('validation_answer', $purchase_request, $options);
                    if (isset($this->input['status'])
                        && $this->input['status'] == CommonITILValidation::ACCEPTED) {
                        if ($validation == true && $purchase_request->fields["status"] == CommonITILValidation::ACCEPTED) {
                            NotificationEvent::raiseEvent('validation_purchaserequest', $purchase_request, $options);
                        } elseif ($purchase_request->fields["status"] == CommonITILValidation::WAITING) {
                            $items = $this->find(["plugin_purchaserequest_purchaserequests_id" => $this->fields["plugin_purchaserequest_purchaserequests_id"]]);

                            foreach ($items as $item) {
                                if ($item["status"] == CommonITILValidation::WAITING) {
                                    $options = ['validation_id'     => $item["id"],
                                        'validation_status' => $item["status"]];
                                    NotificationEvent::raiseEvent('ask_purchaserequest', $purchase_request, $options);
                                }
                            }
                        }
                    } elseif (isset($this->input['status'])
                               && $this->input['status'] == CommonITILValidation::REFUSED) {
                        NotificationEvent::raiseEvent('no_validation_purchaserequest', $purchase_request, $options);
                    }
                }
            }
        }
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

        $canedit            = $this->can($ID, UPDATE);
        $options['canedit'] = $canedit;

        // Repopulate form fields saved on a previous failed submission (same item only)
        $session_id = (int) $ID;
        if (isset($_SESSION['glpi_plugin_purchaserequests_fields'][$session_id])) {
            foreach ($_SESSION['glpi_plugin_purchaserequests_fields'][$session_id] as $key => $value) {
                if ($key === 'id') {
                    continue; // never overwrite the item id from session
                }
                if ($key == "comment") {
                    $this->fields[$key] = RichText::getEnhancedHtml($value);
                } else {
                    $this->fields[$key] = $value;
                }
            }
            unset($_SESSION['glpi_plugin_purchaserequests_fields'][$session_id]);
        }

        $JS = '';

        // Name (editable input or read-only plain value handled in template)
        $name_field = $canedit
            ? Html::input('name', ['value' => $this->fields['name'], 'size' => 40])
            : null;

        // Requester + requester group
        if ($canedit) {
            ob_start();
            $rand_user = User::dropdown([
                'name'      => "users_id",
                'value'     => $this->fields["users_id"],
                'entity'    => $this->fields["entities_id"],
                'on_change' => "PurchaserequestLoadGroups();",
                'right'     => 'all',
            ]);
            $requester_field = ob_get_clean();

            ob_start();
            if ($this->fields['users_id']) {
                PurchaseRequest::displayGroup($this->fields['users_id']);
            }
            $group_field = ob_get_clean();

            // JS updating the requester group dropdown, kept in PHP (rendered after the template)
            $JS     = "function PurchaserequestLoadGroups(){";
            $params = ['users_id' => '__VALUE__',
                'entity'   => $this->fields["entities_id"]];
            $JS     .= Ajax::updateItemJsCode(
                "plugin_purchaserequest_group",
                PLUGIN_PURCHASEREQUEST_WEBDIR . "/ajax/dropdownGroup.php",
                $params,
                'dropdown_users_id' . $rand_user,
                false
            );
            $JS     .= "}";
        } else {
            $requester_field = Dropdown::getDropdownName($dbu->getTableForItemType('User'), $this->fields["users_id"]);
            $group_field     = Dropdown::getDropdownName($dbu->getTableForItemType('Group'), $this->fields["groups_id"]);
        }

        // Location
        ob_start();
        Dropdown::show('Location', ['value'  => $this->fields["locations_id"],
            'entity' => $this->fields["entities_id"]]);
        $location_field = ob_get_clean();

        // Status
        ob_start();
        Dropdown::show(
            PurchaseRequestState::class,
            ['value'  => $this->fields["plugin_purchaserequest_purchaserequeststates_id"],
                'entity' => $this->fields["entities_id"]]
        );
        $state_field = ob_get_clean();

        // Description
        ob_start();
        Html::textarea(['name'            => 'comment',
            'value'           => stripslashes($this->fields['comment']),
            'enable_richtext' => false,
            'cols'            => '100',
            'rows'            => '4']);
        $comment_field = ob_get_clean();

        // Item type
        $reference = new PluginOrderReference();
        ob_start();
        $reference->dropdownAllItems([
            'myname'    => 'itemtype',
            'value'     => $this->fields["itemtype"],
            'entity'    => $_SESSION["glpiactive_entity"],
            'ajax_page' => $CFG_GLPI['root_doc'] . '/plugins/order/ajax/referencespecifications.php',
            'class'     => __CLASS__,
        ]);
        $itemtype_field = ob_get_clean();

        // Type
        ob_start();
        if ($this->fields['itemtype']) {
            if ($this->fields['itemtype'] == 'PluginOrderOther') {
                $file = 'other';
            } else {
                $file = $this->fields['itemtype'];
            }
            $core_typefilename   = GLPI_ROOT . "/src/" . $file . "Type.php";
            $plugin_typefilename = $CFG_GLPI['root_doc'] . "/plugins/order/inc/" . strtolower($file) . "type.class.php";
            $itemtypeclass       = $this->fields['itemtype'] . "Type";

            if (file_exists($core_typefilename)
                || file_exists($plugin_typefilename)) {
                Dropdown::show(
                    $itemtypeclass,
                    [
                        'name'  => "types_id",
                        'value' => $this->fields["types_id"],
                    ]
                );
            }
        }
        $types_field = ob_get_clean();

        // Due date
        ob_start();
        Html::showDateField("due_date", ['value' => $this->fields["due_date"]]);
        $due_date_field = ob_get_clean();

        // To be validated by
        ob_start();
        User::dropdown(['name'   => "users_id_validate",
            'value'  => $this->fields["users_id_validate"],
            'entity' => $this->fields["entities_id"],
            'right'  => 'plugin_purchaserequest_validate']);
        $validator_field = ob_get_clean();

        // Amount
        $amount_field = Html::input('amount', [
            'type'  => 'text',
            'value' => number_format($this->fields['amount'], 2, '.', ' '),
        ]);

        // To be rebilled to the customer
        ob_start();
        Html::showCheckbox(['name'    => "invoice_customer",
            'checked' => $this->fields["invoice_customer"],
        ]);
        $invoice_field = ob_get_clean();

        // Linked to the order
        ob_start();
        $order         = new PluginOrderOrder();
        $order_options = [];
        if ($order->getFromDB($this->fields['plugin_order_orders_id'])) {
            $order_options['value'] = $this->fields['plugin_order_orders_id'];
        }
        PluginOrderOrder::dropdown($order_options);
        $order_field = ob_get_clean();

        // Linked to ticket
        ob_start();
        $ticket         = new Ticket();
        $ticket_options = [];
        if ($ticket->getFromDB($this->fields['tickets_id'])) {
            $ticket_options['value'] = $this->fields['tickets_id'];
        }
        $ticket_options['entity'] = $this->fields["entities_id"];
        Ticket::dropdown($ticket_options);
        $ticket_field = ob_get_clean();

        // Treated
        $is_process_field = null;
        $processing_date  = null;
        if ($ID > 0) {
            if ($this->fields['processing_date'] == null) {
                ob_start();
                Html::showCheckbox(['name' => 'is_process']);
                $is_process_field = ob_get_clean();
            } else {
                $processing_date = Html::convDateTime($this->fields['processing_date']);
            }
        }

        TemplateRenderer::getInstance()->display('@purchaserequest/validation_form.html.twig', [
            'item'             => $this,
            'params'           => $options,
            'canedit'          => $canedit,
            'name_field'       => $name_field,
            'name_value'       => $this->fields['name'],
            'requester_field'  => $requester_field,
            'group_field'      => $group_field,
            'location_field'   => $location_field,
            'state_field'      => $state_field,
            'comment_field'    => $comment_field,
            'itemtype_field'   => $itemtype_field,
            'types_field'      => $types_field,
            'due_date_field'   => $due_date_field,
            'validator_field'  => $validator_field,
            'amount_field'     => $amount_field,
            'invoice_field'    => $invoice_field,
            'order_field'      => $order_field,
            'ticket_field'     => $ticket_field,
            'show_treated'     => ($ID > 0),
            'is_process_field' => $is_process_field,
            'processing_date'  => $processing_date,
            'users_id_creator' => $_SESSION['glpiID'],
        ]);

        // Inline JS kept in PHP (server-injected params), rendered after the template.
        if ($canedit && $JS !== '') {
            echo Html::scriptBlock($JS);
        }

        return true;
    }


    /**
     * Display list of purchase request linked to the order
     *
     * @param $item
     */
    public static function showForOrder($item)
    {
        $dbu = new DbUtils();

        $purchase_request = new PurchaseRequest();
        $data             = $purchase_request->find(['plugin_order_orders_id' => $item->fields['id']]);

        $rows    = count($data);
        $canread = Session::haveRight(self::$rightname, READ);

        if (!$rows || !$canread) {
            TemplateRenderer::getInstance()->display('@purchaserequest/validation_for_order.html.twig', [
                'no_results' => true,
            ]);
            return;
        }

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $rand    = mt_rand();

        $entries = [];
        foreach ($data as $field) {
            // Name
            $pr = new PurchaseRequest();
            $pr->getFromDB($field['id']);

            // Item type
            $orderItem = getItemForItemtype($field["itemtype"] ?? '');
            // Model name (type)
            $itemtypeclass = $field['itemtype'] . "Type";
            // Link with order
            $order = new PluginOrderOrder();
            $order->getFromDB($field['plugin_order_orders_id']);

            $entries[] = [
                'id'         => $field['id'],
                'name'       => $pr->getLink(),
                'requester'  => $dbu->getUserName($field['users_id']),
                'group'      => Dropdown::getDropdownName('glpi_groups', $field['groups_id']),
                'location'   => Dropdown::getDropdownName('glpi_locations', $field['locations_id']),
                'state'      => Dropdown::getDropdownName(
                    'glpi_plugin_purchaserequest_purchaserequeststates',
                    $field['plugin_purchaserequest_purchaserequeststates_id']
                ),
                'itemtype'   => $orderItem !== false ? $orderItem->getTypeName() : '',
                'type'       => Dropdown::getDropdownName($dbu->getTableForItemType($itemtypeclass), $field["types_id"]),
                'due_date'   => Html::convDate($field['due_date']),
                'processing' => Html::convDate($field['processing_date']),
                'validator'  => $dbu->getUserName($field['users_id_validate']),
                'status'     => CommonITILValidation::getStatus($field['status']),
                'order'      => $order->getLink(),
            ];
        }

        // Capture the mass action dropdown ("Delete link with order")
        ob_start();
        $purchase_request->dropdownPurchaseRequestItemsActions();
        $action_dropdown = ob_get_clean();

        TemplateRenderer::getInstance()->display('@purchaserequest/validation_for_order.html.twig', [
            'no_results'      => false,
            'canedit'         => $canedit,
            'rand'            => $rand,
            'target'          => Toolbox::getItemTypeFormURL(PurchaseRequest::class),
            'entries'         => $entries,
            'order_id'        => $item->getID(),
            'order_type_name' => PluginOrderOrder::getTypeName(),
            'action_dropdown' => $action_dropdown,
            'select_all'      => (isset($_GET["select"]) && $_GET["select"] == "all"),
        ]);
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
    public static function showValidation($item)
    {
        $validation = new self();

        $show_actions  = false;
        $validation_id = 0;
        $comment_field = '';

        if ($validation->getFromDBByCrit(["status" => CommonITILValidation::WAITING,
            "users_id_validate" => Session::getLoginUserID(),
            "plugin_purchaserequest_purchaserequests_id" => $item->getID()])) {
            $show_actions  = true;
            $validation_id = $validation->fields['id'];

            ob_start();
            Html::textarea(['name'            => 'comment_validation',
                'enable_richtext' => false,
                'cols'            => '90',
                'rows'            => '3']);
            $comment_field = ob_get_clean();
        }

        TemplateRenderer::getInstance()->display('@purchaserequest/validation_showvalidation.html.twig', [
            'target'            => Toolbox::getItemTypeFormURL(Validation::class),
            'show_actions'      => $show_actions,
            'validation_id'     => $validation_id,
            'users_id_validate' => Session::getLoginUserID(),
            'pr_id'             => $item->fields['id'],
            'comment_field'     => $comment_field,
        ]);

        // Accept/refuse handlers kept in PHP (rendered after the template).
        if ($show_actions) {
            echo Html::scriptBlock('$( "#accept_purchaserequest" ).click(function() {
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'accept_purchaserequest\' value=\'1\' />");
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'update_status\' value=\'1\' />");
                                $( "#formvalidation" ).submit();
                              });
                              $( "#refuse_purchaserequest" ).click(function() {
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'refuse_purchaserequest\' value=\'1\' />");
                                $( "#formvalidation" ).append("<input type=\'hidden\' name=\'update_status\' value=\'1\' />");
                                $( "#formvalidation" ).submit();
                              });');
        }

        $self = new self();
        $self->showSummary($item);
    }


    /**
     * Print the validation list into item
     *
     * @param CommonDBTM $item
     **/
    public function showSummary(CommonDBTM $item)
    {
        global $DB;

        $tID  = $item->fields['id'];
        $rand = mt_rand();

        $iterator = $DB->request([
            'FROM'  => $this->getTable(),
            'WHERE' => ["plugin_purchaserequest_purchaserequests_id" => $item->getField('id')],
            'ORDER' => 'submission_date DESC',
        ]);

        $columns = [_x('item', 'State'),
            __('Request date'),
            __('Approval requester'),
            __('Approval status'),
            __('Approver'),
            __('Approval comments')];

        $entries = [];
        if (count($iterator)) {
            Session::initNavigateListItems(
                $this->getType(),
                //TRANS : %1$s is the itemtype name, %2$s is the name of the item (used for headings of a list)
                sprintf(
                    __('%1$s = %2$s'),
                    $item->getTypeName(1),
                    $item->fields["name"]
                )
            );

            foreach ($iterator as $row) {
                Session::addToNavigateListItems($this->getType(), $row["id"]);

                $entries[] = [
                    'bgcolor'         => CommonITILValidation::getStatusColor($row['status']),
                    'status'          => CommonITILValidation::getStatus($row['status']),
                    'submission_date' => Html::convDateTime($row["submission_date"]),
                    'requester'       => getUserName($row["users_id"]),
                    'validation_date' => Html::convDateTime($row["validation_date"]),
                    'validator'       => getUserName($row["users_id_validate"]),
                    'comment'         => $row["comment_validation"],
                ];
            }
        }

        TemplateRenderer::getInstance()->display('@purchaserequest/validation_summary.html.twig', [
            'tID'     => $tID,
            'rand'    => $rand,
            'title'   => __('Approvals for the purchase request', 'purchaserequest'),
            'columns' => $columns,
            'entries' => $entries,
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
            $query = "CREATE TABLE IF NOT EXISTS `glpi_plugin_purchaserequest_validations` (
                    `id` int unsigned NOT NULL AUTO_INCREMENT,
                    `entities_id` int unsigned NOT NULL DEFAULT '0',
                    `users_id` int unsigned NOT NULL DEFAULT '0',
                    `plugin_purchaserequest_purchaserequests_id` int unsigned NOT NULL DEFAULT '0',
                    `users_id_validate` int unsigned NOT NULL DEFAULT '0',
                    `status` int unsigned NOT NULL DEFAULT '0',
                    `comment_validation` TEXT COLLATE utf8mb4_unicode_ci,
                    `submission_date` timestamp NULL DEFAULT NULL,
                    `validation_date` timestamp NULL DEFAULT NULL,
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
}
