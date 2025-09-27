# Module Brevo Integration par Meditrust

## Installation

1. Copier le dossier `brevointegration` dans `htdocs/custom/` de votre instance Dolibarr 21.0.2.
2. Connectez-vous en tant qu'administrateur et activez le module **Brevo Integration** depuis la page des modules.
3. Exécutez le script SQL `sql/llx_brevo_contactsync.sql` via l'interface Dolibarr (ou import SQL) pour créer la table de synchronisation si nécessaire.

## Configuration

1. Rendez-vous dans **Configuration > Modules/Applications > Brevo > Paramètres**.
2. Renseignez votre clé API Brevo. La clé est validée immédiatement via l'API (`GET /account`).
3. Après validation, la clé est stockée dans la constante `MAIN_BREVOINTEGRATION_APIKEY`.

## Utilisation

- Consultez les listes Brevo synchronisées via le menu **Brevo Integration > Listes**. La pagination Dolibarr permet de naviguer entre les listes.
- Depuis la fiche d'un tiers ou d'un contact Dolibarr, utilisez le bloc **Intégration Brevo** pour :
  - Pousser le contact dans une liste Brevo (`POST /contacts`).
  - Visualiser les listes dans lesquelles le contact est inscrit.
  - Retirer le contact d'une liste (`POST /contacts/lists/{id}/contacts/remove`).
- Les synchronisations sont mémorisées dans la table `llx_brevo_contactsync` avec le statut et la date de dernière action.

## Tests

Un jeu de tests unitaires est fourni (wrapper API et DAO de synchronisation).

```bash
phpunit -c tests/phpunit.xml.dist
```

Les tests utilisent des stubs compatibles PHP 7.4 pour simuler l'environnement Dolibarr.
