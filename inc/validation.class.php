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
class PluginPurchaserequestValidation extends CommonDBTM {
   public static $rightname = 'plugin_purchaserequest_validate';
   public        $dohistory = true;

   const HISTORY_ADDLINK = 50;
   const HISTORY_DELLINK = 51;

   /**
    * @param int $nb
    *
    * @return string|\translated
    */
   public static function getTypeName($nb = 0) {
      return _n("Validation", "Validations", $nb, "purchaserequest");
   }

   /**
    * @return bool
    */
   public static function canValidation() {
      return Session::haveRight("plugin_purchaserequest_validate", 1);
   }

   /**
    * @param array $options
    *
    * @return array
    */
   function defineTabs($options = []) {
      $ong = [];
      $this->addDefaultFormTab($ong);
      return $ong;
   }

   /**
    * @param \CommonGLPI $item
    * @param int         $withtemplate
    *
    * @return string|\translated
    */
   function getTabNameForItem(CommonGLPI $item, $withtemplate = 0) {

      if ($item->getType() == "PluginPurchaserequestPurchaseRequest") {
         return __('Approval');
      }

      return '';
   }


   /**
    * @param \CommonGLPI $item
    * @param int         $tabnum
    * @param int         $withtemplate
    *
    * @return bool
    */
   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item->getType() == "PluginPurchaserequestPurchaseRequest") {
         self::showValidation($item);
      }

      return true;
   }

   /**
    * @param array|\datas $input
    *
    * @return array|bool|\datas
    */
   public function prepareInputForAdd($input) {


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
   public function prepareInputForUpdate($input) {
      global $CFG_GLPI;

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
   public function post_addItem() {
      global $CFG_GLPI;

      if ($CFG_GLPI["notifications_mailing"]) {
         if (isset($this->input["first"])
             && $this->input["first"] == true) {
            $purchase_request = new PluginPurchaserequestPurchaseRequest();
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
         Log::history($this->input['tickets_id'], 'Ticket',
                      $changes, __CLASS__, Log::HISTORY_PLUGIN + self::HISTORY_ADDLINK);
      }
   }

   /**
    * Actions done after the UPDATE of the item in the database
    *
    * @return nothing
    **/
   function post_updateItem($history = 1) {
      global $CFG_GLPI;
      if (isset($this->oldvalues['tickets_id'])) {
         if ($this->oldvalues['tickets_id'] != 0) {
            $changes[0] = 0;
            $changes[1] = $this->input['id'];
            $changes[2] = '';
            Log::history($this->oldvalues['tickets_id'], 'Ticket',
                         $changes, __CLASS__, Log::HISTORY_PLUGIN + self::HISTORY_DELLINK);

         }
         if (!empty($this->fields['tickets_id'])) {
            {
               $changes[0] = 0;
               $changes[1] = '';
               $changes[2] = $this->fields["id"];
               Log::history($this->fields['tickets_id'], 'Ticket',
                            $changes, __CLASS__, Log::HISTORY_PLUGIN + self::HISTORY_ADDLINK);
            }
         }
      }
      if (isset($this->input["update_status"])) {
         $purchase_request = new PluginPurchaserequestPurchaseRequest();
         if (isset($this->input['status'])
             && $this->input['status'] == CommonITILValidation::REFUSED) {
            $input["status"] = CommonITILValidation::REFUSED;
            $input["id"]     = $this->fields["plugin_purchaserequest_purchaserequests_id"];
            $purchase_request->update($input);
         } else if (isset($this->input['status'])
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
                  } else if ($purchase_request->fields["status"] == CommonITILValidation::WAITING) {

                     $items = $this->find(["plugin_purchaserequest_purchaserequests_id" => $this->fields["plugin_purchaserequest_purchaserequests_id"]]);

                     foreach ($items as $item) {
                        if ($item["status"] == CommonITILValidation::WAITING) {
                           $options = ['validation_id'     => $item["id"],
                                       'validation_status' => $item["status"]];
                           NotificationEvent::raiseEvent('ask_purchaserequest', $purchase_request, $options);
                        }
                     }

                  }

               } else if (isset($this->input['status'])
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
   public function showForm($ID, $options = []) {
      global $CFG_GLPI;

      $dbu = new DbUtils();
      $this->initForm($ID, $options);
      $this->showFormHeader($options);

      $canedit            = $this->can($ID, UPDATE);
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
      echo "<tr class='tab_bg_1'><td>" . __("Requester") . "&nbsp;<span class='red'>*</span></td><td>";
      if ($canedit) {
         $rand_user = User::dropdown(['name'      => "users_id",
                                      'value'     => $this->fields["users_id"],
                                      'entity'    => $this->fields["entities_id"],
                                      'on_change' => "pluginPurchaserequestLoadGroups();",
                                      'right'     => 'all']);
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

         $JS     = "function pluginPurchaserequestLoadGroups(){";
         $params = ['users_id' => '__VALUE__',
                    'entity'   => $this->fields["entities_id"]];
         $JS     .= Ajax::updateItemJsCode("plugin_purchaserequest_group",
                                           PLUGIN_PURCHASEREQUEST_WEBDIR . "/ajax/dropdownGroup.php",
                                           $params, 'dropdown_users_id' . $rand_user, false);
         $JS     .= "}";
         echo Html::scriptBlock($JS);
      } else {
         echo Dropdown::getDropdownName($dbu->getTableForItemType('Group'), $this->fields["groups_id"]);
      }
      echo "</td></tr>";

      /* location */
      echo "<tr class='tab_bg_1'><td>" . __("Location") . "&nbsp;</td>";
      echo "<td>";
      Dropdown::show('Location', ['value'  => $this->fields["locations_id"],
                                  'entity' => $this->fields["entities_id"]]);
      echo "</td>";
      echo "<td>" . __("Status") . "&nbsp;</td>";
      echo "<td>";
      Dropdown::show('PluginPurchaserequestPurchaseRequestState',
                     ['value'  => $this->fields["plugin_purchaserequest_purchaserequeststates_id"],
                      'entity' => $this->fields["entities_id"]]);
      echo "</td></tr>";

      /* description */
      echo "<tr class='tab_bg_1'><td>" . __("Description") . "&nbsp;<span class='red'>*</span></td>";
      echo "<td colspan='3'>";
      Html::textarea(['name'            => 'comment',
                      'value'           => stripslashes($this->fields['comment']),
                      'enable_richtext' => false,
                      'cols'            => '100',
                      'rows'            => '4']);
      echo "</td></tr>";

      /* type */
      $reference = new PluginOrderReference();
      echo "<tr class='tab_bg_1'><td>" . __("Item type");
      echo "&nbsp;<span class='red'>*</span></td>";
      echo "<td>";
      $params = [
         'myname'    => 'itemtype',
         'value'     => $this->fields["itemtype"],
         'entity'    => $_SESSION["glpiactive_entity"],
         'ajax_page' => Plugin::getWebDir('order') . '/ajax/referencespecifications.php',
         'class'     => __CLASS__,
      ];

      $reference->dropdownAllItems($params);
      echo "</td>";

      echo "<td>" . __("Type") . "&nbsp;<span class='red'>*</span></td>";
      echo "<td>";
      echo "<span id='show_types_id'>";
      if ($this->fields['itemtype']) {
         if ($this->fields['itemtype'] == 'PluginOrderOther') {
            $file = 'other';
         } else {
            $file = $this->fields['itemtype'];
         }
         $core_typefilename   = GLPI_ROOT . "/src/" .$file . "Type.php";
         $plugin_typefilename = Plugin::getWebDir('order') . "/inc/" . strtolower($file) . "type.class.php";
         $itemtypeclass       = $this->fields['itemtype'] . "Type";

         if (file_exists($core_typefilename)
             || file_exists($plugin_typefilename)) {
            Dropdown::show($itemtypeclass,
                           [
                              'name'  => "types_id",
                              'value' => $this->fields["types_id"],
                           ]);

         }
      }
      echo "</span>";
      echo "</td></tr>";

      echo "<tr class='tab_bg_1'><td>" . __("Due date", "purchaserequest") . "</td>";
      echo "<td>";
      Html::showDateField("due_date", ['value' => $this->fields["due_date"]]);
      echo "</td>";

      echo "<td>" . __("To be validated by", "purchaserequest") . "&nbsp;<span class='red'>*</span></td>";
      echo "<td>";
      User::dropdown(['name'   => "users_id_validate",
                      'value'  => $this->fields["users_id_validate"],
                      'entity' => $this->fields["entities_id"],
                      'right'  => 'plugin_purchaserequest_validate']);
      echo "</td></tr>";
      echo "<tr class='tab_bg_1'><td>" . __("Amount", "purchaserequest") . "</td>";
      echo "<td>";
       $params = [
           'type' => 'text',
           'value' => number_format($this->fields['amount'], 2, '.', ' ')
       ];
       echo Html::input('amount', $params);
      echo "</td>";

      echo "<td>" . __("To be rebilled to the customer", "purchaserequest") . "&nbsp;</td>";
      echo "<td>";
      Html::showCheckbox(['name'    => "invoice_customer",
                          'checked' => $this->fields["invoice_customer"]
                         ]);

      echo "</td>";
      echo "</tr>";
      echo "<tr class='tab_bg_1'>";
      $order = new PluginOrderOrder();
      echo "<td>" . __("Linked to the order", "purchaserequest") . "</td>";
      echo "<td>";

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
    * Display list of purchase request linked to the order
    *
    * @param $item
    */
   static function showForOrder($item) {
      global $CFG_GLPI;

      $dbu = new DbUtils();
      if (isset($_REQUEST["start"])) {
         $start = $_REQUEST["start"];
      } else {
         $start = 0;
      }

      $purchase_request = new PluginPurchaserequestPurchaseRequest();
      $data             = $purchase_request->find(['plugin_order_orders_id' => $item->fields['id']]);

      $rows = count($data);

      $canread = Session::haveRight(self::$rightname, READ);

      if (!$rows || !$canread) {
         echo __('No item to display');
      } else {

         $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
         $rand    = mt_rand();

         echo "<div class='center'>";

         Html::printAjaxPager(PluginPurchaserequestPurchaseRequest::getTypeName(2), $start, $rows);
         echo "<form method='post' name='purchaseresquet_form$rand' id='purchaseresquet_form$rand'  " .
              "action='" . Toolbox::getItemTypeFormURL('PluginPurchaserequestPurchaseRequest') . "'>";

         echo "<table class='tab_cadre_fixe'>";
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
            echo "<td>" . Dropdown::getDropdownName('glpi_plugin_purchaserequest_purchaserequeststates',
                                                    $field['plugin_purchaserequest_purchaserequeststates_id']) . "</td>";
            // item type
            $item = new $field["itemtype"]();
            echo "<td>" . $item->getTypeName() . "</td>";
            // Model name
            $itemtypeclass = $field['itemtype'] . "Type";
            echo "<td>" . Dropdown::getDropdownName($dbu->getTableForItemType($itemtypeclass), $field["types_id"]) . "</td>";
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
            echo "<div class='center'>";
            echo "<table width='950px' class='tab_glpi'>";
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
   public function dropdownPurchaseRequestItemsActions() {

      $action['delete_link'] = __("Delete link with order", "purchaserequest");
      Dropdown::showFromArray('chooseAction', $action);

   }

   /**
    * @param $item
    */
   static function showValidation($item) {

      $dbu        = new DbUtils();
      $validation = new self();
      echo "<form name='form' id='formvalidation' method='post' action='" . Toolbox::getItemTypeFormURL('PluginPurchaserequestValidation') . "'>";

      echo "<div align='center'><table class='tab_cadre_fixe'>";
      //      echo "<tr class='tab_bg_2'>";
      //      echo "<th colspan='3'>" . __('Do you approve this purchase request ?', 'purchaserequest') . "</th>";
      //      echo "</tr>";
      //
      //      echo "<tr class='tab_bg_1'>";
      //      echo "<td colspan='2'>" . __('Approval requester') . "</td>";
      //      echo "<td class='center'>" . $dbu->getUserName($item->fields["users_id"]) . "</td></tr>";
      //
      //      echo "<tr class='tab_bg_1'><td colspan='2'>" . __('Approver') . "</td>";
      //      echo "<td class='center'>" . $dbu->getUserName($item->fields["users_id_validate"]) . "</td></tr>";
      //      echo "</td></tr>";
      if ($validation->getFromDBByCrit(["status" => CommonITILValidation::WAITING,
                                        "users_id_validate" => Session::getLoginUserID(),
                                        "plugin_purchaserequest_purchaserequests_id" => $item->getID()])) {
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
         echo "</td>";
         echo "</tr>";

         echo "<tr class='tab_bg_1'>";
         echo "<td>" . __('Approval comments') . "</td>";
         echo "<td colspan='2'>";
         Html::textarea(['name'            => 'comment_validation',
                         'enable_richtext' => false,
                         'cols'            => '90',
                         'rows'            => '3']);
         echo Html::hidden('id', ['value' => $validation->fields['id']]);
         echo Html::hidden('users_id_validate', ['value' => Session::getLoginUserID()]);
         echo Html::hidden('plugin_purchaserequest_purchaserequests_id', ['value' => $item->fields['id']]);
         echo "</td></tr>";
      }

      $validator = ($item->fields["users_id_validate"] == Session::getLoginUserID());


      echo "</table></div>";
      Html::closeForm();

      $self = new self();
      $self->showSummary($item);
   }


   /**
    * Print the validation list into item
    *
    * @param CommonDBTM $item
    **/
   function showSummary(CommonDBTM $item) {
      global $DB, $CFG_GLPI;

      //      if (!Session::haveRightsOr(static::$rightname,
      //                                 array_merge(static::getCreateRights(),
      //                                             static::getValidateRights(),
      //                                             static::getPurgeRights()))) {
      //         return false;
      //      }

      $tID = $item->fields['id'];

      $tmp    = ["plugin_purchaserequest_purchaserequests_id" => $tID];
      $canadd = $this->can(-1, CREATE, $tmp);
      $rand   = mt_rand();


      echo "<div id='viewvalidation" . $tID . "$rand'></div>\n";


      $iterator = $DB->Request([
                                  'FROM'  => $this->getTable(),
                                  'WHERE' => ["plugin_purchaserequest_purchaserequests_id" => $item->getField('id')],
                                  'ORDER' => 'submission_date DESC'
                               ]);

      $colonnes    = [_x('item', 'State'),
                      __('Request date'),
                      __('Approval requester'),
                      __('Approval status'),
                      __('Approver'),
                      __('Approval comments')];
      $nb_colonnes = count($colonnes);

      echo "<table class='tab_cadre_fixehov'>";
      echo "<tr class='noHover'>";
      echo "<th colspan='" . $nb_colonnes . "'>" ;
      echo __('Approvals for the purchase request', 'purchaserequest');
      echo "</th></tr>";

      //      if ($canadd) {
      //         if (!in_array($item->fields['status'], array_merge($item->getSolvedStatusArray(),
      //                                                            $item->getClosedStatusArray()))) {
      //            echo "<tr class='tab_bg_1 noHover'><td class='center' colspan='" . $nb_colonnes . "'>";
      //            echo "<a class='vsubmit' href='javascript:viewAddValidation".$tID."$rand();'>";
      //            echo __('Send an approval request')."</a></td></tr>\n";
      //         }
      //      }
      if (count($iterator)) {
         $header = "<tr>";
         foreach ($colonnes as $colonne) {
            $header .= "<th>" . $colonne . "</th>";
         }
         $header .= "</tr>";
         echo $header;

         Session::initNavigateListItems($this->getType(),
            //TRANS : %1$s is the itemtype name, %2$s is the name of the item (used for headings of a list)
                                        sprintf(__('%1$s = %2$s'), $item->getTypeName(1),
                                                $item->fields["name"]));

         foreach ($iterator as $row) {
            $canedit = $this->canEdit($row["id"]);
            Session::addToNavigateListItems($this->getType(), $row["id"]);
            $bgcolor = CommonITILValidation::getStatusColor($row['status']);
            $status  = CommonITILValidation::getStatus($row['status']);

            echo "<tr class='tab_bg_1'>";
            echo "<td>";
            //            if ($canedit) {
            //               echo "\n<script type='text/javascript' >\n";
            //               echo "function viewEditValidation" .$item->fields['id']. $row["id"]. "$rand() {\n";
            //               $params = ['type'             => $this->getType(),
            //                          'parenttype'       => static::$itemtype,
            //                          static::$items_id  => $this->fields[static::$items_id],
            //                          'id'               => $row["id"]];
            //               Ajax::updateItemJsCode("viewvalidation" . $item->fields['id'] . "$rand",
            //                                      $CFG_GLPI["root_doc"]."/ajax/viewsubitem.php",
            //                                      $params);
            //               echo "};";
            //               echo "</script>\n";
            //            }

            echo "<div style='background-color:" . $bgcolor . ";'>" . $status . "</div></td>";

            echo "<td>" . Html::convDateTime($row["submission_date"]) . "</td>";
            echo "<td>" . getUserName($row["users_id"]) . "</td>";

            echo "<td>" . Html::convDateTime($row["validation_date"]) . "</td>";
            echo "<td>" . getUserName($row["users_id_validate"]) . "</td>";
            echo "<td>" . $row["comment_validation"] . "</td>";
            echo "</tr>";
            $iterator->next();
         }
         echo $header;
      } else {
         //echo "<div class='center b'>".__('No item found')."</div>";
         echo "<tr class='tab_bg_1 noHover'><th colspan='" . $nb_colonnes . "'>";
         echo __('No item found') . "</th></tr>\n";
      }
      echo "</table>";
   }


   /**
    * @param \Migration $migration
    */
   public static function install(Migration $migration) {
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
         $DB->query($query) or die ($DB->error());

      } else {

      }

   }

   public static function uninstall() {
      global $DB;

      $dbu   = new DbUtils();
      $table = $dbu->getTableForItemType(__CLASS__);
      $DB->query("DROP TABLE IF EXISTS`" . $table . "`") or die ($DB->error());
   }

}
