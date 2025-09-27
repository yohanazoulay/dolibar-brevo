# Module Brevo Integration par Meditrust

## Installation

1. Vérifiez que les extensions PHP **cURL** (`php-curl`) et **JSON** (`php-json`) sont installées et activées : le module les utilise pour contacter l'API Brevo et décoder ses réponses.
2. Copier le dossier `brevointegration` dans `htdocs/custom/` de votre instance Dolibarr 21.0.2.
3. Connectez-vous en tant qu'administrateur et activez le module **Brevo Integration** depuis la page des modules.
4. Exécutez le script SQL `sql/llx_brevo_contactsync.sql` via l'interface Dolibarr (ou import SQL) pour créer les tables de synchronisation et de journalisation (`llx_brevo_contactsync`, `llx_brevo_log`).
5. En cas d'installation partielle ou de colonnes manquantes, l'onglet **Diagnostic** permet désormais de générer un patch SQL prêt à l'emploi pour remettre le schéma en conformité.

### Déploiement via l'assistant Dolibarr

Lors de l'utilisation du bouton **Déployer module externe** (upload ZIP), assurez-vous que :

- Le zip contient un dossier racine unique `brevointegration/` sans niveau supplémentaire.
- Le répertoire cible `htdocs/custom/brevointegration` n'existe pas ou est vide. Si une version précédente est encore présente, supprimez-la avec `rm -rf htdocs/custom/brevointegration` avant de relancer l'upload.
- L'utilisateur PHP/FPM a les droits d'écriture sur `htdocs/custom/` et `documents/admin/temp/`.

Si l'assistant affiche l'erreur :

```
Echec de copie du répertoire '/var/www/html/dolibarr/documents/admin/temp/brevointegration-x.y.z.dir/brevointegration' vers '/var/www/html/dolibarr/htdocs/custom/brevointegration'
```

1. Supprimez le répertoire cible `htdocs/custom/brevointegration` (ancien module ou copie partielle) puis réessayez.
2. Videz les artefacts temporaires éventuels : `rm -rf documents/admin/temp/brevointegration-*`.
3. Vérifiez les permissions : `chown -R www-data:www-data htdocs/custom documents/admin/temp` (adapté à votre distribution).
4. Relancez enfin l'import du zip.

Ces étapes évitent les conflits de copie lorsque Dolibarr tente d'écraser un module déjà présent.

## Arborescence du module

Le zip distribué doit contenir **un unique dossier racine** `brevointegration/` avec les répertoires standards Dolibarr ci-dessous :

```
brevointegration/
├── admin/
│   ├── logs.php
│   └── setup.php
├── class/
│   ├── actions_brevointegration.class.php
│   ├── brevoapi.class.php
│   ├── brevolog.class.php
│   ├── brevosync.class.php
│   └── services/
│       ├── brevofieldmappingservice.class.php
│       ├── brevodatabasemaintenanceservice.class.php
│       └── brevologservice.class.php
├── core/
│   └── modules/
│       └── modBrevoIntegration.class.php
├── langs/
│   ├── en_US/
│   │   └── brevointegration.lang
│   └── fr_FR/
│       └── brevointegration.lang
├── sql/
│   └── llx_brevo_contactsync.sql
├── tests/
│   ├── bootstrap.php
│   ├── phpunit.xml.dist
│   └── unit/
│       ├── BrevoApiTest.php
│       ├── BrevoFieldMappingServiceTest.php
│       └── BrevoSyncTest.php
├── tpl/
│   └── contact_brevointegration.tpl.php
├── CHANGELOG.md
├── README.md
└── lists.php
```

Cette structure garantit la compatibilité avec l'assistant « Déployer module externe » de Dolibarr et évite les erreurs de copie lors de l'installation.

## Configuration

1. Rendez-vous dans **Configuration > Modules/Applications > Brevo > Paramètres**.
2. Renseignez votre clé API Brevo. La clé est validée immédiatement via l'API (`GET /account`).
3. Après validation, la clé est stockée dans la constante `MAIN_BREVOINTEGRATION_APIKEY`.
4. Configurez les correspondances de champs Dolibarr <-> Brevo : pour chaque attribut Brevo, choisissez un champ standard ou un extrafield Dolibarr (contact ou tiers). Ajoutez une ligne vide pour créer une nouvelle association.
5. Associez les catégories de contacts Dolibarr aux listes Brevo pour automatiser l'inscription des contacts dans les bonnes listes marketing.

## Utilisation

- Consultez les listes Brevo synchronisées via le menu **Brevo Integration > Listes**. La pagination Dolibarr permet de naviguer entre les listes.
- Depuis la fiche d'un tiers ou d'un contact Dolibarr, utilisez le bloc **Intégration Brevo** pour :
  - Pousser le contact dans une liste Brevo (`POST /contacts`).
  - Synchroniser le contact avec toutes les listes liées à ses catégories Dolibarr.
  - Visualiser les listes dans lesquelles le contact est inscrit.
  - Retirer le contact d'une liste (`POST /contacts/lists/{id}/contacts/remove`).
- Les attributs envoyés à Brevo reprennent les correspondances configurées (champs standards et extrafields Dolibarr).
- Les synchronisations sont mémorisées dans la table `llx_brevo_contactsync` avec le statut et la date de dernière action.
- Les appels API Brevo sont historisés dans `llx_brevo_log` et consultables depuis **Configuration > Brevo Logs** (module activé).

## Tests

Un jeu de tests unitaires est fourni (wrapper API et DAO de synchronisation).

```bash
phpunit -c tests/phpunit.xml.dist
```

Les tests utilisent des stubs compatibles PHP 7.4 pour simuler l'environnement Dolibarr.
