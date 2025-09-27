AGENT: Développeur de module Dolibarr (Dolibarr 21.0.2 / PHP 7.4.33)

Rôle & objectifs

Concevoir, coder, tester et documenter des modules Dolibarr prêts à publier.

À chaque nouveau commit, incrémenter systématiquement la valeur de version du
module (ex. propriété `$this->version` dans le descripteur) et synchroniser les
fichiers annexes (CHANGELOG, scripts SQL d'upgrade, etc.) pour refléter ce
changement.

Jamais d’oubli : commenter le code utilement, générer la doc utilisateur/dev, tenir un CHANGELOG, maintenir les scripts SQL (install/upgrade), synchroniser i18n.
Toujours synchroniser le service `BrevoDatabaseMaintenanceService`, les scripts SQL (`sql/llx_brevo_contactsync.sql`, `sql/updates/*`) et l’onglet Diagnostic dès qu’un changement de schéma est introduit.

Factoriser au maximum, interdire les duplications, éviter les fichiers volumineux (>500 lignes) en scindant logiquement (DAO/Services/UI/Hooks/Triggers).

Performance d’abord : requêtes efficaces, indexes, pagination, caches légers, pas de N+1, pas d’assets lourds inutiles.

Respect strict de l’écosystème Dolibarr (conventions, sécurité, hooks, triggers, droits, GETPOST, DoliDB…).

Arborescence standard du module
htdocs/custom/<module>/
  core/
    modules/mod<Module>.class.php        # Descripteur du module (DolibarrModules)
    triggers/interface_99_mod<module>_<Feature>.class.php
  class/
    <Entity>.class.php                   # DAO (extends CommonObject)
    actions_<module>.class.php           # Hooks UI (HookManager)
    services/
      <Service>.php                      # Logique métier factorisée
  admin/
    setup.php                            # Page config/droits/constantes
  lib/
    <module>_lib.php                     # Fonctions helpers UI (Form, selects…)
  sql/
    llx_<module>__tables.sql             # Install
    updates/<from>_to_<to>.sql           # Upgrades incrémentaux
  langs/
    fr_FR/<module>.lang                  # i18n (FR)
    en_US/<module>.lang                  # i18n (EN)
  tpl/
    <entity>_card.tpl.php                # Templates UI (si besoin)
    <entity>_list.tpl.php
  script/
    cron_<task>.php                      # Tâches CRON si nécessaires
  tests/
    phpunit.xml.dist
    unit/<Subject>Test.php
    integration/<Flow>Test.php
  README.md
  CHANGELOG.md
  composer.json (optionnel pour outils dev)


Conventions & qualité

PSR-12, nommage StudlyCaps pour classes, camelCase pour méthodes/variables.

Une classe = une responsabilité. DAO = persistance (extends CommonObject), Services = métier, UI = pages/templating, Hooks/Triggers = intégration.

Commentaires: en-tête de fichier (but, auteur, licence), docblocks complets (@var, @param, @return, @throws), TODO avec référence issue.

Log : dol_syslog(__METHOD__." message", LOG_DEBUG) pour débogage contrôlé.

Taille: scinder >500 lignes; interdiction de dupliquer >3 lignes identiques non triviales → factoriser dans une fonction/utilitaire.

i18n: toute chaîne affichée passe par langs->trans('Key'). Mettre à jour toutes les langues.

Front: pas d’assets lourds; pagination par défaut (ex. 25/50 éléments), tri, filtres; respecter le thème Dolibarr (Form, liste standard).

Accessibilité: libellés, attributs for/aria, messages d’erreur clairs.

Sécurité

Entrées : utiliser GETPOST('field','type') avec le bon filtre ('alpha'|'alphanohtml'|'int'|'aZ09'|'restricthtml'|…).

CSRF : token newToken() et vérification sur POST/Actions sensibles.

Droits : toujours vérifier $user->rights->mymodule->... en entrée de page/action/service.

SQL : pas de concat sans escape/typage; préférer méthodes DoliDB, transactions begin/commit/rollback.

Téléversements : extensions/tailles contrôlées, stockage dans répertoires Dolibarr, pas d’exécution.

XSS : échapper à l’affichage (dol_escape_htmltag / dol_htmlentitiesbr selon contexte).

Logs : pas de secrets en clair (API keys, tokens). Utiliser constantes/masquage.

Base de données & performance

Schéma : préfixe llx_<module>_.... Clés primaires INT AUTO_INCREMENT, clés étrangères nommées.

Indexes : pour chaque colonne filtrée/triée fréquemment, créer un index.

Requêtes : sélectionner colonnes nécessaires, pas de SELECT *, éviter N+1 (JOIN, préchargement).

Pagination : LIMIT via $db->plimit($limit, $offset).

Transactions pour opérations multi-étapes.

Caches : caches statiques en mémoire de requêtes répétées dans la même exécution, invalidation claire.

Tâches lourdes : CRON en arrière-plan (fichiers dans script/), progress/log, timeouts.

Hooks & Triggers (intégration)

Hooks UI via actions_<module>.class.php (doActions, printFieldListOption, etc.), limiter les effets de bord, retourner les codes attendus.

Triggers dans core/triggers/ pour réagir à événements (BILL_VALIDATE, THIRDPARTY_CREATE, etc.). Idempotence, performances, logs clairs.

Droits & configuration

Définir des constantes (ex. MYMODULE_FEATURE_ON) via page admin/setup.php.

Définir droits fins (lecture/écriture/export/suppression). Grille claire par rôle.

Respecter $conf->global pour les toggles globaux.

Contexte & compatibilité

Dolibarr cible : 21.0.2

PHP cible : 7.4.33

Base de données : MariaDB/MySQL

Contrainte forte : interdiction d’utiliser des fonctionnalités PHP >7.4.

Interdits (PHP 8+)

❌ match (remplacer par switch)

❌ enum (remplacer par constantes de classe)

❌ union types (function foo(int|string $x))

❌ readonly properties

❌ constructor property promotion (public function __construct(private $x) {})

❌ str_contains, str_starts_with, str_ends_with (implémenter polyfills)

❌ named arguments (foo(bar: "test"))

Conseillé

✅ switch/case au lieu de match

✅ Constantes de classe (const STATUS_DRAFT = 0;) au lieu de enum

✅ Fonctions utilitaires pour simuler str_contains & co

✅ Docblocks clairs pour compenser l’absence de typing moderne

✅ $this->property = $property; dans le constructeur classique

Règles de développement spécifiques PHP 7.4

Types scalaires & retour : les utiliser si possible, mais sans union.

Nullable autorisé (?int, ?string), car disponible depuis PHP 7.1.

Classes anonymes possibles, mais à éviter → préférer une classe nommée factorisée.

Opérateur null coalesce ?? utilisable.

Fonctions fléchées (fn($x) => $x+1) disponibles mais à utiliser avec parcimonie pour lisibilité.

Strict_types : toujours declare(strict_types=1); en tête de fichier.

Arborescence (identique, mais allégée pour PHP 7.4)
htdocs/custom/<module>/
  core/modules/mod<Module>.class.php
  core/triggers/interface_99_mod<module>_<Feature>.class.php
  class/<Entity>.class.php
  class/services/<Service>.php
  admin/setup.php
  lib/<module>_lib.php
  sql/llx_<module>__tables.sql
  sql/updates/<from>_to_<to>.sql
  langs/fr_FR/<module>.lang
  langs/en_US/<module>.lang
  tpl/<entity>_card.tpl.php
  tpl/<entity>_list.tpl.php
  script/cron_<task>.php
  tests/unit/<Entity>Test.php
  tests/integration/<Flow>Test.php
  README.md
  CHANGELOG.md

Exemple de polyfills (compat PHP 7.4)
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}

if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}

if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
    }
}


👉 L’agent doit les générer automatiquement s’il en a besoin.

DAO Exemple adapté PHP 7.4
<?php
declare(strict_types=1);

/**
 * @package   mymodule
 * @author    ACME
 * @license   GPL-3.0-or-later
 * @brief     DAO MyEntity (Dolibarr 21 / PHP 7.4)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class MyEntity extends CommonObject
{
    public $element       = 'myentity';
    public $table_element = 'mymodule_myentity';

    /** @var int */
    public $id;
    /** @var string */
    public $ref;
    /** @var string */
    public $label;
    /** @var int */
    public $status = 0;

    /**
     * Create object into DB
     *
     * @param User $user
     * @return int <0 if KO, id of created object if OK
     */
    public function create(User $user)
    {
        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (ref, label, status) VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= "'".$this->db->escape($this->label)."',";
        $sql .= (int) $this->status;
        $sql .= ")";

        $res = $this->db->query($sql);
        if (!$res) {
            $this->db->rollback();
            $this->error = $this->db->lasterror();
            return -1;
        }

        $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        $this->db->commit();

        return $this->id;
    }
}
