# Plugin Purchaserequest — Documentation

## Présentation

Le plugin **Purchaserequest** pour GLPI permet de gérer des **demandes d'achat** préalablement au lancement d'une commande via le plugin **Order**. Il introduit un circuit d'approbation à deux niveaux : un approbateur manuel désigné par le demandeur, et une escalade automatique vers le **Directeur des Services Généraux** lorsque le montant dépasse un seuil configuré par type d'équipement.

- **Licence** : GPLv3+
- **Auteurs** : Infotel
- **Dépendance** : plugin **Order** (table `glpi_plugin_order_orders` doit exister)

---

## Fonctionnalités

- Saisie d'une demande d'achat avec montant, type d'équipement, localisation et groupe
- Circuit d'approbation double : approbateur manuel + escalade automatique GSM si montant > seuil
- Propagation du statut d'approbation : refus immédiat, acceptation uniquement quand tous les valideurs ont approuvé
- Seuils de montant configurables par type d'équipement (ordinateurs, moniteurs, périphériques, etc.)
- Génération d'une commande Order directement depuis la demande d'achat approuvée
- Association à un ticket GLPI
- Notifications par e-mail : demande d'approbation, approbation, refus, absence de validation
- Actions de masse : lier/délier une commande Order, valider

---

## Prérequis et installation

1. Installer et activer le plugin **Order** en premier (la table `glpi_plugin_order_orders` doit être présente).
2. Télécharger l'archive depuis [GitHub Releases](https://github.com/InfotelGLPI/purchaserequest/releases).
3. Décompresser dans le répertoire `plugins/` de GLPI.
4. Se connecter à GLPI en tant qu'administrateur.
5. Aller dans **Configuration → Plugins** et activer **Purchaserequest**.

---

## Configuration

### Directeur des Services Généraux

**Accès** : **Plugins → Purchaserequest → Configuration**

Sélectionner l'utilisateur GLPI qui recevra automatiquement les demandes d'approbation quand le montant dépasse le seuil du type d'équipement. Ce rôle est stocké dans `glpi_plugin_purchaserequest_configs` (`id_general_service_manager`).

### Seuils par type d'équipement

**Accès** : fiche d'un type d'équipement → onglet **Seuil Purchaserequest**

Un seuil s'applique aux types suivants : ordinateur, moniteur, périphérique, équipement réseau, imprimante, téléphone, consommable, cartouche, contrat, licence logicielle, certificat, baie, PDU, autre type Order.

| Valeur | Comportement |
|---|---|
| Montant positif | Escalade automatique au DSG si `amount > threshold` |
| `-1` | Seuil désactivé — pas d'escalade automatique |

---

## Droits

Dans **Administration → Profils**, un onglet **Purchaserequest** apparaît sur chaque profil.

| Droit | Objet concerné |
|---|---|
| `plugin_purchaserequest_purchaserequest` | Demandes d'achat (READ/CREATE/UPDATE/DELETE/PURGE) |
| `plugin_purchaserequest_validate` | Approbation des demandes |
| `plugin_purchaserequest_config` | Configuration du plugin |

---

## Utilisation

### Créer une demande d'achat

**Accès** : **Plugins → Purchaserequest → Ajouter**

Champs obligatoires :

| Champ | Description |
|---|---|
| Demandeur (`users_id`) | Utilisateur à l'origine de la demande |
| Commentaire | Justification de l'achat |
| Type d'équipement (`itemtype`) | Catégorie de matériel ou logiciel |
| Sous-type (`types_id`) | Sous-type correspondant au type d'équipement |
| Montant (`amount`) | Montant estimé de l'achat (décimal 20,4) |
| Approbateur (`users_id_validate`) | Utilisateur chargé de valider la demande |

Champs optionnels : nom, groupe, localisation, date souhaitée, référence facture client.

### Circuit d'approbation

À la création d'une demande, le plugin :

1. Crée un enregistrement `Validation` pour l'approbateur désigné (statut **En attente**).
2. Vérifie si le montant dépasse le seuil configuré pour le type d'équipement.
3. Si le seuil est dépassé, crée un second enregistrement `Validation` pour le Directeur des Services Généraux.

**Règles de propagation du statut** :

- **Refus** : dès qu'un valideur refuse, la demande passe immédiatement à **Refusé**.
- **Acceptation** : la demande passe à **Accepté** uniquement quand **tous** les valideurs ont approuvé.
- À chaque changement de statut, une notification est envoyée au valideur suivant en attente.

### Statuts d'une demande

| Statut | Valeur interne | Description |
|---|---|---|
| En attente | `WAITING` | Approbation non encore reçue |
| Accepté | `ACCEPTED` | Tous les valideurs ont approuvé |
| Refusé | `REFUSED` | Au moins un valideur a refusé |

### Lier une commande Order

Une fois la demande approuvée, il est possible de créer ou lier une commande Order depuis :
- L'onglet **Commandes** de la fiche de la demande d'achat
- Les actions de masse de la liste des demandes

La demande d'achat apparaît également en onglet sur la fiche de la commande Order correspondante.

### Association à un ticket

Une demande d'achat peut être liée à un ticket GLPI. Le ticket apparaît en onglet sur la fiche de la demande, et la demande d'achat apparaît en onglet sur la fiche du ticket.

---

## Notifications

| Événement | Destinataires | Description |
|---|---|---|
| `ask_purchaserequest` | Approbateur en attente | Nouvelle demande à approuver |
| `validation_purchaserequest` | Demandeur | Demande approuvée |
| `no_validation_purchaserequest` | Demandeur | Demande refusée |

Les modèles de notification sont configurables dans **Configuration → Notifications**.

---

## Structure des tables

| Table | Description |
|---|---|
| `glpi_plugin_purchaserequest_purchaserequests` | Demandes d'achat |
| `glpi_plugin_purchaserequest_validations` | Enregistrements d'approbation |
| `glpi_plugin_purchaserequest_configs` | Configuration du plugin (DSG) |
| `glpi_plugin_purchaserequest_thresholds` | Seuils par type d'équipement |

---

## Intégrations

### Plugin Order

L'intégration principale du plugin. Une demande d'achat approuvée peut générer directement une commande dans le plugin Order. La commande et la demande sont liées de façon bidirectionnelle (onglets croisés).

### Tickets GLPI

Une demande d'achat peut être associée à un ticket GLPI natif, permettant de suivre l'achat dans le contexte d'un incident ou d'une demande de service.

---

## Désinstallation

Dans **Configuration → Plugins**, désactiver puis désinstaller **Purchaserequest**. Toutes les tables `glpi_plugin_purchaserequest_*` sont supprimées, ainsi que les droits de profil associés.

---

## Liens utiles

- [Dépôt GitHub](https://github.com/InfotelGLPI/purchaserequest)
- [Signaler un bug](https://github.com/InfotelGLPI/purchaserequest/issues)
- [Contribuer à la traduction](https://explore.transifex.com/infotelGLPI/GLPI_purchaserequest/)
- [Blog Infotel GLPI](https://blogglpi.infotel.com)
