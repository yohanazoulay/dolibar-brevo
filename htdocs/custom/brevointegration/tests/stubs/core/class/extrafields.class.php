<?php
declare(strict_types=1);

class ExtraFields
{
    /** @var array<string,array<string,string>> */
    public static $labels = array();

    /**
     * @param DoliDB|null $db
     */
    public function __construct($db = null)
    {
    }

    /**
     * @param string $elementtype
     * @param bool   $withlabels
     * @return array<string,string>
     */
    public function fetch_name_optionals_label($elementtype, $withlabels = false)
    {
        if (isset(self::$labels[$elementtype])) {
            return self::$labels[$elementtype];
        }

        return array();
    }
}
