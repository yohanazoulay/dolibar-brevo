<?php
declare(strict_types=1);

/**
 * @package   brevointegration
 * @author    Meditrust
 * @license   GPL-3.0-or-later
 * @brief     Service to manage Dolibarr to Brevo field mappings.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';

class BrevoFieldMappingService
{
    /** @var DoliDB */
    private $db;

    /** @var Conf */
    private $conf;

    /** @var string */
    public const CONST_NAME = 'MAIN_BREVOINTEGRATION_FIELD_MAPPING';

    /**
     * @param DoliDB $db   Database handler
     * @param Conf   $conf Dolibarr configuration
     */
    public function __construct($db, $conf)
    {
        $this->db = $db;
        $this->conf = $conf;
    }

    /**
     * Retrieve the complete mapping configuration.
     *
     * @return array<string,array<int,array<string,string>>>
     */
    public function getMapping()
    {
        $rawValue = isset($this->conf->global->{self::CONST_NAME}) ? $this->conf->global->{self::CONST_NAME} : '';
        if (!is_string($rawValue) || $rawValue === '') {
            return array();
        }

        $decoded = json_decode($rawValue, true);
        if (!is_array($decoded)) {
            return array();
        }

        return $decoded;
    }

    /**
     * Retrieve mapping for a specific Dolibarr object type.
     *
     * @param string $type contact|thirdparty
     * @return array<int,array<string,string>>
     */
    public function getMappingForType($type)
    {
        $mapping = $this->getMapping();
        if (!isset($mapping[$type]) || !is_array($mapping[$type]) || count($mapping[$type]) === 0) {
            return $this->getDefaultMapping($type);
        }

        return $mapping[$type];
    }

    /**
     * Persist mapping configuration for all types.
     *
     * @param array<string,array<int,array<string,string>>> $mapping
     * @return bool
     */
    public function saveMapping(array $mapping)
    {
        $cleanMapping = $this->sanitizeMapping($mapping);
        $encoded = json_encode($cleanMapping);
        if ($encoded === false) {
            return false;
        }

        $result = dolibarr_set_const(
            $this->db,
            self::CONST_NAME,
            $encoded,
            'chaine',
            0,
            '',
            $this->conf->entity
        );

        if ($result > 0) {
            if (!isset($this->conf->global)) {
                $this->conf->global = new stdClass();
            }
            $this->conf->global->{self::CONST_NAME} = $encoded;
        }

        return $result > 0;
    }

    /**
     * Return available Dolibarr fields for mapping.
     *
     * @param string $type contact|thirdparty
     * @return array<string,array<string,string>>
     */
    public function getAvailableFields($type)
    {
        $fields = array(
            'standard' => array(),
            'extrafields' => array(),
        );

        if ($type === 'contact') {
            $fields['standard'] = $this->translateFields(array(
                'firstname' => array('Firstname', 'Firstname'),
                'lastname' => array('Lastname', 'Lastname'),
                'email' => array('Email', 'Email'),
                'phone_pro' => array('PhonePro', 'Professional phone'),
                'phone_mobile' => array('PhoneMobile', 'Mobile'),
                'phone_perso' => array('PhonePerso', 'Personal phone'),
                'address' => array('Address', 'Address'),
                'zip' => array('Zip', 'Zip code'),
                'town' => array('Town', 'Town'),
                'civility_code' => array('UserTitle', 'Civility'),
            ));

            $element = 'socpeople';
        } elseif ($type === 'thirdparty') {
            $fields['standard'] = $this->translateFields(array(
                'name' => array('CompanyName', 'Company name'),
                'name_alias' => array('AliasNames', 'Alias'),
                'email' => array('Email', 'Email'),
                'phone' => array('Phone', 'Phone'),
                'phone_mobile' => array('PhoneMobile', 'Mobile'),
                'fax' => array('Fax', 'Fax'),
                'address' => array('Address', 'Address'),
                'zip' => array('Zip', 'Zip code'),
                'town' => array('Town', 'Town'),
                'url' => array('Url', 'Website'),
                'code_client' => array('CustomerCode', 'Customer code'),
                'code_fournisseur' => array('SupplierCode', 'Supplier code'),
            ));

            $element = 'societe';
        } else {
            return $fields;
        }

        $extrafields = new ExtraFields($this->db);
        $labels = $extrafields->fetch_name_optionals_label($element, true);
        if (is_array($labels)) {
            foreach ($labels as $key => $label) {
                $fields['extrafields'][$key] = $label;
            }
        }

        return $fields;
    }

    /**
     * Sanitize mapping structure before persistence.
     *
     * @param array<string,array<int,array<string,string>>> $mapping
     * @return array<string,array<int,array<string,string>>>
     */
    private function sanitizeMapping(array $mapping)
    {
        $clean = array(
            'contact' => array(),
            'thirdparty' => array(),
        );

        foreach ($mapping as $type => $entries) {
            if (!isset($clean[$type]) || !is_array($entries)) {
                continue;
            }

            foreach ($entries as $entry) {
                if (!is_array($entry)) {
                    continue;
                }

                $attribute = isset($entry['attribute']) ? strtoupper((string) $entry['attribute']) : '';
                $attribute = preg_replace('/[^A-Z0-9_]/', '_', $attribute);
                $source = isset($entry['source']) ? $entry['source'] : '';
                $field = isset($entry['field']) ? $entry['field'] : '';

                if ($attribute === '' || $source === '' || $field === '') {
                    continue;
                }

                if ($source !== 'standard' && $source !== 'extrafield') {
                    continue;
                }

                $clean[$type][] = array(
                    'attribute' => $attribute,
                    'source' => $source,
                    'field' => $field,
                );
            }
        }

        return $clean;
    }

    /**
     * Default mapping when no configuration has been saved yet.
     *
     * @param string $type contact|thirdparty
     * @return array<int,array<string,string>>
     */
    private function getDefaultMapping($type)
    {
        if ($type === 'contact') {
            return array(
                array('attribute' => 'FIRSTNAME', 'source' => 'standard', 'field' => 'firstname'),
                array('attribute' => 'LASTNAME', 'source' => 'standard', 'field' => 'lastname'),
            );
        }
        if ($type === 'thirdparty') {
            return array(
                array('attribute' => 'LASTNAME', 'source' => 'standard', 'field' => 'name'),
            );
        }

        return array();
    }

    /**
     * @param array<string,array<int,string>> $definitions
     * @return array<string,string>
     */
    private function translateFields(array $definitions)
    {
        global $langs;

        $results = array();
        foreach ($definitions as $field => $definition) {
            $translationKey = $definition[0];
            $fallback = $definition[1];
            $label = $langs->trans($translationKey);
            if ($label === $translationKey) {
                $label = $fallback;
            }
            $results[$field] = $label;
        }

        return $results;
    }
}
