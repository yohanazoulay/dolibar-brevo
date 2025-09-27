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
