# Purchaserequest Plugin — Documentation

## Overview

The **Purchaserequest** plugin for GLPI manages **purchase requests** that must be approved before a purchase order is created in the **Order** plugin. It implements a two-level approval workflow: a manually designated approver chosen by the requester, and an automatic escalation to the **General Service Manager** when the requested amount exceeds a per-equipment-type threshold.

- **License**: GPLv3+
- **Authors**: Infotel
- **Dependency**: **Order** plugin (table `glpi_plugin_order_orders` must exist)

---

## Features

- Submit a purchase request with amount, equipment type, location, and group
- Two-level approval workflow: manual approver + automatic GSM escalation when amount exceeds threshold
- Approval status propagation: immediate refusal, acceptance only when all validators have approved
- Per-equipment-type amount thresholds (computers, monitors, peripherals, etc.)
- Generate an Order directly from an approved purchase request
- Link to a GLPI ticket
- E-mail notifications: approval request, approval, refusal, no validation
- Bulk actions: link/unlink an Order, validate

---

## Prerequisites and installation

1. Install and activate the **Order** plugin first (the table `glpi_plugin_order_orders` must exist).
2. Download the archive from [GitHub Releases](https://github.com/InfotelGLPI/purchaserequest/releases).
3. Extract it into the `plugins/` directory of your GLPI installation.
4. Log in to GLPI as an administrator.
5. Go to **Configuration → Plugins** and activate **Purchaserequest**.

---

## Configuration

### General Service Manager

**Access**: **Plugins → Purchaserequest → Configuration**

Select the GLPI user who will automatically receive approval requests when the amount exceeds the equipment-type threshold. This setting is stored in `glpi_plugin_purchaserequest_configs` (`id_general_service_manager`).

### Per-equipment-type thresholds

**Access**: equipment type form → **Purchaserequest Threshold** tab

A threshold can be set for the following types: computer type, monitor type, peripheral type, network equipment type, printer type, phone type, consumable type, cartridge type, contract type, software license type, certificate type, rack type, PDU type, other Order type.

| Value | Behavior |
|---|---|
| Positive amount | Automatic escalation to the GSM if `amount > threshold` |
| `-1` | Threshold disabled — no automatic escalation |

---

## Rights

In **Administration → Profiles**, a **Purchaserequest** tab appears on each profile.

| Right | Scope |
|---|---|
| `plugin_purchaserequest_purchaserequest` | Purchase requests (READ/CREATE/UPDATE/DELETE/PURGE) |
| `plugin_purchaserequest_validate` | Approval of requests |
| `plugin_purchaserequest_config` | Plugin configuration |

---

## Usage

### Create a purchase request

**Access**: **Plugins → Purchaserequest → Add**

Mandatory fields:

| Field | Description |
|---|---|
| Requester (`users_id`) | User submitting the request |
| Comment | Purchase justification |
| Equipment type (`itemtype`) | Hardware or software category |
| Sub-type (`types_id`) | Sub-type matching the equipment type |
| Amount (`amount`) | Estimated purchase amount (decimal 20,4) |
| Approver (`users_id_validate`) | User responsible for approving the request |

Optional fields: name, group, location, due date, customer invoice reference.

### Approval workflow

When a purchase request is created, the plugin:

1. Creates a `Validation` record for the designated approver (status **Waiting**).
2. Checks whether the amount exceeds the threshold configured for the equipment type.
3. If the threshold is exceeded, creates a second `Validation` record for the General Service Manager.

**Status propagation rules**:

- **Refusal**: as soon as one validator refuses, the request immediately moves to **Refused**.
- **Acceptance**: the request moves to **Accepted** only when **all** validators have approved.
- On each status change, a notification is sent to the next waiting validator.

### Request statuses

| Status | Internal value | Description |
|---|---|---|
| Waiting | `WAITING` | Approval not yet received |
| Accepted | `ACCEPTED` | All validators have approved |
| Refused | `REFUSED` | At least one validator has refused |

### Link an Order

Once a request is approved, you can create or link an Order from:
- The **Orders** tab on the purchase request form
- The bulk actions in the purchase request list

The purchase request also appears as a tab on the corresponding Order form.

### Link to a ticket

A purchase request can be linked to a GLPI ticket. The ticket appears as a tab on the purchase request form, and the purchase request appears as a tab on the ticket form.

---

## Notifications

| Event | Recipients | Description |
|---|---|---|
| `ask_purchaserequest` | Waiting approver | New request to approve |
| `validation_purchaserequest` | Requester | Request approved |
| `no_validation_purchaserequest` | Requester | Request refused |

Notification templates are configurable under **Configuration → Notifications**.

---

## Database schema

| Table | Description |
|---|---|
| `glpi_plugin_purchaserequest_purchaserequests` | Purchase requests |
| `glpi_plugin_purchaserequest_validations` | Approval records |
| `glpi_plugin_purchaserequest_configs` | Plugin configuration (GSM) |
| `glpi_plugin_purchaserequest_thresholds` | Per-equipment-type thresholds |

---

## Integrations

### Order plugin

The primary integration. An approved purchase request can directly generate an order in the Order plugin. The order and the request are linked bidirectionally (cross-referenced tabs).

### GLPI tickets

A purchase request can be associated with a native GLPI ticket, allowing the purchase to be tracked in the context of an incident or service request.

---

## Uninstallation

In **Configuration → Plugins**, deactivate then uninstall **Purchaserequest**. All `glpi_plugin_purchaserequest_*` tables are dropped, along with the associated profile rights.

---

## Useful links

- [GitHub repository](https://github.com/InfotelGLPI/purchaserequest)
- [Report a bug](https://github.com/InfotelGLPI/purchaserequest/issues)
- [Contribute translations](https://explore.transifex.com/infotelGLPI/GLPI_purchaserequest/)
- [Infotel GLPI blog](https://blogglpi.infotel.com)
