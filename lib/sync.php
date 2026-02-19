<?php
/*
	Redaxo-Addon NewsSync
	Client-Sync-Klasse
	v1.0.8
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


function newssync_perform_sync() {
	$config = rex_config::get('newssync', 'config');
	
	$systemlog 	= (int)@$config['systemlog'];
	
	
	$api_key 	= @$config['client_api_key'];
	$host_url 	= @$config['client_host_url'];	
    
    if (empty($api_key) || empty($host_url)) {
        $error_msg = 'API-Key oder Host-URL nicht konfiguriert';
        rex_config::set('newssync', 'last_sync_error', $error_msg);
        return [
            'success' => false,
            'message' => $error_msg
        ];
    }
    
    // API-Key Format validieren
    if (!preg_match('/^[a-f0-9]{64}$/i', $api_key)) {
        $error_msg = 'Ungültiges API-Key Format';
        rex_config::set('newssync', 'last_sync_error', $error_msg);
        return [
            'success' => false,
            'message' => $error_msg
        ];
    }
    
    // Host-URL validieren
    $parsed_url = parse_url($host_url);
    if (!$parsed_url || !isset($parsed_url['scheme']) || !isset($parsed_url['host'])) {
        $error_msg = 'Ungültige Host-URL';
        rex_config::set('newssync', 'last_sync_error', $error_msg);
        return [
            'success' => false,
            'message' => $error_msg
        ];
    }
    
    // Nur HTTPS erlauben (außer localhost für Development)
    if ($parsed_url['scheme'] !== 'https' && $parsed_url['host'] !== 'localhost' && $parsed_url['host'] !== '127.0.0.1') {
        $error_msg = 'Nur HTTPS-Verbindungen erlaubt (außer localhost)';
        rex_config::set('newssync', 'last_sync_error', $error_msg);
        return [
            'success' => false,
            'message' => $error_msg
        ];
    }
    
    // PRÜFEN ob lokale Cache-Tabelle leer ist -> KOMPLETT-SYNC notwendig
    $check_sql = rex_sql::factory();
    $check_sql->setQuery('SELECT COUNT(*) as count FROM ' . rex::getTable('1940_newssync_cache'));
    $local_news_count = (int)$check_sql->getValue('count');
    
    $force_full_sync = ($local_news_count === 0);
    
    // Letztes Update-Datum holen und validieren
    $last_sync = rex_config::get('newssync', 'last_sync', '');
    
    // Bei Komplett-Sync ignorieren wir das last_sync Datum
    if ($force_full_sync) {
        $last_sync = '';
		
		if ($systemlog):
			rex_logger::factory()->log(
				'info',
				'News Sync: Lokale Cache-Tabelle ist leer - führe Komplett-Synchronisation durch',
				[],
				'newssync'
			);
		endif;
		
    } elseif (!empty($last_sync)) {
        $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $last_sync);
        if (!$datetime) {
            $last_sync = ''; // Bei ungültigem Format ignorieren
        }
    }
    
    // URL zusammenbauen - API-Key wird NUR im Header übertragen
    $url = rtrim($host_url, '/') . '/index.php?newssync_api=1';
    
    // WICHTIG: Bei Full-Sync KEINEN last_update Parameter senden!
    if (!empty($last_sync) && !$force_full_sync) {
        $url .= '&last_update=' . urlencode($last_sync);
    }
    
    // cURL mit strengen Sicherheitseinstellungen
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false, // Keine Redirects folgen
        CURLOPT_MAXREDIRS => 0,
        CURLOPT_SSL_VERIFYPEER => true, // SSL-Zertifikat prüfen
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_HTTPHEADER => [
            'X-API-Key: ' . $api_key, // API-Key SICHER im Header
            'Accept: application/json',
            'User-Agent: RedaxoNewsSync/1.0'
        ],
        CURLOPT_HEADER => true, // Header mit zurückgeben für Signatur-Check
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $error = curl_error($ch);
    $error_no = curl_errno($ch);
    curl_close($ch);
    
    if ($error) {
        $error_msg = 'Verbindungsfehler: ' . $error;
        rex_config::set('newssync', 'last_sync_error', $error_msg);
		
		//if ($systemlog):
			rex_logger::factory()->log(
				'error',
				'News Sync Verbindungsfehler: ' . $error . ' (Code: ' . $error_no . ')',
				[],
				'newssync'
			);
		//endif;
		
        return [
            'success' => false,
            'message' => $error_msg
        ];
    }
    
    // Response in Header und Body aufteilen
    $header = substr($response, 0, $header_size);
    $body = substr($response, $header_size);
    
    // HTTP-Fehler behandeln
    if ($http_code !== 200) {
        $error_messages = [
            401 => 'Authentifizierung fehlgeschlagen (ungültiger API-Key)',
            403 => 'Zugriff verweigert',
            429 => 'Zu viele Anfragen (Rate Limit erreicht)',
            500 => 'Server-Fehler auf Host-System',
            503 => 'Host-System nicht verfügbar'
        ];
        
        $message = $error_messages[$http_code] ?? 'API-Fehler';
        $error_msg = $message . ' (HTTP ' . $http_code . ')';
        
        rex_config::set('newssync', 'last_sync_error', $error_msg);
		
		//if ($systemlog):
			rex_logger::factory()->log(
				'warning',
				'News Sync HTTP-Fehler: ' . $http_code . ' - ' . $message,
				[],
				'newssync'
			);
		//endif;
        
        return [
            'success' => false,
            'message' => $error_msg
        ];
    }
    
    // JSON dekodieren
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
		//if ($systemlog):
			rex_logger::factory()->log(
				'error',
				'News Sync JSON-Fehler: ' . json_last_error_msg(),
				[],
				'newssync'
			);
		//endif;
		
        return [
            'success' => false,
            'message' => 'Ungültige JSON-Antwort: ' . json_last_error_msg()
        ];
    }
    
    if (!$data || !isset($data['success']) || !$data['success']) {
        return [
            'success' => false,
            'message' => 'Ungültige API-Antwort: ' . ($data['error'] ?? 'Unbekannter Fehler')
        ];
    }
    
    // HMAC-Signatur prüfen (Integritätsprüfung)
    if (preg_match('/X-Signature:\s*([a-f0-9]+)/i', $header, $matches)) {
        $received_signature = $matches[1];
        $expected_signature = hash_hmac('sha256', $body, $api_key);
        
        if (!hash_equals($expected_signature, $received_signature)) {
			//if ($systemlog):
				rex_logger::factory()->log(
					'critical',
					'News Sync: Signatur-Prüfung fehlgeschlagen! Möglicher MITM-Angriff!',
					['host' => $host_url],
					'newssync'
				);
			//endif;
			
            return [
                'success' => false,
                'message' => 'Sicherheitsfehler: Ungültige Signatur (Datenintegrität nicht gewährleistet)'
            ];
        }
    } else {
        // Warnung wenn keine Signatur vorhanden (alte API-Version?)
		//if ($systemlog):
			rex_logger::factory()->log(
				'warning',
				'News Sync: Keine Signatur in API-Response gefunden',
				[],
				'newssync'
			);
		//endif;
    }
	
    
	// Custom Title vom Host übernehmen (wenn vorhanden)
    if (isset($data['custom_title'])) {
        $custom_title = htmlspecialchars(trim($data['custom_title']));
        
        // Custom Title vom Host übernehmen
        $config['custom_title'] = $custom_title;
        
        // Config aktualisieren
        rex_config::set('newssync', 'config', $config);
        
		if ($systemlog):
			rex_logger::factory()->log(
				'debug',
				'News Sync: Custom Title vom Host übernommen: "' . $custom_title . '"',
				[],
				'newssync'
			);
		endif;
    }	
	
    // Prüfen ob all_active_ids vorhanden sind (für Löschung)
    $all_active_ids = isset($data['all_active_ids']) && is_array($data['all_active_ids']) 
        ? $data['all_active_ids'] 
        : [];
    

    // Sicherheits-Check: Maximale Anzahl News pro Sync (sollte Host schon begrenzen)
    // Sehr hohes Limit (10.000) um kaputte/manipulierte Responses zu erkennen
    $security_limit = 10000;

    if (count($data['news']) > $security_limit) {
        $error_msg = 'Sicherheitsfehler: Zu viele News in Response (>' . $security_limit . '). Möglicherweise fehlerhafte Host-Konfiguration.';
        rex_config::set('newssync', 'last_sync_error', $error_msg);
        
		//if ($systemlog):
			rex_logger::factory()->log(
				'error',
				'News Sync Security: ' . $error_msg,
				['count' => count($data['news'])],
				'newssync'
			);
		//endif;
        
        return [
            'success' => false,
            'message' => $error_msg
        ];
    }
	
    
    // News mit strikter Validierung in lokale DB speichern
    $sql = rex_sql::factory();
    $count_new = 0;
    $count_updated = 0;
    $count_skipped = 0;
    $count_deleted = 0;
    
    // DELTA-SYNC: Nur geänderte News verarbeiten
    // Hole alle lokalen News mit ihren Update-Zeitstempeln
    $local_news_map = [];
    if (!$force_full_sync) {
        $local_sql = rex_sql::factory();
        $local_sql->setQuery('SELECT news_id, updatedate FROM ' . rex::getTable('1940_newssync_cache'));
        foreach ($local_sql as $row) {
            $local_news_map[(int)$row->getValue('news_id')] = $row->getValue('updatedate');
        }
    }

    
    foreach ($data['news'] as $news) {
        // Strikte Validierung jedes News-Elements
        if (!isset($news['id'], $news['title'], $news['content'], $news['type'], $news['updatedate'])) {
            $count_skipped++;
			
			if ($systemlog):
				rex_logger::factory()->log(
					'debug',
					'News übersprungen: Fehlende Pflichtfelder',
					[],
					'newssync'
				);
			endif;
			
            continue;
        }
        
        // ID muss numerisch sein
        $news_id = filter_var($news['id'], FILTER_VALIDATE_INT);
        if ($news_id === false || $news_id <= 0) {
            $count_skipped++;
			
			if ($systemlog):
				rex_logger::factory()->log(
					'debug',
					'News übersprungen: Ungültige ID',
					[],
					'newssync'
				);
			endif;
			
            continue;
        }
        
        // Typ muss valide sein
        if (!in_array($news['type'], ['info', 'success', 'warning', 'danger'])) {
            $count_skipped++;
			
			if ($systemlog):
				rex_logger::factory()->log(
					'debug',
					'News übersprungen: Ungültiger Typ',
					['type' => $news['type']],
					'newssync'
				);
			endif;
			
            continue;
        }
        
        // Datum validieren
        $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $news['updatedate']);
        if (!$datetime || $datetime->format('Y-m-d H:i:s') !== $news['updatedate']) {
            $count_skipped++;
			
			if ($systemlog):
				rex_logger::factory()->log(
					'debug',
					'News übersprungen: Ungültiges Datum',
					['date' => $news['updatedate']],
					'newssync'
				);
			endif;
			
            continue;
        }
        
        // Expire-Date validieren (optional)
        $expire_date = null;
        if (isset($news['expire_date']) && !empty($news['expire_date']) && $news['expire_date'] != '0000-00-00 00:00:00') {
            $expire_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $news['expire_date']);
            if ($expire_datetime && $expire_datetime->format('Y-m-d H:i:s') === $news['expire_date']) {
                $expire_date = $news['expire_date'];
            }
        }
        
        // Titel und Content Länge prüfen
        if (strlen($news['title']) > 255 || strlen($news['content']) > 65535) {
            $count_skipped++;
			
			if ($systemlog):
				rex_logger::factory()->log(
					'debug',
					'News übersprungen: Titel oder Content zu lang',
					[],
					'newssync'
				);
			endif;
			
            continue;
        }
        
        //HTML bereinigen
        $clean_title = newssync_helper::textOnly($news['title']);
        $clean_content = newssync_helper::sanitizeHTML($news['content']);
        
        // Prüfen ob News schon existiert
        $check_sql = rex_sql::factory();
        $check_sql->setQuery(
            'SELECT id FROM ' . rex::getTable('1940_newssync_cache') . ' WHERE news_id = ?',
            [$news_id]
        );
        
        try {
            if ($check_sql->getRows() > 0) {
                // DELTA-CHECK: Nur updaten wenn sich etwas geändert hat (außer bei Komplett-Sync)
                if (!$force_full_sync) {
                    $local_updatedate = $local_news_map[$news_id] ?? '';
                    
                    if ($local_updatedate === $news['updatedate']) {
                        // Keine Änderung, überspringen (Performance!)
                        continue;
                    }
                }
                
                // Update mit Prepared Statement
                $update_sql = rex_sql::factory();
                $update_sql->setTable(rex::getTable('1940_newssync_cache'));
                $update_sql->setWhere(['news_id' => $news_id]);
                $update_sql->setValue('title', $clean_title);
                $update_sql->setValue('content', $clean_content);
                $update_sql->setValue('type', $news['type']);
				$update_sql->setValue('state', 2);
                $update_sql->setValue('expire_date', $expire_date);                
                $update_sql->setDateTimeValue('synced_at', time());
				
				$update_sql->setValue('updatedate', $news['updatedate']);
				
                $update_sql->update();
                $count_updated++;
            } else {
                // Insert mit Prepared Statement
                $insert_sql = rex_sql::factory();
                $insert_sql->setTable(rex::getTable('1940_newssync_cache'));
                $insert_sql->setValue('news_id', $news_id);
                $insert_sql->setValue('title', $clean_title);
                $insert_sql->setValue('content', $clean_content);
                $insert_sql->setValue('type', $news['type']);
				$insert_sql->setValue('state', 1);
                $insert_sql->setValue('expire_date', $expire_date);
				$insert_sql->setDateTimeValue('synced_at', time());
				
				$insert_sql->setValue('createdate', $news['updatedate']);
                $insert_sql->setValue('updatedate', $news['updatedate']);
                
                $insert_sql->insert();
                $count_new++;
            }
        } catch (Exception $e) {
			//if ($systemlog):
				rex_logger::factory()->log(
					'error',
					'News Sync DB-Fehler: ' . $e->getMessage(),
					['news_id' => $news_id],
					'newssync'
				);
			//endif;
			
            $count_skipped++;
        }
    }
    
    // Letzten Sync-Zeitpunkt speichern
    rex_config::set('newssync', 'last_sync', date('Y-m-d H:i:s'));
    
    // Gelöschte News auf Client entfernen (nur wenn all_active_ids vorhanden)
    if (!empty($all_active_ids)) {
        // Alle lokalen News holen
        $local_sql = rex_sql::factory();
        $local_sql->setQuery('SELECT news_id FROM ' . rex::getTable('1940_newssync_cache'));
        
        $local_ids = [];
        foreach ($local_sql as $row) {
            $local_ids[] = (int)$row->getValue('news_id');
        }
        
        // IDs finden, die lokal vorhanden sind, aber nicht mehr auf Host aktiv
        $ids_to_delete = array_diff($local_ids, $all_active_ids);
        
        if (!empty($ids_to_delete)) {
            // Korrektes Prepared Statement für IN-Clause mit Array
            $delete_sql = rex_sql::factory();
            $placeholders = implode(',', array_fill(0, count($ids_to_delete), '?'));
            
            // Array-Werte müssen einzeln als Parameter übergeben werden
            $delete_sql->setQuery(
                'DELETE FROM ' . rex::getTable('1940_newssync_cache') . ' WHERE news_id IN (' . $placeholders . ')',
                array_values($ids_to_delete) // array_values() stellt sicher, dass numerische Keys existieren
            );
            
            $count_deleted = count($ids_to_delete);
            
			if ($systemlog):
				rex_logger::factory()->log(
					'info',
					sprintf('News Sync: %d News gelöscht (auf Host nicht mehr aktiv)', $count_deleted),
					['ids' => implode(', ', $ids_to_delete)],
					'newssync'
				);
			endif;
        }
    }
    
    // Statistiken speichern für Dashboard
    rex_config::set('newssync', 'last_sync_stats', [
        'new' => $count_new,
        'updated' => $count_updated,
        'deleted' => $count_deleted,
        'skipped' => $count_skipped,
        'timestamp' => time()
    ]);
    
    // Fehler zurücksetzen bei erfolgreichem Sync
    rex_config::remove('newssync', 'last_sync_error');
    
    // Erfolgs-Logging
	if ($systemlog):
		rex_logger::factory()->log(
			'info',
			sprintf(
				'News Sync erfolgreich%s: %d neu, %d aktualisiert, %d gelöscht, %d übersprungen',
				$force_full_sync ? ' (Komplett-Sync)' : '',
				$count_new,
				$count_updated,
				$count_deleted,
				$count_skipped
			),
			[],
			'newssync'
		);
	endif;
    
    // Erfolgs-Message zusammenbauen
    $message = 'Synchronisation erfolgreich! ';
    if ($force_full_sync) {
        $message = 'Komplett-Synchronisation erfolgreich! ';
    }
    if ($count_new > 0) {
        $message .= $count_new . ' neue News hinzugefügt. ';
    }
    if ($count_updated > 0) {
        $message .= $count_updated . ' News aktualisiert. ';
    }
    if ($count_deleted > 0) {
        $message .= $count_deleted . ' News gelöscht. ';
    }
    if ($count_skipped > 0) {
        $message .= $count_skipped . ' ungültige News übersprungen.';
    }
    if ($count_new == 0 && $count_updated == 0 && $count_deleted == 0 && $count_skipped == 0) {
        $message .= 'Keine neuen oder geänderten News.';
    }
    
    return [
        'success' => true,
        'message' => $message,
        'stats' => [
            'new' => $count_new,
            'updated' => $count_updated,
            'deleted' => $count_deleted,
            'skipped' => $count_skipped
        ]
    ];
}