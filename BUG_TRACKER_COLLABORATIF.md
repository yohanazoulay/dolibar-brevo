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
