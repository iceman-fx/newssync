<?php
/*
	Redaxo-Addon NewsSync
	API-Klasse
	v1.0.8
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


$addon = rex_addon::get('newssync');
$config = $addon->getConfig('config');

$systemlog 	= (int)@$config['systemlog'];
$isHostmode = (int)@$config['host_mode'];
$logEnabled = (int)@$config['enable_logging'];

// Sicherheitsheader setzen
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Nur JSON-Responses
header('Content-Type: application/json; charset=utf-8');

// Request-ID für Logging generieren
$request_id = uniqid('req_', true);

// Variablen für Logging initialisieren
$http_code = 200;
$client_key_id = null;
$news_count = 0;

// Rate Limiting - Wert aus Config holen
$max_requests_per_minute = (int)rex_config::get('newssync', 'rate_limit_per_minute', 60);

$rate_limit_key = 'newssync_api_' . md5($_SERVER['REMOTE_ADDR']);
$rate_limit = rex_config::get('newssync', $rate_limit_key, 0);
$rate_limit_time = rex_config::get('newssync', $rate_limit_key . '_time', 0);

if (time() - $rate_limit_time > 60) {
    rex_config::set('newssync', $rate_limit_key, 1);
    rex_config::set('newssync', $rate_limit_key . '_time', time());
} else {
    if ($rate_limit > $max_requests_per_minute) {
        $http_code = 429;
        http_response_code($http_code);
        
        if ($logEnabled) {
            $log_sql = rex_sql::factory();
            $log_sql->setTable(rex::getTable('1940_newssync_logs'));
            $log_sql->setValue('client_key_id', null);
            $log_sql->setValue('ip_address', $_SERVER['REMOTE_ADDR']);
            $log_sql->setDateTimeValue('request_time', time());
            $log_sql->setValue('http_code', $http_code);
            $log_sql->setValue('news_count', 0);
            $log_sql->setValue('request_id', $request_id);
            try {
                $log_sql->insert();
            } catch (Exception $e) {}
        }
        
        echo json_encode([
            'error' => 'Rate limit exceeded',
            'retry_after' => 60 - (time() - $rate_limit_time)
        ]);
        exit;
    }
    rex_config::set('newssync', $rate_limit_key, $rate_limit + 1);
}


// Host-Modus prüfen
if (!$isHostmode) {
    $http_code = 403;
    http_response_code($http_code);
    
    if ($logEnabled) {
        $log_sql = rex_sql::factory();
        $log_sql->setTable(rex::getTable('1940_newssync_logs'));
        $log_sql->setValue('client_key_id', null);
        $log_sql->setValue('ip_address', $_SERVER['REMOTE_ADDR']);
        $log_sql->setDateTimeValue('request_time', time());
        $log_sql->setValue('http_code', $http_code);
        $log_sql->setValue('news_count', 0);
        $log_sql->setValue('request_id', $request_id);
        try {
            $log_sql->insert();
        } catch (Exception $e) {}
    }
    
    echo json_encode(['error' => 'API not available']);
    exit;
}

// API-Key aus Header oder Query-Parameter (Header bevorzugt)
$api_key = '';
if (isset($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_GET['api_key'])) {
    $api_key = $_GET['api_key'];
} elseif (isset($_POST['api_key'])) {
    $api_key = $_POST['api_key'];
}

// API-Key Validierung
if (empty($api_key) || !preg_match('/^[a-f0-9]{64}$/i', $api_key)) {
    $http_code = 401;
    http_response_code($http_code);
    
    if ($logEnabled) {
        $log_sql = rex_sql::factory();
        $log_sql->setTable(rex::getTable('1940_newssync_logs'));
        $log_sql->setValue('client_key_id', null);
        $log_sql->setValue('ip_address', $_SERVER['REMOTE_ADDR']);
        $log_sql->setDateTimeValue('request_time', time());
        $log_sql->setValue('http_code', $http_code);
        $log_sql->setValue('news_count', 0);
        $log_sql->setValue('request_id', $request_id);
        try {
            $log_sql->insert();
        } catch (Exception $e) {}
    }
    
    echo json_encode(['error' => 'Invalid API key format']);
    exit;
}

// Timing-Attack-Schutz durch Prepared Statement
$sql = rex_sql::factory();
$sql->setQuery(
    'SELECT id, client_name FROM ' . rex::getTable('1940_newssync_keys') . ' 
    WHERE api_key = ? AND active = 1 LIMIT 1',
    [$api_key]
);

if ($sql->getRows() === 0) {
    usleep(rand(100000, 300000));
    $http_code = 401;
    http_response_code($http_code);
    
    if ($logEnabled) {
        $log_sql = rex_sql::factory();
        $log_sql->setTable(rex::getTable('1940_newssync_logs'));
        $log_sql->setValue('client_key_id', null);
        $log_sql->setValue('ip_address', $_SERVER['REMOTE_ADDR']);
        $log_sql->setDateTimeValue('request_time', time());
        $log_sql->setValue('http_code', $http_code);
        $log_sql->setValue('news_count', 0);
        $log_sql->setValue('request_id', $request_id);
        try {
            $log_sql->insert();
        } catch (Exception $e) {}
    }
    
    echo json_encode(['error' => 'Invalid API key']);
    exit;
}

$client_key_id = (int)$sql->getValue('id');
$client_name = $sql->getValue('client_name');

// last_used und request_count aktualisieren
$update_sql = rex_sql::factory();
$update_sql->setTable(rex::getTable('1940_newssync_keys'));
$update_sql->setWhere(['id' => $client_key_id]);
$update_sql->setDateTimeValue('last_used', time());
$update_sql->setRawValue('request_count', 'request_count + 1');
$update_sql->update();

// Last-Update Parameter validieren
$last_update = $_GET['last_update'] ?? '';
if (!empty($last_update)) {
    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $last_update);
    if (!$datetime || $datetime->format('Y-m-d H:i:s') !== $last_update) {
        $http_code = 400;
        http_response_code($http_code);
        
        if ($logEnabled) {
            $log_sql = rex_sql::factory();
            $log_sql->setTable(rex::getTable('1940_newssync_logs'));
            $log_sql->setValue('client_key_id', $client_key_id);
            $log_sql->setValue('ip_address', $_SERVER['REMOTE_ADDR']);
            $log_sql->setDateTimeValue('request_time', time());
            $log_sql->setValue('http_code', $http_code);
            $log_sql->setValue('news_count', 0);
            $log_sql->setValue('request_id', $request_id);
            try {
                $log_sql->insert();
            } catch (Exception $e) {}
        }
        
        echo json_encode(['error' => 'Invalid date format']);
        exit;
    }
    
    if ($datetime > new DateTime()) {
        $http_code = 400;
        http_response_code($http_code);
        
        if ($logEnabled) {
            $log_sql = rex_sql::factory();
            $log_sql->setTable(rex::getTable('1940_newssync_logs'));
            $log_sql->setValue('client_key_id', $client_key_id);
            $log_sql->setValue('ip_address', $_SERVER['REMOTE_ADDR']);
            $log_sql->setDateTimeValue('request_time', time());
            $log_sql->setValue('http_code', $http_code);
            $log_sql->setValue('news_count', 0);
            $log_sql->setValue('request_id', $request_id);
            try {
                $log_sql->insert();
            } catch (Exception $e) {}
        }
        
        echo json_encode(['error' => 'Date cannot be in future']);
        exit;
    }
}

// FIX: Korrekte Ablaufdatum-Prüfung
// News sollten NUR ausgeschlossen werden, wenn expire_date BEREITS ABGELAUFEN ist
// News mit zukünftigem Ablaufdatum müssen INKLUDIERT werden!
$query = 'SELECT id, title, content, type, expire_date, updatedate, client_key
    FROM ' . rex::getTable('1940_newssync_news') . ' 
    WHERE active = 1 
    AND (
        expire_date IS NULL 
        OR expire_date = "0000-00-00 00:00:00" 
        OR expire_date >= NOW()
    )';

$params = [];

// Nur wenn last_update gesetzt ist, filtern wir nach Datum (sonst FULL-SYNC)
if (!empty($last_update)) {
    $query .= ' AND updatedate > ?';
    $params[] = $last_update;
}

// Maximale Anzahl News pro Request aus Config holen (Host bestimmt!)
$max_news_per_request = (int)rex_config::get('newssync', 'max_news_per_request', 1000);

$query .= ' ORDER BY updatedate DESC LIMIT ' . (int)$max_news_per_request;

$news_sql = rex_sql::factory();
$news_sql->setQuery($query, $params);

$news = [];
$filtered_out = 0;
$debug_filter_reasons = [];


/*
if ($systemlog):
	rex_logger::factory()->log(
		'debug',
		sprintf(
			'API Query lieferte %d News für Client-ID: %d',
			$news_sql->getRows(),
			$client_key_id
		),
		[],
		'newssync_api'
	);
endif;
*/


foreach ($news_sql as $row) {
    $client_key_str = $row->getValue('client_key');
    $news_id = $row->getValue('id');
    $news_title = $row->getValue('title');


// DEBUG-Ausgabe für News
/*
if (!$is_for_client) {
    $expire_date = $row->getValue('expire_date');
    
    // Nur News mit Ablaufdatum loggen (die sind interessant)
    if (!empty($expire_date) && $expire_date != '0000-00-00 00:00:00') {
		
		if ($systemlog):
			rex_logger::factory()->log(
				'warning',
				sprintf(
					'?? News mit Ablaufdatum wurde gefiltert! | ID: %d | Titel: "%s" | Expire: %s | Client-Key in DB: "%s" | Anfragender Client-ID: %d',
					$row->getValue('id'),
					substr($row->getValue('title'), 0, 40),
					$expire_date,
					$client_key_str === null ? 'NULL' : ($client_key_str === '' ? 'LEER' : $client_key_str),
					$client_key_id
				),
				[],
				'newssync_api'
			);
		endif;
    }
    
    $filtered_out++;
    $debug_filter_reasons[] = 'News #' . $news_id . ' (' . $news_title . '): ' . $filter_reason;
    continue;
}
*/

    
    // Prüfen ob diese News für diesen Client bestimmt ist
    $is_for_client = false;
    $filter_reason = '';
    
    // WICHTIG: Alle möglichen "leer" Zustände abfangen UND Legacy "0"
    if ($client_key_str === null || $client_key_str === '' || trim($client_key_str) === '' || $client_key_str === '0') {
        // NULL, leer oder "0" = für alle Clients
        $is_for_client = true;
        $filter_reason = 'Alle Clients (leer/0)';
    } else {
        // Kommaseparierte Liste prüfen
        $allowed_client_ids = array_map('intval', explode(',', trim($client_key_str)));
        if (in_array($client_key_id, $allowed_client_ids)) {
            $is_for_client = true;
            $filter_reason = 'Client in Liste: ' . $client_key_str;
        } else {
            $filter_reason = 'Client NICHT in Liste: ' . $client_key_str . ' (gesucht: ' . $client_key_id . ')';
        }
    }
    
    if (!$is_for_client) {
        $filtered_out++;
        $debug_filter_reasons[] = 'News #' . $news_id . ' (' . $news_title . '): ' . $filter_reason;
        continue;
    }
    
    $debug_filter_reasons[] = 'News #' . $news_id . ' (' . $news_title . '): ? ' . $filter_reason;
    
	// Content filtern + ersetze Redaxo-Links redaxo://x
	$content = $row->getValue('content');
		$clid	 = rex_clang::getCurrentId();
		$content = preg_replace_callback('@redaxo://(\d+)(?:-(\d+))?/?@i', function (array $matches) use (&$clid){
			return rex_getUrl((int) $matches[1], (int) ($matches[2] ?? $clid));
		}, $content);
		$content = newssync_helper::sanitizeHTML($content);
	
	// News für Rückgabe vorbereiten
    $news[] = [
        'id' => (int)$row->getValue('id'),
        'title' => htmlspecialchars($row->getValue('title'), ENT_QUOTES, 'UTF-8'),
        'content' => $content,
        'type' => in_array($row->getValue('type'), ['info', 'success', 'warning', 'danger']) 
            ? $row->getValue('type') 
            : 'info',
        'expire_date' => $row->getValue('expire_date'),
        'updatedate' => $row->getValue('updatedate')
    ];
}

$news_count = count($news);

//var_dump($news);


// ALLE aktiven News-IDs für Sync-Abgleich (für Löschung auf Client)
// FIX: Gleiche Ablaufdatum-Logik wie oben
$all_active_ids_query = 'SELECT id, client_key FROM ' . rex::getTable('1940_newssync_news') . ' 
    WHERE active = 1 
    AND (
        expire_date IS NULL 
        OR expire_date = "0000-00-00 00:00:00" 
        OR expire_date >= NOW()
    )';

$ids_sql = rex_sql::factory();
$ids_sql->setQuery($all_active_ids_query);

$all_active_ids = [];
foreach ($ids_sql as $row) {
    $client_key_str = $row->getValue('client_key');
    
    $is_for_client = false;
    
    if ($client_key_str === null || $client_key_str === '' || trim($client_key_str) === '' || $client_key_str === '0') {
        $is_for_client = true;
    } else {
        $allowed_client_ids = array_map('intval', explode(',', trim($client_key_str)));
        if (in_array($client_key_id, $allowed_client_ids)) {
            $is_for_client = true;
        }
    }
    
    if ($is_for_client) {
        $all_active_ids[] = (int)$row->getValue('id');
    }
}


// Custom Title vom Host holen
$custom_title = @$config['custom_title'];

// Response mit allen aktiven IDs
$response_data = [
    'success' => true,
    'count' => $news_count,
    'news' => $news,
    'all_active_ids' => $all_active_ids,
	'custom_title' => $custom_title,
    'timestamp' => date('Y-m-d H:i:s'),
    'client' => $client_name
];

$json_response = json_encode($response_data, JSON_UNESCAPED_UNICODE);

// Signatur mit API-Key als Secret erstellen
$signature = hash_hmac('sha256', $json_response, $api_key);
header('X-Signature: ' . $signature);

// Request-ID für Logging
header('X-Request-ID: ' . $request_id);


// Erfolgreiche Request in Datenbank loggen
if ($logEnabled) {
    $log_sql = rex_sql::factory();
    $log_sql->setTable(rex::getTable('1940_newssync_logs'));
    $log_sql->setValue('client_key_id', $client_key_id);
    $log_sql->setValue('ip_address', $_SERVER['REMOTE_ADDR']);
    $log_sql->setDateTimeValue('request_time', time());
    $log_sql->setValue('http_code', 200);
    $log_sql->setValue('news_count', $news_count);
    $log_sql->setValue('request_id', $request_id);
    
    try {
        $log_sql->insert();
    } catch (Exception $e) {
		//if ($systemlog):
			rex_logger::factory()->log(
				'error',
				'News API: Logging-Fehler: ' . $e->getMessage(),
				[],
				'newssync_api'
			);
		//endif;
    }
}

// Rex Logger
if ($systemlog):
	rex_logger::factory()->log(
		'info',
		sprintf(
			'News API: Client "%s" (ID: %d) abgerufen, %d News, Request-ID: %s',
			$client_name,
			$client_key_id,
			$news_count,
			$request_id
		),
		[],
		'newssync_api'
	);
endif;

echo $json_response;