<?php
/*
	Redaxo-Addon NewsSync
	Basisklasse
	v1.0.5
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


namespace IcemanFx\NewsSync;

use rex_list;


class newssync
{
	
    public function __construct()
    {
    }

}


class RexList extends rex_list
{	 
    public function get()
    {
        return parent::get().$this->getPagination();
    }
}
?>