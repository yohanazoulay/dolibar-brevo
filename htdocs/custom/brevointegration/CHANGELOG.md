# Changelog

## [Unreleased]

## [1.3.19] - 2025-10-21
### Fixed
- Ajout d'un repli automatique sur `admin/logs.php` lorsque `list.lib.php` est absent afin d'éviter l'erreur 500 et de conserver une pagination simplifiée.
- Journalisation et avertissement dédiés pour signaler l'utilisation du rendu de secours lorsque les helpers Dolibarr sont manquants.

## [1.3.18] - 2025-10-20
### Changed
- Harmonisation du chargement de l'environnement Dolibarr pour supporter les installations du module en racine ou dans `htdocs/custom`.

## [1.3.17] - 2025-10-19
### Changed
- Ajout d'une journalisation chronologique détaillée sur `admin/logs.php` (démarrage, exécution des requêtes SQL, nombre de lignes récupérées, clôture) dans le fichier `brevo_admin.log`.
- Harmonisation des messages d'erreur pour afficher explicitement « Erreur SQL » lors d'un échec de requête tout en conservant un rendu Dolibarr.
- Ajout d'un bloc `finally` pour fermer systématiquement la connexion SQL, tracer le statut final et éviter les erreurs 500 silencieuses.

## [1.3.16] - 2025-10-18
### Added
- Ajout d'un logger dédié `brevo_admin.log` pour tracer chaque accès et les erreurs SQL de la page `admin/logs.php` avec un identifiant de requête.
### Changed
- Encadrement de la page d'administration des journaux dans un bloc `try/catch` pour consigner les exceptions et afficher un message d'erreur contrôlé au lieu d'une erreur 500.

## [1.3.15] - 2025-10-17
### Fixed
- Empêche l'enregistrement d'une clé API Brevo invalide en vérifiant la connexion avant de sauvegarder et en journalisant l'appel.
- Clarifie le message d'échec du test de connexion avec la cause détaillée et le code HTTP.

## [1.3.14] - 2025-10-16
### Fixed
- Restaure la constante `MAIN_MODULE_BREVOINTEGRATION` dans le descripteur afin de permettre la désactivation du module depuis l'administration Dolibarr.

## [1.3.13] - 2025-10-15
### Fixed
- Ajout d'un helper de sécurité dédié pour charger automatiquement les bibliothèques Dolibarr et fournir un repli CSRF afin d'éviter l'erreur fatale « Call to undefined function checkToken() ».
- Sécurisation des formulaires d'administration et des hooks Brevo en utilisant le nouveau helper `brevointegration_check_token()` et des jetons générés de manière résiliente.
- Prévention d'un blocage de l'interface Dolibarr (désactivation du module, sauvegarde de clé ou consultation des journaux) lorsque les fonctions de jeton natives sont indisponibles.

## [1.3.12] - 2025-10-14
### Fixed
- Encadrement systématique des dates SQL via un helper dédié pour éviter les erreurs de syntaxe lorsque `DoliDB::idate()` ne renvoie pas de quotes (MySQL/MariaDB).
- Sécurisation des enregistrements `BrevoSync` et `BrevoLog` avec le même helper afin de garantir la compatibilité multi-SGBD.

## [1.3.11] - 2025-10-13
### Fixed
- Normalisation défensive du statut de la table `llx_brevo_log` pour éviter l'erreur 500 lorsque la liste des colonnes manquantes est invalide.
- Journalisation explicite des anomalies de schéma sur la page des journaux afin de laisser des traces exploitables dans `dolibarr.log`.

## [1.3.10] - 2025-10-12
### Fixed
- Ajout explicite du chargement de `security.lib.php` pour rétablir la fonction `checkToken()` sur la page de configuration Brevo et dans les hooks.

## [1.3.9] - 2025-10-11
### Added
- Diagnostic write test covering INSERT/UPDATE/DELETE on `llx_brevo_log` to detect permission issues proactively.
- Check ensuring the module resolves `main.inc.php` from the expected Dolibarr root path.

## [1.3.8] - 2025-10-10
### Fixed
- Remplacement du client HTTP Brevo par `BrevoClient` tolérant aux erreurs et ajout d'un service de journalisation non bloquant.
- Séparation de l'enregistrement et du test de la clé API avec gestion CSRF et affichage des erreurs via `setEventMessages()`.
- Stabilisation de la page des journaux (tri/pagination sécurisés, messages d'erreur contrôlés) pour éliminer les erreurs 500.
- Mise à jour des traductions et de la documentation pour refléter la nouvelle constante `BREVO_APIKEY` et les actions disponibles.

## [1.3.7] - 2025-10-09
### Fixed
- Remplacement du chargement heuristique de `main.inc.php` sur la page des journaux par un `require` déterministe pour éviter les erreurs 500 en environnement proxifié.
- Encadrement de l'enregistrement de la clé API dans un bloc `try/catch` avec message d'erreur localisé pour exposer proprement les échecs inattendus.

## [1.3.6] - 2025-10-08
### Fixed
- Empêche l'erreur fatale lors de la validation de la clé API lorsque l'extension PHP JSON est absente et affiche un message explicite.
- Ajout d'un test unitaire garantissant le retour de l'erreur "Missing PHP JSON extension" et traduction associée.

## [1.3.5] - 2025-10-07
### Fixed
- Empêche les erreurs 500 lors de l'enregistrement de la clé API Brevo en encapsulant les appels HTTP dans une gestion d'exceptions robuste.
- Ajout d'un test unitaire garantissant le retour d'un message d'erreur contrôlé quand la validation de clé déclenche une exception inattendue.

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

