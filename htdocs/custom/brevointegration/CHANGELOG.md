# Changelog

## [Unreleased]

## [1.3.4] - 2025-10-06
### Added
- Génération d'un patch SQL depuis l'onglet Diagnostic pour recréer la table `llx_brevo_log` ou ajouter les colonnes manquantes.
- Affichage des colonnes détectées pour `llx_brevo_log` et `llx_brevo_contactsync` afin de faciliter les audits de schéma.
### Changed
- Centralisation des contrôles de schéma dans un service de maintenance dédié pour fiabiliser les diagnostics.

## [1.3.3] - 2025-10-05
### Added
- Checklist de diagnostic complète sur l'onglet d'administration avec vérification des extensions PHP, de la base de données et de la configuration Brevo pour expliquer les erreurs 500.

## [1.3.2] - 2025-10-03
### Fixed
- Empêche l'erreur fatale lors de la validation de la clé API lorsque l'extension PHP cURL est absente et affiche un message explicite.
- Documente la dépendance à l'extension php-curl et localise le message d'erreur associé.

## [1.3.1] - 2025-10-02
### Fixed
- Correction de l'URL de l'éditeur vers le domaine officiel `meditrust.io`.
- Neutralisation des requêtes SQL sur la page des journaux lorsque la table `llx_brevo_log` n'est pas installée ou incomplète afin d'éviter les erreurs 500.

## [1.3.0] - 2025-10-01
### Added
- Synchronisation des contacts Brevo basée sur les catégories Dolibarr avec déclenchement direct depuis les fiches.
- Interface d'administration pour lier les catégories de contacts aux listes Brevo.

## [1.2.6] - 2025-09-30
### Fixed
- Renforcement du chargement de `main.inc.php` sur la page des journaux pour supporter les hébergements SaaS Dolibarr.

## [1.2.5] - 2025-09-29
### Added
- Onglet de diagnostic sur la page de configuration affichant la version actuelle du module.

## [1.2.4] - 2025-09-28
### Fixed
- Suppression du repli d'inclusion redondant sur la page des journaux pour éviter les erreurs fatales en environnement SaaS.
- Protection de l'accès au message du journal lorsqu'il est absent afin d'éviter les notices en mode strict.

## [1.2.3] - 2025-09-27
### Fixed
- Fallback vers `mktime` lorsque `dol_mktime` n'est pas disponible afin d'éviter l'erreur fatale sur la page des journaux.

## [1.2.2] - 2025-09-27
### Changed
- Utilisation du picto Brevo dédié pour le module et ses menus Dolibarr.

### Fixed
- Ajout de l'icône `object_icon-picto-brevo.svg` pour l'affichage du module dans la liste Dolibarr.
- Correction du lien de la roue dentée "Logs" pour les hébergements qui ne résolvaient pas le chemin `@brevointegration`.
- Ajout d'un repli d'inclusion pour charger les classes Brevo en environnement SaaS où `dol_include_once` ne résout pas le chemin personnalisé.

## [1.2.1] - 2024-05-29
### Added
- Stockage du libellé des listes Brevo synchronisées pour afficher un titre compréhensible dans les fiches contact et tiers.
- Script SQL d'upgrade et tests unitaires adaptés au nouveau champ `brevo_list_label`.

## [1.2.0] - 2024-05-28
### Added
- Configuration avancée des correspondances de champs Dolibarr/Brevo (champs standards et extrafields) avec interface d'administration dédiée.
- Nouveau service de mapping pour exploiter les correspondances lors de la synchronisation des contacts.

## [1.0.3] - 2024-05-27
### Fixed
- Ajout d'une procédure de dépannage documentée pour éviter l'échec de copie lors du déploiement du module via l'assistant Dolibarr.

## [1.0.2] - 2024-05-26
### Fixed
- Correction de l'arborescence du package pour respecter le standard Dolibarr (suppression du sous-répertoire redondant).
- Mise à jour du guide d'arborescence dans la documentation utilisateur.

## [1.0.1] - 2024-05-25
### Changed
- Renommage du module en **Brevo Integration** avec mise à jour des chemins Dolibarr et des constantes de configuration.

## [1.0.0] - 2024-05-25
### Added
- Module Brevo par Meditrust initial release.
- Configuration de la clé API avec validation instantanée.
- Consultation des listes Brevo avec pagination.
- Synchronisation des contacts Dolibarr vers Brevo et mémorisation en base.
- Retrait d'un contact d'une liste Brevo depuis Dolibarr.
- Tests unitaires pour l'API wrapper et la DAO de synchronisation.
# [1.1.0] - 2024-05-28
### Added
- Page d'administration des journaux Brevo avec filtre par période et pagination.
- Journalisation automatique des appels API Brevo (méthode, statut HTTP, temps de réponse, message).

