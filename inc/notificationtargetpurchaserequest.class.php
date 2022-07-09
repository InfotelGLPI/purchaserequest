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

// Class PluginPurchaserequestNotificationTargetPurchaseRequest
class PluginPurchaserequestNotificationTargetPurchaseRequest extends NotificationTarget {
   const PURCHASE_VALIDATOR = 30;
   const PURCHASE_AUTHOR    = 31;


   public function getEvents() {
      return [
         'ask_purchaserequest'           => __("Request for validation of the purchase request", "purchaserequest"),
         'no_validation_purchaserequest' => __("Refusal of validation request", "purchaserequest"),
         'validation_purchaserequest'    => __("Purchase request validation", "purchaserequest"),
      ];
   }

   public function addDataForTemplate($event, $options = []) {
      global $CFG_GLPI;

      $dbu    = new DbUtils();
      $events = $this->getAllEvents();

      $this->data['##purchaserequest.action##'] = $events[$event];

      $this->data['##lang.purchaserequest.title##'] = $events[$event];

      $this->data['##lang.purchaserequest.entity##'] = __("Entity");
      $this->data['##purchaserequest.entity##']      = Dropdown::getDropdownName('glpi_entities',
                                                                                 $this->obj->getField('entities_id'));

      $this->data['##lang.purchaserequest.name##'] = __("Name");
      $this->data['##purchaserequest.name##']      = $this->obj->getField("name");

      $this->data['##lang.purchaserequest.amount##'] = __("Amount", "purchaserequest");
      $this->data['##purchaserequest.amount##']      = Dropdown::getValueWithUnit($this->obj->getField("amount"), "€");

      $this->data['##lang.purchaserequest.rebill##'] = __("To be rebilled to the customer", "purchaserequest");
      $this->data['##purchaserequest.rebill##']      = Dropdown::getYesNo($this->obj->getField("invoice_customer"));

      $this->data['##lang.purchaserequest.requester##'] = __("Requester");
      $this->data['##purchaserequest.requester##']      = getUserName($this->obj->getField('users_id'));

      $this->data['##lang.purchaserequest.group##'] = __("Requester group");
      $this->data['##purchaserequest.group##']      = Dropdown::getDropdownName('glpi_groups',
                                                                                            $this->obj->getField('groups_id'));

      $this->data['##lang.purchaserequest.duedate##'] = __("Due date", "purchaserequest");
      $this->data['##purchaserequest.duedate##']      = Html::convDate($this->obj->getField("due_date"));

      $this->data['##lang.purchaserequest.comment##'] = __("Description");

      //      $comment                                    = Toolbox::stripslashes_deep(str_replace(['\r\n', '\n', '\r'], "<br/>", $this->obj->getField('comment')));
      //      $comment                                       = html_entity_decode(stripslashes($this->obj->fields['comment']));
      $this->data['##purchaserequest.comment##'] = $this->obj->fields['comment'];

      $itemtype = $this->obj->getField("itemtype");

      $this->data['##lang.purchaserequest.itemtype##'] = __("Item type");
      if (file_exists(GLPI_ROOT . "/src/" . $itemtype . "Type.php")) {
         $this->data['##purchaserequest.itemtype##'] = Dropdown::getDropdownName($dbu->getTableForItemType($itemtype . "Type"),
                                                                                 $this->obj->getField("types_id"));
      } else if ($itemtype == "PluginOrderOther") {
         $this->data['##purchaserequest.itemtype##'] = $this->obj->getField('othertypename');
      }

      $this->data['##lang.purchaserequest.type##'] = __("Type");
      $item                                        = new $itemtype();
      $this->data['##purchaserequest.type##']      = $item->getTypeName();

      switch ($event) {
         case "ask_purchaserequest" :
            $this->data['##lang.purchaserequest.users_validation##'] = __("Purchase request validation", "purchaserequest")
                                                                       . " " . __("By");
            break;
         case "validation_purchaserequest" :
            $this->data['##lang.purchaserequest.users_validation##'] = __("Purchase request is validated", "purchaserequest")
                                                                       . " " . __("By");
            break;
         case "no_validation_purchaserequest" :
            $this->data['##lang.purchaserequest.users_validation##'] = __("Purchase request canceled", "purchaserequest")
                                                                       . " " . __("By");
            break;

      }
      $this->data['##purchaserequest.users_validation##'] = getUserName($this->obj->getField('users_id_validation'));

      $restrict            = ['plugin_purchaserequest_purchaserequests_id' => $this->obj->getField("id")];
      $dbu                 = new DbUtils();
      $validations         = $dbu->getAllDataFromTable('glpi_plugin_purchaserequest_validations', $restrict);
      $data['validations'] = [];
      if (count($validations)) {
         $this->data['##lang.validation.state##']          = _x('item', 'State');
         $this->data['##lang.validation.datesubmit##']     = __('Request date');
         $this->data['##lang.validation.requester##']      = __('Approval requester');
         $this->data['##lang.validation.approver##']       = __('Approver');
         $this->data['##lang.validation.comment##']        = __('Approval comments');
         $this->data['##lang.validation.datevalidation##'] = __('Approval date');

         $validation = new PluginPurchaserequestValidation();
         foreach ($validations as $row) {

            $tmp = [];

            $tmp['##validation.state##']
                                   = CommonITILValidation::getStatus($row['status']);
            $tmp['##validation.datesubmit##']
                                   = Html::convDateTime($row["submission_date"]);
            $tmp['##validation.requester##']
                                   = getUserName($row["users_id"]);
            $tmp['##validation.approver##']
                                   = getUserName($row["users_id_validate"]);
            $tmp['##validation.comment##']
                                   = $row["comment_validation"];
            $tmp['##validation.datevalidation##']
                                   = Html::convDateTime($row["validation_date"]);
            $data['validations'][] = $tmp;

         }
         $this->data["validations"] = $data["validations"];
      }
      $this->data['##lang.purchaserequest.url##'] = "URL";

      $url                                   = $CFG_GLPI["url_base"] . "/index.php?redirect=PluginPurchaserequestPurchaserequest_" . $this->obj->getField("id");
      $this->data['##purchaserequest.url##'] = urldecode($url);

   }

   public function getTags() {
      $tags = [
         'purchaserequest.name'             => __("Name"),
         'purchaserequest.requester'        => __("Requester"),
         'purchaserequest.group'            => __("Requester group"),
         'purchaserequest.duedate'          => __("Due date", "purchaserequest"),
         'purchaserequest.comment'          => __("Description"),
         'purchaserequest.itemtype'         => __("Item type"),
         'purchaserequest.type'             => __("Type"),
         'purchaserequest.amount'           => __("Amount", "purchaserequest"),
         'purchaserequest.rebill'           => __("To be rebilled to the customer", "purchaserequest"),
         'purchaserequest.users_validation' => __("Editor of validation", "purchaserequest"),
         'validation.state'                 => _x('item', 'State'),
         'validation.datesubmit'            => __('Request date'),
         'validation.requester'             => __('Approval requester'),
         'validation.approver'              => __('Approver'),
         'validation.comment'               => __('Approval comments'),
         'validation.datevalidation'        => __('Approval date'),

      ];

      foreach ($tags as $tag => $label) {
         $this->addTagToList([
                                'tag'   => $tag,
                                'label' => $label,
                                'value' => true,
                             ]);
      }

      //Foreach global tags
      $tags = ['validations' => __('Approval')];

      foreach ($tags as $tag => $label) {
         $this->addTagToList(['tag'     => $tag,
                              'label'   => $label,
                              'value'   => false,
                              'foreach' => true]);
      }
      asort($this->tag_descriptions);
   }

   public static function install(Migration $migration) {
      global $DB;

      $migration->displayMessage("Migrate PluginPurchaserequestPurchaserequest notifications");

      $template     = new NotificationTemplate();
      $templates_id = false;
      $query_id     = "SELECT `id`
                       FROM `glpi_notificationtemplates`
                       WHERE `itemtype`='PluginPurchaserequestPurchaseRequest'
                       AND `name` = 'Purchase Request Validation'";
      $result = $DB->query($query_id) or die ($DB->error());

      if ($DB->numrows($result) > 0) {
         $templates_id = $DB->result($result, 0, 'id');
      } else {
         $tmp          = [
            'name'     => 'Purchase Request Validation',
            'itemtype' => 'PluginPurchaserequestPurchaseRequest',
            'date_mod' => $_SESSION['glpi_currenttime'],
            'comment'  => '',
            'css'      => '',
         ];
         $templates_id = $template->add($tmp);
      }

      if ($templates_id) {
         $translation = new NotificationTemplateTranslation();
         $dbu         = new DbUtils();
         if (!$dbu->countElementsInTable($translation->getTable(), ["notificationtemplates_id" => $templates_id])) {
            $tmp['notificationtemplates_id'] = $templates_id;
            $tmp['language']                 = '';
            $tmp['subject']                  = '##lang.purchaserequest.title##';
            $tmp['content_text']             = '##lang.purchaserequest.url## : ##purchaserequest.url##
               ##lang.purchaserequest.entity## : ##purchaserequest.entity##
               ##IFpurchaserequest.name####lang.purchaserequest.name## : ##purchaserequest.name##
               ##ENDIFpurchaserequest.name##
               ##IFpurchaserequest.requester####lang.purchaserequest.requester## : ##purchaserequest.requester##
               ##ENDIFpurchaserequest.requester##               
               ##IFpurchaserequest.group####lang.purchaserequest.group## : ##purchaserequest.group##
               ##ENDIFpurchaserequest.group##
               ##IFpurchaserequest.due_date####lang.purchaserequest.due_date##  : ##purchaserequest.due_date####ENDIFpurchaserequest.due_date##
               ##IFpurchaserequest.itemtype####lang.purchaserequest.itemtype## : ##purchaserequest.itemtype####ENDIFpurchaserequest.itemtype##
               ##IFpurchaserequest.type####lang.purchaserequest.type## : ##purchaserequest.type####ENDIFpurchaserequest.type##

               ##IFpurchaserequest.comment####lang.purchaserequest.comment## : ##purchaserequest.comment####ENDIFpurchaserequest.comment##';

            $tmp['content_html'] = '&lt;p&gt;&lt;strong&gt;##lang.purchaserequest.url##&lt;/strong&gt; : ' .
                                   '&lt;a href=\"##purchaserequest.url##\"&gt;##purchaserequest.url##&lt;/a&gt;&lt;br /&gt;' .
                                   '&lt;br /&gt;&lt;strong&gt;##lang.purchaserequest.entity##&lt;/strong&gt; : ##purchaserequest.entity##&lt;br /&gt;' .
                                   ' ##IFpurchaserequest.name##&lt;strong&gt;##lang.purchaserequest.name##&lt;/strong&gt;' .
                                   ' : ##purchaserequest.name####ENDIFpurchaserequest.name##&lt;br /&gt;' .
                                   '##IFpurchaserequest.requester##&lt;strong&gt;##lang.purchaserequest.requester##&lt;/strong&gt;' .
                                   ' : ##purchaserequest.requester####ENDIFpurchaserequest.requester##&lt;br /&gt;' .
                                   '##IFpurchaserequest.group##&lt;strong&gt;##lang.purchaserequest.group##&lt;/strong&gt;' .
                                   ' : ##purchaserequest.group####ENDIFpurchaserequest.group##&lt;br /&gt;' .
                                   '##IFpurchaserequest.due_date##&lt;strong&gt;##lang.purchaserequest.due_date##&lt;/strong&gt;' .
                                   ' : ##purchaserequest.due_date####ENDIFpurchaserequest.due_date##&lt;br /&gt;' .
                                   '##IFpurchaserequest.itemtype##&lt;strong&gt;##lang.purchaserequest.itemtype##&lt;/strong&gt;' .
                                   ' : ##purchaserequest.itemtype####ENDIFpurchaserequest.itemtype##&lt;br /&gt;' .
                                   '##IFpurchaserequest.type##&lt;strong&gt;##lang.purchaserequest.type##&lt;/strong&gt;' .
                                   ' : ##purchaserequest.type####ENDIFpurchaserequest.type##&lt;br /&gt;&lt;br /&gt;' .
                                   '##IFpurchaserequest.comment##&lt;strong&gt;##lang.purchaserequest.comment##&lt;/strong&gt; :' .
                                   '##purchaserequest.comment####ENDIFpurchaserequest.comment##&lt;/p&gt;';
            $translation->add($tmp);
         }

         $notifs               = [
            'New Purchase Request Validation'     => 'ask_purchaserequest',
            'Confirm Purchase Request Validation' => 'validation_purchaserequest',
            'Cancel Purchase Request Validation'  => 'no_validation_purchaserequest',
         ];
         $notification         = new Notification();
         $notificationtemplate = new Notification_NotificationTemplate();
         foreach ($notifs as $label => $name) {
            if (!$dbu->countElementsInTable("glpi_notifications",
                                            ["itemtype" => "PluginPurchaserequestPurchaserequest",
                                             "event"    => $name])) {
               $tmp             = [
                  'name'         => $label,
                  'entities_id'  => 0,
                  'itemtype'     => 'PluginPurchaserequestPurchaseRequest',
                  'event'        => $name,
                  'comment'      => '',
                  'is_recursive' => 1,
                  'is_active'    => 1,
                  'date_mod'     => $_SESSION['glpi_currenttime'],
               ];
               $notification_id = $notification->add($tmp);

               $notificationtemplate->add(['notificationtemplates_id' => $templates_id,
                                           'mode'                     => 'mailing',
                                           'notifications_id'         => $notification_id]);
            }
         }
      }

   }

   public static function uninstall() {
      global $DB;

      $notif = new Notification();

      foreach (['ask_purchaserequest', 'validation_purchaserequest', 'no_validation_purchaserequest'] as $event) {
         $options = [
            'itemtype' => 'PluginPurchaserequestPurchaseRequest',
            'event'    => $event,
            'FIELDS'   => 'id',
         ];

         foreach ($DB->request('glpi_notifications', $options) as $data) {
            $notif->delete($data);
         }
      }

      //templates
      $template       = new NotificationTemplate();
      $translation    = new NotificationTemplateTranslation();
      $notif_template = new Notification_NotificationTemplate();
      $options        = ['itemtype' => 'PluginPurchaserequestPurchaseRequest', 'FIELDS' => 'id'];

      foreach ($DB->request('glpi_notificationtemplates', $options) as $data) {
         $options_template = ['notificationtemplates_id' => $data['id'], 'FIELDS' => 'id'];

         foreach ($DB->request('glpi_notificationtemplatetranslations', $options_template) as $data_template) {
            $translation->delete($data_template);
         }
         $template->delete($data);

         foreach ($DB->request('glpi_notifications_notificationtemplates', $options_template) as $data_template) {
            $notif_template->delete($data_template);
         }
      }
   }

   /**
    * Get additionnals targets for Tickets
    **/
   public function addAdditionalTargets($event = '') {
      $this->addTarget(self::PURCHASE_VALIDATOR, __("Validator of the purchase request", "purchaserequest"));
      $this->addTarget(self::PURCHASE_AUTHOR, __("Author of the purchase request", "purchaserequest"));

   }

   public function addSpecificTargets($data, $options) {
      switch ($data['items_id']) {
         case self::PURCHASE_VALIDATOR:
            //            $this->addUserByField ("users_id_validate");
            $this->addValidationApprover($options);
            break;
         case self::PURCHASE_AUTHOR:
            $this->addUserByField("users_id_creator");
            break;

      }
   }


   /**
    * Add approver related to the ITIL object validation
    *
    * @param $options array
    *
    * @return void
    */
   function addValidationApprover($options = []) {
      global $DB;

      if (isset($options['validation_id'])) {
         $validationtable = getTableForItemType('PluginPurchaserequestValidation');

         $criteria                                 = ['LEFT JOIN' => [
               User::getTable() => [
                  'ON' => [
                     $validationtable => 'users_id_validate',
                     User::getTable() => 'id'
                  ]
               ]
            ]] + $this->getDistinctUserCriteria() + $this->getProfileJoinCriteria();
         $criteria['FROM']                         = $validationtable;
         $criteria['WHERE']["$validationtable.id"] = $options['validation_id'];

         $iterator = $DB->request($criteria);
         foreach ($iterator as $data) {
            $this->addToRecipientsList($data);
            $iterator->next();
         }
      }
   }
}
