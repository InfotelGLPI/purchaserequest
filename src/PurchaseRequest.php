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

use Ajax;
use CommonDBTM;
use CommonGLPI;
use CommonITILActor;
use CommonITILValidation;
use DbUtils;
use Dropdown;
use Glpi\Application\View\TemplateRenderer;
use Glpi\RichText\RichText;
use Group;
use Group_User;
use Html;
use Location;
use Log;
use MassiveAction;
use Migration;
use Plugin;
use PluginOrderOrder;
use PluginOrderOrder_Item;
use PluginOrderReference;
use Session;
use Ticket;
use Ticket_User;
use TicketValidation;
use Toolbox;
use User;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}

/**
 * Class PurchaseRequest
 */
class PurchaseRequest extends CommonDBTM
{
    public static $rightname = 'plugin_purchaserequest_purchaserequest';
    public $dohistory = true;

    /**
     * Set in prepareInputForUpdate() when a financially engaging field changes on an
     * already-decided request; consumed in post_updateItem() to reopen approval.
     */
    private bool $requeuePendingValidation = false;

    public const HISTORY_ADDLINK = 50;
    public const HISTORY_DELLINK = 51;

    /**
     * @param int $nb
     *
     * @return string|\translated
     */
    public static function getTypeName($nb = 0)
    {
        return _n("Purchase request", "Purchase requests", $nb, "purchaserequest");
    }

    public static function getIcon()
    {
        return "ti ti-basket";
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
        $this->addStandardTab(__CLASS__, $ong, $options);
        $this->addStandardTab('Document_Item', $ong, $options);
        $this->addStandardTab('Log', $ong, $options);

        return $ong;
    }

    /**
     * @param CommonGLPI $item
     * @param int $withtemplate
     *
     * @return string|\translated
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() == PurchaseRequest::class) {
            return self::createTabEntry(__('Approval'));
        } elseif ($item->getType() == "Ticket" && Session::getCurrentInterface() == 'central') {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(2), self::countForTicket($item));
            }
            return self::createTabEntry(self::getTypeName());
        } elseif ($item->getType() == "PluginOrderOrder"
            && Session::haveRight(self::$rightname, READ)) {
            if ($_SESSION['glpishow_count_on_tabs']) {
                return self::createTabEntry(self::getTypeName(2), self::countForPluginOrderOrder($item));
            }
            return self::createTabEntry(self::getTypeName());
        }

        return '';
    }

    public static function countForTicket(Ticket $item)
    {
        $dbu = new DbUtils();
        $restrict = ["tickets_id" => $item->getField('id')];
        $nb = $dbu->countElementsInTable(['glpi_plugin_purchaserequest_purchaserequests'], $restrict);

        return $nb;
    }

    public static function countForPluginOrderOrder(PluginOrderOrder $item)
    {
        $dbu = new DbUtils();
        $restrict = ["plugin_order_orders_id" => $item->getField('id')];
        $nb = $dbu->countElementsInTable(['glpi_plugin_purchaserequest_purchaserequests'], $restrict);

        return $nb;
    }

    /**
     * @param CommonGLPI $item
     * @param int $tabnum
     * @param int $withtemplate
     *
     * @return bool
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        //        if (!Plugin::isPluginActive('order')) {
        //            echo "<div class='alert  alert-warning d-flex'>";
        //            echo "<b>" . __('Please activate the plugin order', 'purchaserequest') . "</b></div>";
        //            return false;
        //        }
        if ($item->getType() == PurchaseRequest::class) {
            Validation::showValidation($item);
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

        // Harden against mass assignment: system/structural columns must never be
        // taken from client input. The creator is forced to the current user
        // server-side (the hidden users_id_creator form field is spoofable), and
        // soft-delete/recursion/date columns are managed by GLPI core, not the form.
        unset(
            $input['is_deleted'],
            $input['is_recursive'],
            $input['date_creation'],
            $input['date_mod'],
        );
        $input['users_id_creator'] = Session::getLoginUserID();

        $input['status'] = CommonITILValidation::WAITING;

        // Constrain itemtype to the whitelist enforced at display time
        // (Threshold::$list_type_allowed / hook.php's giveItem()) so a forged
        // POST cannot store an arbitrary itemtype that showForm() later feeds
        // into a file-path / class resolution.
        if (!empty($input['itemtype']) && !in_array($input['itemtype'] . 'Type', Threshold::$list_type_allowed, true)) {
            Session::addMessageAfterRedirect(
                __('Invalid item type.', 'purchaserequest'),
                false,
                ERROR,
            );
            return false;
        }

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
        // or set once at creation and must never be mutated through a generic
        // update. Soft-delete/restore go through delete()/restore(), and the
        // creator is immutable after creation.
        unset(
            $input['is_deleted'],
            $input['is_recursive'],
            $input['users_id_creator'],
            $input['date_creation'],
            $input['date_mod'],
        );

        // Constrain itemtype to the whitelist enforced at display time
        // (Threshold::$list_type_allowed / hook.php's giveItem()) so a forged
        // POST cannot store an arbitrary itemtype that showForm() later feeds
        // into a file-path / class resolution.
        if (isset($input['itemtype']) && $input['itemtype'] !== '' && !in_array($input['itemtype'] . 'Type', Threshold::$list_type_allowed, true)) {
            Session::addMessageAfterRedirect(
                __('Invalid item type.', 'purchaserequest'),
                false,
                ERROR,
            );
            return false;
        }

        // Server-side enforcement of the approval workflow: only the designated
        // validator (users_id_validate) may accept/refuse or change the status.
        // The generic UPDATE right is not enough — without this gate any user who
        // can edit the request could self-approve it by POSTing the accept/refuse/
        // update_status flags (the validator check in showValidation() is UI-only).
        $is_validation_action = isset($input['refuse_purchaserequest'])
            || isset($input['accept_purchaserequest'])
            || isset($input['update_status']);
        $is_validator = (int) ($this->fields['users_id_validate'] ?? 0) === (int) Session::getLoginUserID();
        if ($is_validation_action && !$is_validator) {
            unset(
                $input['refuse_purchaserequest'],
                $input['accept_purchaserequest'],
                $input['update_status'],
                $input['status'],
            );
            Session::addMessageAfterRedirect(
                __('You are not allowed to approve or refuse this purchase request.', 'purchaserequest'),
                false,
                ERROR,
            );
        }

        if (isset($input['refuse_purchaserequest']) && $input['refuse_purchaserequest'] == 1) {
            $input['status'] = CommonITILValidation::REFUSED;
        }

        if (isset($input['accept_purchaserequest']) && $input['accept_purchaserequest'] == 1) {
            $input['status'] = CommonITILValidation::ACCEPTED;
        }

        if (isset($input['update_status'])) {
            //         if ($CFG_GLPI["notifications_mailing"]) {
            //            $purchase_request = new PurchaseRequest();
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

        // Re-open the approval cycle when a financially engaging field changes on an
        // already-decided request: editing the amount (or item type / customer
        // re-invoicing) after acceptance must not keep the old approval. Force the
        // status back to WAITING and requeue pending validations (see post_updateItem),
        // so the validator is re-solicited before the request can be linked to an order.
        if (!$is_validation_action
            && in_array(
                (int) ($this->fields['status'] ?? 0),
                [CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED],
                true,
            )
        ) {
            $engaging_changed = false;
            foreach (['amount', 'itemtype', 'types_id', 'invoice_customer'] as $field) {
                if (array_key_exists($field, $input)
                    && array_key_exists($field, $this->fields)
                    && (string) $input[$field] !== (string) $this->fields[$field]) {
                    $engaging_changed = true;
                    break;
                }
            }
            if ($engaging_changed) {
                $input['status'] = CommonITILValidation::WAITING;
                $this->requeuePendingValidation = true;
            }
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

        // Convert images pasted in the rich-text description into GLPI documents
        // so the stored "comment" keeps a small tag instead of an inline base64
        // blob (which overflows the column and makes MySQL reject the write).
        $this->input = $this->addFiles($this->input, [
            'force_update'  => true,
            'name'          => 'comment',
            'content_field' => 'comment',
        ]);

        $list = Threshold::$list_type_allowed;

        //      if ($CFG_GLPI["notifications_mailing"]) {
        //         NotificationEvent::raiseEvent('ask_purchaserequest', $this);
        //      }

        if (isset($this->input["users_id_validate"])) {
            $validation = new Validation();
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
        $threshold = new Threshold();
        $itemtype = Threshold::getObject($this->fields["itemtype"]);
        if ($threshold->getFromDBByCrit([
            "itemtype" => $itemtype,
            "items_id" => $this->fields["types_id"],
        ])) {
            $th = intval($threshold->fields["thresholds"]);
            if ($th != -1) {
                $config = new Config();
                $config->getFromDB(1);

                if ($th < intval($this->fields["amount"])
                    && $config->fields["id_general_service_manager"] > 0) {
                    $validation = new Validation();
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
                Log::HISTORY_PLUGIN + self::HISTORY_ADDLINK,
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
        // Convert images pasted in the rich-text description into GLPI documents
        // so the stored "comment" keeps a small tag instead of an inline base64
        // blob (which overflows the column and makes MySQL reject the write).
        $this->input = $this->addFiles($this->input, [
            'force_update'  => true,
            'name'          => 'comment',
            'content_field' => 'comment',
        ]);

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
                    Log::HISTORY_PLUGIN + self::HISTORY_DELLINK,
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
                        Log::HISTORY_PLUGIN + self::HISTORY_ADDLINK,
                    );
                }
            }
        }

        if (isset($this->oldvalues['users_id_validate'])) {
            $validation = new Validation();
            $validation->deleteByCriteria(
                [
                    "users_id_validate" => $this->oldvalues['users_id_validate'],
                    "plugin_purchaserequest_purchaserequests_id" => $this->fields["id"],
                ],
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

        // A financially engaging field changed on an already-decided request
        // (see prepareInputForUpdate): drop the stale decision and re-solicit the
        // approvers so the new amount/type is validated before any order is placed.
        if ($this->requeuePendingValidation) {
            $this->requeuePendingValidation = false;
            $this->requeuePendingValidations();
        }
    }

    /**
     * Rebuild the pending approval chain from scratch after an engaging change.
     *
     * Deletes every existing validation row of the request, then recreates the
     * primary WAITING validation (for users_id_validate) and, when the amount
     * exceeds the configured threshold, the second-level service-manager
     * validation — mirroring post_addItem().
     *
     * @return void
     */
    private function requeuePendingValidations(): void
    {
        $validation = new Validation();
        $validation->deleteByCriteria([
            "plugin_purchaserequest_purchaserequests_id" => $this->fields["id"],
        ]);

        if (!empty($this->fields["users_id_validate"])) {
            $validation = new Validation();
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

        $threshold = new Threshold();
        $itemtype = Threshold::getObject($this->fields["itemtype"]);
        if ($threshold->getFromDBByCrit([
            "itemtype" => $itemtype,
            "items_id" => $this->fields["types_id"],
        ])) {
            $th = intval($threshold->fields["thresholds"]);
            if ($th != -1) {
                $config = new Config();
                $config->getFromDB(1);

                if ($th < intval($this->fields["amount"])
                    && $config->fields["id_general_service_manager"] > 0) {
                    $validation = new Validation();
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
    }


    /**
     * @param $input
     *
     * @return bool
     */
    public function checkMandatoryFields($input)
    {
        $msg = [];
        $checkKo = false;

        $mandatory_fields = [
            'users_id' => __('Requester'),
            'comment' => __('Description'),
            'itemtype' => __('Item type'),
            'types_id' => __('Type'),
            'amount' => __("Amount", "purchaserequest"),
            'users_id_validate' => __('To be validated by', 'purchaserequest'),
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
        }

        if ($checkKo) {
            // Only save to session on validation failure, so the form can
            // repopulate what the user typed. Keyed by item id to prevent
            // cross-tab contamination when two purchase requests are open.
            $item_id = (int) ($input['id'] ?? 0);
            foreach ($input as $key => $value) {
                $_SESSION['glpi_plugin_purchaserequests_fields'][$item_id][$key] = $value;
            }
            Session::addMessageAfterRedirect(
                sprintf(__("Mandatory fields are not filled. Please correct: %s"), implode(', ', $msg)),
                false,
                ERROR,
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
            'name' => self::getTypeName(),
        ];

        $tab[] = [
            'id' => '1',
            'table' => $this->getTable(),
            'field' => 'name',
            'name' => __('Name'),
            'datatype' => 'itemlink',
            'itemlink_type' => $this->getType(),
        ];

        $tab[] = [
            'id' => 2,
            'table' => getTableForItemType('User'),
            'field' => 'name',
            'name' => __("Requester"),
            'linkfield' => 'users_id',
            'datatype' => 'dropdown',
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
            'injectable' => true,
        ];

        $tab[] = [
            'id' => 5,
            'table' => getTableForItemType('User'),
            'field' => 'name',
            'linkfield' => 'users_id_validate',
            'name' => __("Approver"),
            'datatype' => 'dropdown',
            'right' => 'plugin_purchaserequest_validate',
        ];

        $tab[] = [
            'id' => 6,
            'table' => $this->getTable(),
            'field' => 'due_date',
            'massiveaction' => false,
            'name' => __("Due date", "purchaserequest"),
            'datatype' => 'datetime',
        ];

        $tab[] = [
            'id' => 7,
            'table' => $this->getTable(),
            'field' => 'types_id',
            'name' => __("Type"),
            'massiveaction' => false,
            'checktype' => 'text',
            'searchtype' => ['equals'],
            'nosearch' => true,
        ];

        $tab[] = [
            'id' => 8,
            'table' => $this->getTable(),
            'field' => 'status',
            'name' => __('Approval status'),
            'searchtype' => 'equals',
            'datatype' => 'specific',
        ];

        $tab[] = [
            'id' => 9,
            'table' => $this->getTable(),
            'field' => 'plugin_order_orders_id',
            'datatype' => 'itemlink',
            'massiveaction' => false,
            'name' => PluginOrderOrder::getTypeName(),
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
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => 12,
            'table' => getTableForItemType(PurchaseRequestState::class),
            'field' => 'name',
            'name' => __("Status"),
            'linkfield' => 'plugin_purchaserequest_purchaserequeststates_id',
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id' => 13,
            'table' => $this->getTable(),
            'field' => 'date_mod',
            'name' => __('Last update'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => 14,
            'table' => $this->getTable(),
            'field' => 'date_creation',
            'name' => __('Creation date'),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id' => 15,
            'table' => $this->getTable(),
            'field' => 'processing_date',
            'name' => __('Treated on', "purchaserequest"),
            'datatype' => 'datetime',
            'massiveaction' => false,
        ];

        /* comments */
        $tab[] = [
            'id' => 16,
            'table' => $this->getTable(),
            'field' => 'comment',
            'name' => __("Description"),
            'datatype' => 'text',
        ];
        /* amount */
        $tab[] = [
            'id' => 17,
            'table' => $this->getTable(),
            'field' => 'amount',
            'name' => __("Amount", "purchaserequest"),
            'datatype' => 'decimal',
        ];

        /* rebill */
        $tab[] = [
            'id' => 18,
            'table' => $this->getTable(),
            'field' => 'invoice_customer',
            'name' => __("To be rebilled to the customer", "purchaserequest"),
            'datatype' => 'bool',
        ];
        /* ID */
        $tab[] = [
            'id' => 30,
            'table' => $this->getTable(),
            'field' => 'id',
            'name' => __("ID"),
            'datatype' => 'number',
        ];

        /* entity */
        $tab[] = [
            'id' => 80,
            'table' => 'glpi_entities',
            'field' => 'completename',
            'name' => __("Entity"),
            'datatype' => 'dropdown',
        ];

        /* entity */
        $tab[] = [
            'id' => 86,
            'table' => $this->getTable(),
            'field' => 'is_recursive',
            'name' => __("Child entities"),
            'datatype' => 'bool',
            'massiveaction' => false,
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
                    'table' => Validation::getTable(),
                    'joinparams' => [
                        'jointype' => 'child',
                    ],
                ],
            ],
        ];

        return $tab;
    }

    /**
     * @param $field
     * @param $values
     * @param $options   array
     **/
    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'status':
                return CommonITILValidation::getStatus($values[$field]);
            case 'itemtype':
                $item = getItemForItemtype($values['itemtype']);
                return $item !== false ? $item->getTypeName() : '';
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
    public static function getSpecificValueToSelect($field, $name = '', $values = '', array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }
        $options['display'] = false;

        switch ($field) {
            case 'status':
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

        $canedit = $this->can($ID, UPDATE);
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

        // Inline JS is kept in PHP (no <script> in templates); echoed after render.
        $JS = '';

        // Name (editable input or read-only escaped text)
        if ($canedit) {
            $name_field = Html::input('name', ['value' => $this->fields['name'], 'size' => 40]);
        } else {
            $name_field = htmlescape($this->fields["name"]);
        }

        // Requester + requester group (with on-change AJAX group reload)
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
                self::displayGroup($this->fields['users_id']);
            }
            $group_field = '<div id="plugin_purchaserequest_group">' . ob_get_clean() . '</div>';

            $params = [
                'users_id' => '__VALUE__',
                'entity'   => $this->fields["entities_id"],
            ];
            $JS  = "function PurchaserequestLoadGroups(){";
            $JS .= Ajax::updateItemJsCode(
                "plugin_purchaserequest_group",
                PLUGIN_PURCHASEREQUEST_WEBDIR . "/ajax/dropdownGroup.php",
                $params,
                'dropdown_users_id' . $rand_user,
                false,
            );
            $JS .= "}";
        } else {
            $requester_field = htmlescape(
                Dropdown::getDropdownName($dbu->getTableForItemType('User'), $this->fields["users_id"]),
            );
            $group_field = htmlescape(
                Dropdown::getDropdownName($dbu->getTableForItemType('Group'), $this->fields["groups_id"]),
            );
        }

        // Location
        ob_start();
        Dropdown::show('Location', [
            'value'  => $this->fields["locations_id"],
            'entity' => $this->fields["entities_id"],
        ]);
        $location_field = ob_get_clean();

        // Status (purchase request state)
        ob_start();
        Dropdown::show(
            PurchaseRequestState::class,
            [
                'value'  => $this->fields["plugin_purchaserequest_purchaserequeststates_id"],
                'entity' => $this->fields["entities_id"],
            ],
        );
        $state_field = ob_get_clean();

        // Description (richtext)
        ob_start();
        Html::textarea([
            "id"              => "comment",
            "name"            => "comment",
            "row"             => 4,
            "cols"            => 100,
            "enable_richtext" => true,
            "value"           => $this->fields['comment'],
        ]);
        $comment_field = ob_get_clean();

        // Item type (order reference selector in central, plain type name otherwise)
        if (Session::getCurrentInterface() == 'central') {
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
        } else {
            $item = getItemForItemtype($this->fields["itemtype"] ?? '');
            $itemtype_field = ($item !== false) ? htmlescape($item->getTypeName()) : '';
        }

        // Type (model), refreshed by the item type selector into #show_types_id
        ob_start();
        if ($this->fields['itemtype']) {
            $itemtypeclass = $this->fields['itemtype'] . "Type";

            // Resolve the *Type class through class_exists() instead of building a
            // filesystem path from the stored itemtype and testing it with
            // file_exists(): itemtype is user-controlled at write time, and
            // concatenating it into a path handed to file_exists() would turn a
            // forged value into a file-existence oracle (mirrors the hardening
            // already applied in hook.php::plugin_purchaserequest_giveItem()).
            if (class_exists($itemtypeclass)) {
                Dropdown::show(
                    $itemtypeclass,
                    [
                        'name'  => "types_id",
                        'value' => $this->fields["types_id"],
                    ],
                );
            }
        }
        $types_field = '<span id="show_types_id">' . ob_get_clean() . '</span>';

        // Due date
        ob_start();
        Html::showDateField("due_date", ['value' => $this->fields["due_date"]]);
        $due_date_field = ob_get_clean();

        // To be validated by
        ob_start();
        User::dropdown([
            'name'   => "users_id_validate",
            'value'  => $this->fields["users_id_validate"],
            'entity' => $this->fields["entities_id"],
            'right'  => 'plugin_purchaserequest_validate',
        ]);
        $validator_field = ob_get_clean();

        // Amount
        $amount       = $this->fields['amount'] ?? number_format($this->fields['amount'], 2, '.', ' ');
        $amount_field = Html::input('amount', ['type' => 'text', 'value' => $amount]);

        // To be rebilled to the customer
        ob_start();
        Html::showCheckbox([
            'name'    => "invoice_customer",
            'checked' => $this->fields["invoice_customer"],
        ]);
        $invoice_field = ob_get_clean();

        // Linked to the order (only shown when the request is accepted)
        $order             = new PluginOrderOrder();
        $order_link_hidden = ($this->fields["status"] != CommonITILValidation::ACCEPTED);
        $order_options     = [];
        if ($order->getFromDB($this->fields['plugin_order_orders_id'])) {
            $order_options['value'] = $this->fields['plugin_order_orders_id'];
        }
        ob_start();
        PluginOrderOrder::dropdown($order_options);
        $order_field = ob_get_clean();

        // Linked to ticket
        $ticket         = new Ticket();
        $ticket_options = [];
        if ($ticket->getFromDB($this->fields['tickets_id'])) {
            $ticket_options['value'] = $this->fields['tickets_id'];
        }
        $ticket_options['entity'] = $this->fields["entities_id"];
        ob_start();
        Ticket::dropdown($ticket_options);
        $ticket_field = ob_get_clean();

        // Treated flag / date (existing items only)
        $show_treated  = false;
        $treated_field = '';
        $treated_label = '';
        if ($ID > 0) {
            $show_treated = true;
            if ($this->fields['processing_date'] == null) {
                $treated_label = __("Treated", "purchaserequest");
                ob_start();
                Html::showCheckbox(['name' => 'is_process']);
                $treated_field = ob_get_clean();
            } else {
                $treated_label = __("Treated on", "purchaserequest");
                $treated_field = htmlescape(Html::convDateTime($this->fields['processing_date']));
            }
        }

        TemplateRenderer::getInstance()->display('@purchaserequest/pr_form.html.twig', [
            'item'              => $this,
            'params'            => $options,
            'canedit'           => $canedit,
            'name_field'        => $name_field,
            'requester_field'   => $requester_field,
            'group_field'       => $group_field,
            'location_field'    => $location_field,
            'state_field'       => $state_field,
            'comment_field'     => $comment_field,
            'itemtype_field'    => $itemtype_field,
            'types_field'       => $types_field,
            'due_date_field'    => $due_date_field,
            'validator_field'   => $validator_field,
            'amount_field'      => $amount_field,
            'invoice_field'     => $invoice_field,
            'order_field'       => $order_field,
            'order_link_hidden' => $order_link_hidden,
            'ticket_field'      => $ticket_field,
            'show_treated'      => $show_treated,
            'treated_field'     => $treated_field,
            'treated_label'     => $treated_label,
            'users_id_creator'  => (int) ($_SESSION['glpiID'] ?? 0),
        ]);

        // Inline JS kept out of the template (no <script> in Twig).
        if ($JS !== '') {
            echo Html::scriptBlock($JS);
        }

        return true;
    }


    /**
     * @param $item
     */
    public static function showForTicket($item)
    {
        $purchaserequest = new self();

        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $start = (int) ($_REQUEST["start"] ?? 0);

        $datas = $purchaserequest->getItems($item->fields['id'], ['start' => $start, 'addLimit' => true]);
        $rows = count($purchaserequest->getItems($item->fields['id'], ['addLimit' => false]));

        //form
        if ($canedit) {
            $purchaserequest = new PurchaseRequest();
            $purchaserequest->showFormPurchase($item->fields['id']);
        }

        //Purchase request linked to the ticket
        if (Plugin::isPluginActive('order')) {
            // The datatable component renders its own "No results found" state
            // when there is nothing to display.
            $purchaserequest->listItems($datas, $canedit, $start, $rows);
        }
    }

    /**
     * @param $tickets_id
     */
    public static function showFormPurchase($tickets_id)
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
            ['tickets_id' => (int) $tickets_id, 'type' => CommonITILActor::REQUESTER],
        )) {
            $purchaserequest->fields['users_id'] = $actor->fields['users_id'];
        }

        // Name
        $name_field = Html::input('name', ['value' => $purchaserequest->fields['name'], 'size' => 40]);

        // Ticket validators (accepted validations), read-only text
        $ticket_validation  = new TicketValidation();
        $ticket_validations = $ticket_validation->find([
            'tickets_id' => $tickets_id,
            'status'     => CommonITILValidation::ACCEPTED,
        ]);
        $users_validations = [];
        foreach ($ticket_validations as $validation) {
            $users_validations[] = $dbu->getUserName($validation['users_id_validate']);
        }
        $validated_by = implode(', ', $users_validations);

        // Requester + requester group (with on-change AJAX group reload)
        ob_start();
        $rand_user = User::dropdown([
            'name'      => "users_id",
            'value'     => $purchaserequest->fields["users_id"],
            'entity'    => $purchaserequest->fields["entities_id"],
            'on_change' => "PurchaserequestLoadGroups();",
            'right'     => 'all',
        ]);
        $requester_field = ob_get_clean();

        ob_start();
        if ($purchaserequest->fields['users_id']) {
            self::displayGroup($purchaserequest->fields['users_id']);
        }
        $group_field = '<div id="plugin_purchaserequest_group">' . ob_get_clean() . '</div>';

        $params = [
            'users_id' => '__VALUE__',
            'entity'   => $purchaserequest->fields["entities_id"],
        ];
        $JS  = "function PurchaserequestLoadGroups(){";
        $JS .= Ajax::updateItemJsCode(
            "plugin_purchaserequest_group",
            PLUGIN_PURCHASEREQUEST_WEBDIR . "/ajax/dropdownGroup.php",
            $params,
            'dropdown_users_id' . $rand_user,
            false,
        );
        $JS .= "}";

        // Location
        ob_start();
        Dropdown::show('Location', [
            'value'  => $purchaserequest->fields["locations_id"],
            'entity' => $purchaserequest->fields["entities_id"],
        ]);
        $location_field = ob_get_clean();

        // Status (purchase request state)
        ob_start();
        Dropdown::show(
            PurchaseRequestState::class,
            [
                'value'  => $purchaserequest->fields["plugin_purchaserequest_purchaserequeststates_id"],
                'entity' => $purchaserequest->fields["entities_id"],
            ],
        );
        $state_field = ob_get_clean();

        // Description (richtext)
        ob_start();
        Html::textarea([
            "id"              => "comment",
            "name"            => "comment",
            "row"             => 4,
            "cols"            => 100,
            "enable_richtext" => true,
            "value"           => $purchaserequest->fields['comment'],
        ]);
        $comment_field = ob_get_clean();

        // Item type (order reference selector)
        ob_start();
        if (Plugin::isPluginActive('order')) {
            $reference = new PluginOrderReference();
            $reference->dropdownAllItems([
                'myname'    => 'itemtype',
                'value'     => $purchaserequest->fields["itemtype"],
                'entity'    => $_SESSION["glpiactive_entity"],
                'ajax_page' => $CFG_GLPI['root_doc'] . '/plugins/order/ajax/referencespecifications.php',
                'class'     => __CLASS__,
            ]);
        }
        $itemtype_field = ob_get_clean();

        // Type (model), refreshed by the item type selector into #show_types_id
        ob_start();
        if ($purchaserequest->fields['itemtype']) {
            $itemtypeclass = $purchaserequest->fields['itemtype'] . "Type";

            // Resolve the *Type class through class_exists() instead of building a
            // filesystem path from the stored itemtype and testing it with
            // file_exists(): itemtype is user-controlled at write time, and
            // concatenating it into a path handed to file_exists() would turn a
            // forged value into a file-existence oracle (mirrors the hardening
            // already applied in hook.php::plugin_purchaserequest_giveItem()).
            if (class_exists($itemtypeclass)) {
                Dropdown::show(
                    $itemtypeclass,
                    [
                        'name'  => "types_id",
                        'value' => $purchaserequest->fields["types_id"],
                    ],
                );
            }
        }
        $types_field = '<span id="show_types_id">' . ob_get_clean() . '</span>';

        // Due date
        ob_start();
        Html::showDateField("due_date", ['value' => $purchaserequest->fields["due_date"]]);
        $due_date_field = ob_get_clean();

        // To be validated by
        ob_start();
        User::dropdown([
            'name'   => "users_id_validate",
            'value'  => $purchaserequest->fields["users_id_validate"],
            'entity' => $purchaserequest->fields["entities_id"],
            'right'  => 'plugin_purchaserequest_validate',
        ]);
        $validator_field = ob_get_clean();

        // Amount
        $amount       = $purchaserequest->fields['amount'] ?? number_format($purchaserequest->fields['amount'], 2, '.', ' ');
        $amount_field = Html::input('amount', ['type' => 'text', 'value' => $amount]);

        // To be rebilled to the customer
        ob_start();
        Html::showCheckbox(['name' => "invoice_customer"]);
        $invoice_field = ob_get_clean();

        TemplateRenderer::getInstance()->display('@purchaserequest/pr_add.html.twig', [
            'target'           => Toolbox::getItemTypeFormURL(PurchaseRequest::class),
            'name_field'       => $name_field,
            'validated_by'     => $validated_by,
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
            'tickets_id'       => (int) $tickets_id,
            'entities_id'      => (int) $purchaserequest->fields['entities_id'],
            'users_id_creator' => (int) ($_SESSION['glpiID'] ?? 0),
        ]);

        // Inline JS kept out of the template (no <script> in Twig).
        echo Html::scriptBlock($JS);
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
        $dbu  = new DbUtils();

        $entries = [];
        foreach ($data as $field) {
            // Name
            $purchase_request = new PurchaseRequest();
            $purchase_request->getFromDB($field['id']);

            // item type
            $item = getItemForItemtype($field["itemtype"] ?? '');
            // Model name
            $itemtypeclass = $field['itemtype'] . "Type";
            // link with order
            $order = new PluginOrderOrder();
            $order->getFromDB($field['plugin_order_orders_id']);

            $entries[] = [
                'itemtype'         => self::class,
                'id'               => $field['id'],
                'name'             => $purchase_request->getLink(),
                'requester'        => $dbu->getUserName($field['users_id']),
                'group'            => Dropdown::getDropdownName('glpi_groups', $field['groups_id']),
                'location'         => Dropdown::getDropdownName('glpi_locations', $field['locations_id']),
                'state'            => Dropdown::getDropdownName(
                    'glpi_plugin_purchaserequest_purchaserequeststates',
                    $field['plugin_purchaserequest_purchaserequeststates_id'],
                ),
                'item_type'        => $item !== false ? $item->getTypeName() : '',
                'type'             => Dropdown::getDropdownName(
                    $dbu->getTableForItemType($itemtypeclass),
                    $field["types_id"],
                ),
                'due_date'         => Html::convDate($field['due_date']),
                'processing_date'  => Html::convDate($field['processing_date']),
                'amount'           => number_format($field['amount'], 2, '.', ' ') . " €",
                'invoice_customer' => Dropdown::getYesNo($field['invoice_customer']),
                'validator'        => $dbu->getUserName($field['users_id_validate']),
                'status'           => CommonITILValidation::getStatus($field['status']),
                'order'            => $order->getLink(),
            ];
        }

        TemplateRenderer::getInstance()->display('components/datatable.html.twig', [
            'is_tab'          => true,
            'nofilter'        => true,
            'nosort'          => true,
            'columns'         => [
                'name'             => __('Name'),
                'requester'        => __('Requester'),
                'group'            => __('Requester group'),
                'location'         => __('Location'),
                'state'            => __('Status'),
                'item_type'        => __('Item type'),
                'type'             => __('Type'),
                'due_date'         => __('Due date', 'purchaserequest'),
                'processing_date'  => __('Treated on', 'purchaserequest'),
                'amount'           => __('Amount', 'purchaserequest'),
                'invoice_customer' => __('To be rebilled to the customer', 'purchaserequest'),
                'validator'        => __('Approver'),
                'status'           => __('Approval status'),
                'order'            => PluginOrderOrder::getTypeName(),
            ],
            // Columns holding pre-rendered GLPI links must not be re-escaped;
            // every other column is auto-escaped by the component (XSS-safe).
            'formatters'      => [
                'name'  => 'raw_html',
                'order' => 'raw_html',
            ],
            'entries'         => $entries,
            'total_number'    => $rows,
            'filtered_number' => $rows,
            'start'           => $start,
            'limit'           => (int) $_SESSION['glpilist_limit'],
            'showmassiveactions' => $canedit,
            'massiveactionparams' => [
                'num_displayed' => count($entries),
                'container'     => 'mass' . str_replace('\\', '', self::class) . $rand,
            ],
        ]);
    }

    /**
     * @param int $tickets_id
     * @param array $options
     *
     * @return \all
     */
    public function getItems($tickets_id = 0, $options = [])
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

        $criteria = [
            'FROM'  => $this->getTable(),
            'WHERE' => [
                'is_deleted' => 0,
                'tickets_id' => (int) $tickets_id,
            ],
        ];

        if ($params['addLimit']) {
            $criteria['START'] = (int) $params['start'];
            $criteria['LIMIT'] = (int) $params['limit'];
        }

        foreach ($DB->request($criteria) as $data) {
            $output[$data['id']] = $data;
        }

        return $output;
    }

    /**
     * Display list of purchase request linked to the order
     *
     * @param $item
     */
    public static function showForOrder($item)
    {
        global $CFG_GLPI;

        $dbu   = new DbUtils();

        $purchase_request = new PurchaseRequest();
        $data = $purchase_request->find(['plugin_order_orders_id' => $item->fields['id']]);

        $rows    = count($data);
        $canread = Session::haveRight(self::$rightname, READ);
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $rand    = mt_rand();

        $checked = (isset($_GET["select"]) && $_GET["select"] == "all");

        $entries = [];
        if ($rows && $canread) {
            foreach ($data as $field) {
                // Name
                $purchase_request = new PurchaseRequest();
                $purchase_request->getFromDB($field['id']);
                // item type
                $orderItem = getItemForItemtype($field["itemtype"] ?? '');
                // Model name
                $itemtypeclass = $field['itemtype'] . "Type";
                // link with order
                $order = new PluginOrderOrder();
                $order->getFromDB($field['plugin_order_orders_id']);

                $entries[] = [
                    'id'              => $field['id'],
                    'checked'         => $checked,
                    'name'            => $purchase_request->getLink(),
                    'requester'       => $dbu->getUserName($field['users_id']),
                    'group'           => Dropdown::getDropdownName('glpi_groups', $field['groups_id']),
                    'location'        => Dropdown::getDropdownName('glpi_locations', $field['locations_id']),
                    'state'           => Dropdown::getDropdownName(
                        'glpi_plugin_purchaserequest_purchaserequeststates',
                        $field['plugin_purchaserequest_purchaserequeststates_id'],
                    ),
                    'item_type'       => $orderItem !== false ? $orderItem->getTypeName() : '',
                    'type'            => Dropdown::getDropdownName(
                        $dbu->getTableForItemType($itemtypeclass),
                        $field["types_id"],
                    ),
                    'due_date'        => Html::convDate($field['due_date']),
                    'processing_date' => Html::convDate($field['processing_date']),
                    'validator'       => $dbu->getUserName($field['users_id_validate']),
                    'status'          => CommonITILValidation::getStatus($field['status']),
                    'order'           => $order->getLink(),
                ];
            }
        }

        // Massive action selector (delete_link), captured as trusted GLPI HTML.
        ob_start();
        $purchase_request->dropdownPurchaseRequestItemsActions();
        $action_dropdown = ob_get_clean();

        TemplateRenderer::getInstance()->display('@purchaserequest/pr_order_list.html.twig', [
            'canread'                => $canread,
            'canedit'                => $canedit,
            'entries'                => $entries,
            'form_name'              => 'purchaseresquet_form' . $rand,
            'target'                 => Toolbox::getItemTypeFormURL(PurchaseRequest::class),
            'typename'               => PurchaseRequest::getTypeName(2),
            'order_typename'         => PluginOrderOrder::getTypeName(),
            'arrow_src'              => $CFG_GLPI["root_doc"] . "/pics/arrow-left.png",
            'action_dropdown'        => $action_dropdown,
            'plugin_order_orders_id' => $item->getID(),
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
        $dbu       = new DbUtils();
        $validator = ($item->fields["users_id_validate"] == Session::getLoginUserID());

        $can_validate = ($validator && $item->fields["status"] == CommonITILValidation::WAITING);

        // Approval comment editor (captured GLPI widget), only used when validating.
        $comment_field = '';
        if ($can_validate) {
            ob_start();
            Html::textarea([
                'name'            => 'comment_validation',
                'value'           => $item->fields["comment_validation"],
                'enable_richtext' => false,
                'cols'            => '90',
                'rows'            => '3',
            ]);
            $comment_field = ob_get_clean();
        }

        $refused_or_accepted = [CommonITILValidation::REFUSED, CommonITILValidation::ACCEPTED];

        TemplateRenderer::getInstance()->display('@purchaserequest/pr_validation.html.twig', [
            'target'                 => Toolbox::getItemTypeFormURL(PurchaseRequest::class),
            'id'                     => $item->fields['id'],
            'requester_name'        => $dbu->getUserName($item->fields["users_id_creator"]),
            'approver_name'         => $dbu->getUserName($item->fields["users_id_validate"]),
            'can_validate'           => $can_validate,
            'comment_field'          => $comment_field,
            'status_code'            => $item->fields["status"],
            'status_label'           => CommonITILValidation::getStatus($item->fields["status"]),
            'show_validation_comment' => in_array($item->fields["status"], $refused_or_accepted)
                && !empty($item->fields["comment_validation"]),
            'comment_validation'     => $item->fields["comment_validation"],
        ]);

        // Accept/refuse handlers are inline JS and stay in PHP (no <script> in Twig).
        if ($can_validate) {
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
                              });',
            );
        }
    }

    /**
     * @since version 0.85
     *
     * @see CommonDBTM::showMassiveActionsSubForm()
     **/
    public static function showMassiveActionsSubForm(MassiveAction $ma)
    {
        switch ($ma->getAction()) {
            case 'link':
                PluginOrderOrder::dropdown();
                echo "&nbsp;"
                    . Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;

            case 'delete_link':
                echo "&nbsp;"
                    . Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;

            case 'validate':
                CommonITILValidation::dropdownStatus('status');
                echo "</br>"
                    . Html::submit(_x('button', 'Post'), ['name' => 'massiveaction']);
                return true;
        }
        return "";
    }

    /**
     * Get the specific massive actions
     *
     * @param $checkitem link item to check right   (default NULL)
     *
     * @return $actions
     **@since version 0.84
     *
     * This should be overloaded in Class
     *
     */
    public function getSpecificMassiveActions($checkitem = null)
    {
        $actions['GlpiPlugin\Purchaserequest\PurchaseRequest:link'] = __("Link to an order", "purchaserequest");
        $actions['GlpiPlugin\Purchaserequest\PurchaseRequest:delete_link'] = __("Delete link to order", "purchaserequest");
        if (self::canValidation()) {
            $actions['GlpiPlugin\Purchaserequest\PurchaseRequest:validate'] = __(
                "Validate purchasing requests",
                "purchaserequest",
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
    public static function processMassiveActionsForOneItemtype(MassiveAction $ma, CommonDBTM $item, array $ids)
    {
        switch ($ma->getAction()) {
            case "link":
                $input = $ma->getInput();
                $order_id = (int) ($input['plugin_order_orders_id'] ?? 0);

                // Load the target order once and scope it to the caller's entity:
                // can() below only re-checks the purchase request row (per-id UPDATE +
                // entity), but plugin_order_orders_id is a second, independent foreign
                // key taken from the massive-action input. Without this extra check a
                // user could link one of their own requests to an order belonging to
                // another entity (IDOR on the order reference).
                $order = new PluginOrderOrder();
                $order_ok = $order_id > 0
                    && $order->getFromDB($order_id)
                    && $order->can($order_id, READ);

                foreach ($ids as $id) {
                    // can() enforces the UPDATE right AND entity access per row.
                    // Core forwards the raw POSTed id list to the plugin, so without
                    // this gate a user could link a request from another entity to an
                    // arbitrary order (IDOR) -- mirror the front controller's check().
                    if ($item->can($id, UPDATE)) {
                        //Possible connection with an order if purchase request is validated
                        if (!$order_ok) {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                        } elseif ($item->fields['status'] == CommonITILValidation::ACCEPTED) {
                            $item->update([
                                "id" => $id,
                                "plugin_order_orders_id" => $order_id,
                                "update" => __('Update'),
                            ]);
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                        }
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                    }
                }
                return;
                break;

            case "delete_link":

                foreach ($ids as $id) {
                    // can() enforces the UPDATE right AND entity access per row,
                    // blocking a cross-entity unlink (IDOR) on ids POSTed to the
                    // massive-action endpoint — mirror the front controller's check().
                    if ($item->can($id, UPDATE)) {
                        $item->update([
                            "id" => $id,
                            "plugin_order_orders_id" => 0,
                            "update" => __('Update'),
                        ]);
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                    } else {
                        $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
                    }
                }
                return;
                break;
            case "validate":
                if (self::canValidation()) {
                    $input = $ma->getInput();
                    // The applied status comes straight from the massive-action input; a
                    // forged API request could POST an out-of-enum value. Reject anything
                    // outside the legal validation statuses before writing it — the front
                    // controller path is protected by Validation::prepareInputForUpdate(),
                    // this bulk path was not.
                    $validation = (int) ($input['status'] ?? -1);
                    if (!in_array($validation, [CommonITILValidation::WAITING, CommonITILValidation::ACCEPTED, CommonITILValidation::REFUSED], true)) {
                        $ma->itemDone($item->getType(), 0, MassiveAction::ACTION_KO);
                        return;
                    }
                    foreach ($ids as $id) {
                        // can() enforces the plugin right AND entity access per row, mirroring
                        // the front controller's check(). getFromDB() alone skipped the entity
                        // scope, so a validator who lost access to the request's entity could
                        // still act on it through this bulk endpoint (IDOR).
                        if ($item->can($id, READ)) {
                            if ($item->fields['users_id_validate'] == Session::getLoginUserID()) {
                                $item->update([
                                    "id" => $id,
                                    "update_status" => true,
                                    "status" => $validation,
                                    "comment_validation" => "",
                                    "update" => __('Update'),
                                ]);

                                $validationrequest = new Validation();
                                if ($validationrequest->getFromDBByCrit(["plugin_purchaserequest_purchaserequests_id" => $id])) {
                                    $validationrequest->update([
                                        "id"     => $validationrequest->fields["id"],
                                        "status" => $validation,
                                    ]);
                                }


                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_OK);
                            } else {
                                $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_KO);
                            }
                        } else {
                            $ma->itemDone($item->getType(), $id, MassiveAction::ACTION_NORIGHT);
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
    public static function displayGroup($users_id)
    {
        //list of groups
        $group_users = Group_User::getUserGroups($users_id);
        $groups = [];

        foreach ($group_users as $item) {
            $groups[] = (int) $item['id'];
        }

        if (count($groups) > 0) {
            // Restrict the dropdown to groups within the caller's own entity scope.
            // The endpoint only requires plugin READ, so without this an authenticated
            // user could POST arbitrary users_id values and enumerate any account's
            // full group membership across entities. Intersecting with the session
            // entities bounds the disclosure to the caller's perimeter.
            $dbu = new DbUtils();
            $condition['condition'] = array_merge(
                ['id' => $groups],
                $dbu->getEntitiesRestrictCriteria(Group::getTable(), '', '', true),
            );
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
    public static function getHistoryEntry($data)
    {
        switch ($data['linked_action'] - Log::HISTORY_PLUGIN) {
            case self::HISTORY_ADDLINK:
                return sprintf(
                    __('%1$s: %2$s'),
                    __('Add a link with an item'),
                    $data["new_value"],
                );

            case self::HISTORY_DELLINK:
                return sprintf(
                    __('%1$s: %2$s'),
                    __('Delete a link with an item'),
                    $data["old_value"],
                );
        }
        return '';
    }

    public static function transfer($ID, $entity)
    {
        global $DB;

        if ($ID > 0) {
            $iterator = $DB->request([
                'FROM'  => self::getTable(),
                'WHERE' => ['id' => (int) $ID],
            ]);

            if (count($iterator)) {
                $data              = $iterator->current();
                $input['name']     = $data['name'];
                $input['entities_id'] = $entity;
                $temp  = new self();
                $newID = $temp->getID($input);

                if ($newID < 0) {
                    $newID = $temp->import($input);
                }

                return $newID;
            }
        }
        return 0;
    }

    /**
     * @param Migration $migration
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
                    `comment` MEDIUMTEXT COLLATE utf8mb4_unicode_ci,
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
            // No "or die($DB->error())": the raw MySQL error must not leak to output.
            $DB->doQuery($query);
        } else {
            if (!$DB->fieldExists($table, 'locations_id')) {
                $DB->doQuery(
                    "ALTER TABLE `$table`
                     ADD `locations_id` int unsigned NOT NULL DEFAULT '0';",
                );
            }
            if (!$DB->fieldExists($table, 'plugin_purchaserequest_purchaserequeststates_id')) {
                $DB->doQuery(
                    "ALTER TABLE `$table`
                     ADD `plugin_purchaserequest_purchaserequeststates_id` int unsigned NOT NULL DEFAULT '0';",
                );

                $DB->doQuery(
                    "ALTER TABLE `$table`
                     ADD `is_deleted` tinyint NOT NULL DEFAULT '0';",
                );
            }

            if (!$DB->fieldExists($table, 'processing_date')) {
                $DB->doQuery(
                    "ALTER TABLE `$table`
                     ADD `processing_date` timestamp NULL DEFAULT NULL;",
                );
            }

            if (!$DB->fieldExists($table, 'invoice_customer')) {
                $DB->doQuery(
                    "ALTER TABLE `$table`
                     ADD `invoice_customer` tinyint NOT NULL DEFAULT '0';",
                );
                $DB->doQuery(
                    "ALTER TABLE `$table`
                     ADD `amount` int unsigned NOT NULL DEFAULT '0';",
                );
            }

            $DB->doQuery(
                "ALTER TABLE `$table`
                   CHANGE `amount` `amount` decimal(20, 4) NOT NULL DEFAULT '0.0000';",
            );

            // The rich-text description embeds pasted images. A TEXT column
            // (64 KiB) overflows on such content and MySQL rejects the whole
            // write (error 1406 "Data too long"). Widen it to MEDIUMTEXT (16 MiB).
            $DB->doQuery(
                "ALTER TABLE `$table`
                   CHANGE `comment` `comment` MEDIUMTEXT COLLATE utf8mb4_unicode_ci;",
            );
        }

        $query = $DB->buildUpdate(
            'glpi_displaypreferences',
            [
                'itemtype' => self::class,
            ],
            [
                'itemtype' =>  'PluginPurchaserequestPurchaseRequest',
            ],
        );

        $DB->doQuery($query);

        $query = $DB->buildUpdate(
            'glpi_documents_items',
            [
                'itemtype' => self::class,
            ],
            [
                'itemtype' =>  'PluginPurchaserequestPurchaseRequest',
            ],
        );

        $DB->doQuery($query);

        $query = $DB->buildUpdate(
            'glpi_savedsearches',
            [
                'itemtype' => self::class,
            ],
            [
                'itemtype' =>  'PluginPurchaserequestPurchaseRequest',
            ],
        );

        $DB->doQuery($query);

        $query = $DB->buildUpdate(
            'glpi_savedsearches_users',
            [
                'itemtype' => self::class,
            ],
            [
                'itemtype' =>  'PluginPurchaserequestPurchaseRequest',
            ],
        );

        $DB->doQuery($query);
    }

    public static function uninstall()
    {
        global $DB;

        $dbu = new DbUtils();
        $table = $dbu->getTableForItemType(__CLASS__);
        // Use the query builder instead of a raw DELETE string so the itemtype
        // is bound safely and the routine follows the GLPI DB API convention.
        foreach (["displaypreferences", "documents_items", "savedsearches", "logs"] as $t) {
            $DB->delete('glpi_' . $t, ['itemtype' => self::class]);
        }
        // Do not append "or die($DB->error())": it would echo the raw MySQL error
        // (table names, constraints) to the install/upgrade screen. doQuery() logs
        // failures to the SQL error log on its own.
        $DB->doQuery("DROP TABLE IF EXISTS`" . $table . "`");
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
    //    $menu['links']['config']                      = Config::getFormURL(false);
    //Link to config page in admin plugins list
    //    $menu['config_page']                          = Config::getFormURL(false);

    //   return $menu;
    //}

}
