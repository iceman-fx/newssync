<?php
/*
	Redaxo-Addon NewsSync
	DeInstallation
	v1.0.6
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


$sql = rex_sql::factory();
$sql->setQuery("DROP TABLE IF EXISTS ".rex::getTable('1940_newssync_news'));
$sql->setQuery("DROP TABLE IF EXISTS ".rex::getTable('1940_newssync_keys'));
$sql->setQuery("DROP TABLE IF EXISTS ".rex::getTable('1940_newssync_cache'));
$sql->setQuery("DROP TABLE IF EXISTS ".rex::getTable('1940_newssync_logs'));


// Konfiguration entfernen
rex_config::removeNamespace('newssync');