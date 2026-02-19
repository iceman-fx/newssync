<?php
/*
	Redaxo-Addon NewsSync
	Installation
	v1.0.10
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */


//Variablen deklarieren
$mypage = $this->getProperty('package');
$error = "";


//Vorgaben vornehmen
if (!$this->hasConfig()):
	rex_config::set('newssync', 'last_sync', '');
	rex_config::set('newssync', 'last_log_cleanup', '');
	rex_config::set('newssync', 'max_news_per_request', 100);
	rex_config::set('newssync', 'rate_limit_per_minute', 20);

	$this->setConfig('config', [
		'host_mode'					=> 0,
		'custom_title'				=> '',
		'client_api_key'			=> '',
		'client_host_url'			=> '',
		'auto_sync_enabled'			=> 1,
		'auto_sync_interval'		=> 3,
		'enable_logging'			=> 1,
		'log_retention_months'		=> 3,
        'editor_class'				=> 'ckeditor',
        'editor_data'				=> 'data-ckeditor-profile="addonsimple"',
		'systemlog'					=> 0,
	]);
endif;


//Datenbank-Einträge vornehmen
rex_sql_table::get(rex::getTable('1940_newssync_news'))
	->ensureColumn(new rex_sql_column('id', 'int(100)', false, null, 'auto_increment'))
	->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
	->ensureColumn(new rex_sql_column('content', 'text'))
	->ensureColumn(new rex_sql_column('type', 'enum("info","warning","success","danger")', false, 'info'))
	->ensureColumn(new rex_sql_column('active', 'tinyint(1)'))
	->ensureColumn(new rex_sql_column('expire_date', 'datetime', true, null))	
	->ensureColumn(new rex_sql_column('client_key', 'text', true, null))	
	
	->ensureGlobalColumns()
	->setPrimaryKey('id')
	
	->ensureIndex(new rex_sql_index('active_updatedate', ['active', 'updatedate'], rex_sql_index::INDEX))
	->ensureIndex(new rex_sql_index('updatedate', ['updatedate'], rex_sql_index::INDEX))
	->ensureIndex(new rex_sql_index('expire_date', ['expire_date'], rex_sql_index::INDEX))
	
	->ensure();


rex_sql_table::get(rex::getTable('1940_newssync_keys'))
	->ensureColumn(new rex_sql_column('id', 'int(100)', false, null, 'auto_increment'))
	->ensureColumn(new rex_sql_column('client_name', 'varchar(255)'))
	->ensureColumn(new rex_sql_column('api_key', 'varchar(64)'))
	->ensureColumn(new rex_sql_column('active', 'tinyint(1)', false, 1))
	->ensureColumn(new rex_sql_column('last_used', 'datetime', true, null))	
	->ensureColumn(new rex_sql_column('request_count', 'int(100)', false, 0))	
	
	->ensureGlobalColumns()
	->setPrimaryKey('id')
	
	->ensureIndex(new rex_sql_index('api_key', ['api_key'], rex_sql_index::UNIQUE))
	->ensureIndex(new rex_sql_index('active', ['active'], rex_sql_index::INDEX))

	->ensure();


rex_sql_table::get(rex::getTable('1940_newssync_cache'))
	->ensureColumn(new rex_sql_column('id', 'int(100)', false, null, 'auto_increment'))
	->ensureColumn(new rex_sql_column('news_id', 'int(100)'))
	->ensureColumn(new rex_sql_column('title', 'varchar(255)'))
	->ensureColumn(new rex_sql_column('content', 'text'))
	->ensureColumn(new rex_sql_column('type', 'enum("info","warning","success","danger")', false, 'info'))
	->ensureColumn(new rex_sql_column('expire_date', 'datetime', true, null))	
	->ensureColumn(new rex_sql_column('synced_at', 'datetime', true, null))
	->ensureColumn(new rex_sql_column('state', 'tinyint(1)', false, 0))
	
	->ensureGlobalColumns()
	->setPrimaryKey('id')
	
	->ensureIndex(new rex_sql_index('news_id', ['news_id'], rex_sql_index::UNIQUE))
	->ensureIndex(new rex_sql_index('updatedate', ['updatedate'], rex_sql_index::INDEX))
	->ensureIndex(new rex_sql_index('expire_date', ['expire_date'], rex_sql_index::INDEX))

	->ensure();


rex_sql_table::get(rex::getTable('1940_newssync_logs'))
	->ensureColumn(new rex_sql_column('id', 'int(100)', false, null, 'auto_increment'))
	->ensureColumn(new rex_sql_column('client_key_id', 'int(100)', true, null))
	->ensureColumn(new rex_sql_column('ip_address', 'varchar(45)'))
	->ensureColumn(new rex_sql_column('request_time', 'datetime'))
	->ensureColumn(new rex_sql_column('http_code', 'int(11)'))
	->ensureColumn(new rex_sql_column('news_count', 'int(100)', false, 0))	
	->ensureColumn(new rex_sql_column('request_id', 'varchar(64)', true, null))	
	
	->ensureGlobalColumns()
	->setPrimaryKey('id')
	
	->ensureIndex(new rex_sql_index('client_time', ['client_key_id', 'request_time'], rex_sql_index::INDEX))
	->ensureIndex(new rex_sql_index('request_time', ['request_time'], rex_sql_index::INDEX))
	
	->ensure();
	
	
$sql = rex_sql::factory();
$sql->setQuery('ALTER TABLE `' . rex::getTable('1940_newssync_news') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
$sql->setQuery('ALTER TABLE `' . rex::getTable('1940_newssync_cache') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');
$sql->setQuery('ALTER TABLE `' . rex::getTable('1940_newssync_logs') . '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');