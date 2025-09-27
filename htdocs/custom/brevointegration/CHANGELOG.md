# Changelog

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
