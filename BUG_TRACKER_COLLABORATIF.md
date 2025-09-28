# Bug Tracker Collaboratif

> **Règle obligatoire**
>
> Avant de travailler sur un bug, consultez ce document.
> Si le bug existe déjà, ajoutez vos nouvelles observations ou tests dans sa fiche.
> Vérifiez les solutions déjà testées pour éviter de refaire les mêmes tentatives infructueuses.
> Si le bug n’existe pas encore, créez une nouvelle entrée en suivant le modèle ci-dessous.

## Modèle de fiche

- **ID du bug** :
- **Description** :
- **Date de détection** :
- **Étapes pour reproduire** :
- **Solutions déjà testées** :
  - Tentative 1 :
    - Détail :
    - Résultat :
  - Tentative 2 :
    - Détail :
    - Résultat :
- **Solution retenue / correctif appliqué** :
- **Statut actuel** : (ouvert, en cours, corrigé, abandonné)

---

## Historique des bugs

### BUG-2025-10-09-LOGS-500
- **ID du bug** : BUG-2025-10-09-LOGS-500
- **Description** : Erreur 500 sur la page `custom/brevointegration/admin/logs.php` sur certains hébergements proxifiés à cause du loader heuristique de `main.inc.php`.
- **Date de détection** : 2025-10-09
- **Étapes pour reproduire** :
  1. Déployer Dolibarr sur un hébergement où `$_SERVER['DOCUMENT_ROOT']` ne pointe pas vers `htdocs` (ex. reverse proxy SaaS).
  2. Accéder à `custom/brevointegration/admin/logs.php`.
  3. Constater l'erreur HTTP 500.
- **Solutions déjà testées** :
  - Tentative 1 :
    - Détail : Conserver le loader heuristique actuel.
    - Résultat : Toujours en erreur 500.
  - Tentative 2 :
    - Détail : Utiliser `require __DIR__.'/../../../main.inc.php';`.
    - Résultat : Inclusion OK sur infrastructure de tests.
- **Solution retenue / correctif appliqué** : Remplacement du loader heuristique par un `require` déterministe basé sur `__DIR__` et sécurisation de l'action `setapikey` avec `try/catch` pour exposer proprement les erreurs inattendues.
- **Statut actuel** : corrigé

### BUG-2025-10-10-BREVO-SETUP-500
- **ID du bug** : BUG-2025-10-10-BREVO-SETUP-500
- **Description** : Erreur 500 lors de l'ouverture des pages `admin/setup.php` et `admin/logs.php` en cas de clé API invalide ou de table de logs incomplète.
- **Date de détection** : 2025-10-10
- **Étapes pour reproduire** :
  1. Enregistrer une clé API Brevo invalide ou vide puis déclencher le test de connexion.
  2. Ouvrir `custom/brevointegration/admin/logs.php` quand la table `llx_brevo_log` est absente.
  3. Constater l'erreur HTTP 500 (messages non interceptés / requêtes SQL non sécurisées).
- **Solutions déjà testées** :
  - Tentative 1 : Conserver l'ancien client `BrevoApi` avec validations à l'enregistrement.
    - Résultat : Exceptions non interceptées → 500.
  - Tentative 2 : Récupérer les journaux via `BrevoLogService::fetchLogs()` sans contrôler le tri.
    - Résultat : SQL pouvant échouer selon la configuration → 500.
- **Solution retenue / correctif appliqué** : Introduction d'un client `BrevoClient` tolérant aux erreurs, séparation des actions « Enregistrer » / « Tester » avec vérification CSRF, requêtes SQL paginées sécurisées et messages utilisateur via `setEventMessages()`.
- **Statut actuel** : corrigé

### BUG-2025-10-12-SETUP-CHECKTOKEN
- **ID du bug** : BUG-2025-10-12-SETUP-CHECKTOKEN
- **Description** : Erreur fatale « Call to undefined function checkToken() » lors de l'enregistrement ou des actions POST sur `custom/brevointegration/admin/setup.php`.
- **Date de détection** : 2025-10-12
- **Étapes pour reproduire** :
  1. Ouvrir `custom/brevointegration/admin/setup.php`.
  2. Soumettre le formulaire (enregistrement de clé, test de connexion, mappings…).
  3. Constater l'erreur 500 avec le message « Call to undefined function checkToken() » dans les logs.
- **Solutions déjà testées** :
  - Tentative 1 : Conserver les inclusions actuelles (`admin.lib.php` uniquement).
    - Résultat : fonction `checkToken()` absente → erreur fatale.
- **Solution retenue / correctif appliqué** : Charger explicitement `core/lib/security.lib.php` dans la page de configuration et les hooks pour garantir la disponibilité de `checkToken()`.
- **Statut actuel** : corrigé

### BUG-2025-10-13-LOGS-MISSINGCOLS
- **ID du bug** : BUG-2025-10-13-LOGS-MISSINGCOLS
- **Description** : Erreur 500 sur `custom/brevointegration/admin/logs.php` lorsque `BrevoDatabaseMaintenanceService` retourne une liste de colonnes manquantes `null`, provoquant un `TypeError` sans trace dans `dolibarr.log`.
- **Date de détection** : 2025-10-13
- **Étapes pour reproduire** :
  1. Forcer un retour incohérent de `getLogTableStatus()` (ex. hook tiers ou ancien cache retournant `missing_columns = null`).
  2. Ouvrir `custom/brevointegration/admin/logs.php`.
  3. Constater l'erreur 500 et l'absence de journalisation.
- **Solutions déjà testées** :
  - Tentative 1 : Aucun traitement spécifique, laisser PHP remonter l'exception `TypeError`.
    - Résultat : Erreur 500 persistante sans trace exploitable.
- **Solution retenue / correctif appliqué** : Normalisation défensive des métadonnées de schéma dans `BrevoLogService`, conversion des colonnes en tableau typé et ajout d'une journalisation explicite côté interface admin.
- **Statut actuel** : corrigé

### BUG-2025-10-14-LOGS-IDATE-QUOTE
- **ID du bug** : BUG-2025-10-14-LOGS-IDATE-QUOTE
- **Description** : Les insertions dans `llx_brevo_log` échouent sur MariaDB/MySQL avec `DB_ERROR_SYNTAX` car `DoliDB::idate()` renvoie une date non quotée dans les requêtes construites dynamiquement.
- **Date de détection** : 2025-10-14
- **Étapes pour reproduire** :
  1. Activer le module et lancer le test Diagnostic (`/brevointegration/diagnostic`).
  2. Observer la requête `INSERT` générée sans quotes autour de `date_event`.
  3. Constater l'erreur SQL dans `dolibarr.log`.
- **Solutions déjà testées** :
  - Tentative 1 : Encapsuler uniquement le fallback `date('Y-m-d H:i:s')` dans des quotes.
    - Résultat : Marche seulement lorsque `idate()` n'est pas disponible.
- **Solution retenue / correctif appliqué** : Ajout du helper `brevointegration_format_sql_datetime()` forçant les quotes si nécessaire et utilisation systématique dans `BrevoLogService`, `BrevoLog`, `BrevoSync` et `admin/logs.php`.
- **Statut actuel** : corrigé

### BUG-2025-10-15-CSRF-FALLBACK
- **ID du bug** : BUG-2025-10-15-CSRF-FALLBACK
- **Description** : Environnements mutualisés ne chargeant pas `functions.lib.php` / `security.lib.php` provoquant toujours l'erreur fatale « Call to undefined function checkToken() » sur `admin/setup.php` et les hooks Brevo, empêchant la sauvegarde de la clé API et la désactivation du module.
- **Date de détection** : 2025-10-15
- **Étapes pour reproduire** :
  1. Déployer Dolibarr avec un loader restreint ne fournissant pas `checkToken()` / `newToken()` au module.
  2. Ouvrir `custom/brevointegration/admin/setup.php` et soumettre un formulaire ou utiliser les boutons Brevo sur une fiche contact.
  3. Constater l'erreur HTTP 500 et la trace « Call to undefined function checkToken() » dans `dolibarr.log`.
- **Solutions déjà testées** :
  - Tentative 1 : Charger directement `security.lib.php` depuis la page d'administration.
    - Résultat : Toujours en échec lorsque l'hébergeur bloque l'inclusion (fichier absent ou chemin filtré).
- **Solution retenue / correctif appliqué** : Création d'un helper `brevointegration_security.lib.php` qui tente de charger les bibliothèques natives et fournit un repli CSRF contrôlé (journalisation + génération/validation locale) pour éviter l'erreur 500.
- **Statut actuel** : corrigé

### BUG-2025-10-18-LOGS-NO-TRACE
- **ID du bug** : BUG-2025-10-18-LOGS-NO-TRACE
- **Description** : La page `custom/brevointegration/admin/logs.php` renvoie une erreur 500 silencieuse lorsque la requête SQL échoue, sans trace dans `dolibarr.log`.
- **Date de détection** : 2025-10-18
- **Étapes pour reproduire** :
  1. Accéder à `custom/brevointegration/admin/logs.php` sur un environnement où la table `llx_brevo_log` est présente mais où l'accès SQL échoue (droits insuffisants, schéma incomplet).
  2. Constater l'erreur HTTP 500.
  3. Vérifier l'absence de trace exploitable dans `dolibarr.log`.
- **Solutions déjà testées** :
  - Tentative 1 : S'appuyer uniquement sur `dol_syslog()` pour les erreurs SQL.
    - Résultat : Aucune trace lorsque PHP plante avant la journalisation centrale.
- **Solution retenue / correctif appliqué** : Création d'un logger dédié `brevo_admin.log` avec enregistrement systématique des requêtes, instrumentation complète du cycle de vie (démarrage, SQL, nombre de lignes, statut final) et encapsulation de la page dans un `try/catch`/`finally` pour afficher un message contrôlé (« Erreur SQL » ou « Erreur interne ») sans 500 silencieux.
- **Statut actuel** : corrigé

### BUG-2025-10-21-LOGS-LISTLIB
- **ID du bug** : BUG-2025-10-21-LOGS-LISTLIB
- **Description** : Erreur 500 sur `custom/brevointegration/admin/logs.php` lorsque `core/lib/list.lib.php` est absent sur les hébergements mutualisés (versions Dolibarr allégées), empêchant le rendu de la liste.
- **Date de détection** : 2025-10-21
- **Étapes pour reproduire** :
  1. Déployer le module sur un Dolibarr où seuls `functions.lib.php`/`functions2.lib.php` sont disponibles (pas de `list.lib.php`).
  2. Ouvrir `custom/brevointegration/admin/logs.php`.
  3. Constater l'erreur HTTP 500 sans journal Brevo.
- **Solutions déjà testées** :
  - Tentative 1 : Charger uniquement `core/lib/list.lib.php`.
    - Résultat : Inclusion impossible → erreur fatale.
- **Solution retenue / correctif appliqué** : Détection proactive des helpers de listes, repli automatique vers `functions2.lib.php` et rendu manuel de l'entête/pagination avec avertissement utilisateur et trace dans `brevo_admin.log`.
- **Statut actuel** : corrigé

### BUG-2025-10-16-DISABLE-CONST
- **ID du bug** : BUG-2025-10-16-DISABLE-CONST
- **Description** : Impossible de désactiver le module depuis la liste des modules Dolibarr, le curseur revient immédiatement à « activé ».
- **Date de détection** : 2025-10-16
- **Étapes pour reproduire** :
  1. Aller sur `setup/modules.php`.
  2. Cliquer sur l'interrupteur de désactivation du module Brevo Integration.
  3. Constater que l'interrupteur revient à l'état activé après rechargement.
- **Solutions déjà testées** :
  - Tentative 1 : Vidage du cache navigateur et rechargement de la page.
    - Résultat : Aucun effet, le module reste activé.
  - Tentative 2 : Suppression manuelle de la constante `BREVO_MODULE_BREVOINTEGRATION`.
    - Résultat : Le module se réactive automatiquement car Dolibarr s'appuie sur `MAIN_MODULE_BREVOINTEGRATION` pour gérer l'état.
- **Solution retenue / correctif appliqué** : Harmonisation de la constante `$this->const_name` du descripteur pour qu'elle corresponde à `MAIN_MODULE_BREVOINTEGRATION`, ce qui aligne le module sur le mécanisme standard d'activation/désactivation Dolibarr.
- **Statut actuel** : corrigé
