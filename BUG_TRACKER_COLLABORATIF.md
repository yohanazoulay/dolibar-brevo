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
