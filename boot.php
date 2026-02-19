<?php
/*
	Redaxo-Addon NewsSync
	Boot (weitere Konfigurationen & Einbindung)
	v1.0.7
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


use IcemanFx\NewsSync\RexList;
rex_list::setFactoryClass(RexList::class);


//Variablen deklarieren
$mypage = $this->getProperty('package');


//Userrechte prüfen
$isAdmin = ( is_object(rex::getUser()) AND (rex::getUser()->hasPerm($mypage.'[admin]') OR rex::getUser()->isAdmin()) ) ? true : false;


//Addon Einstellungen
$config 	= $this->getConfig('config');			                                    //Addon-Konfig einladen
$isHostmode = (int)@$config['host_mode'];
	

// API-Endpoint registrieren
if (rex_request('newssync_api', 'int') == 1) {
    require_once rex_path::addon('newssync', 'lib/api.php');
    exit;
}

// Menüpunkte dynamisch ein-/ausblenden basierend auf Modus + Host-Indikator in Navigation
if (rex::isBackend()) {
    rex_extension::register('PAGES_PREPARED', function(rex_extension_point $ep) use ($config) {
        $host_mode = (int)@$config['host_mode'];
        $custom_title = @$config['custom_title'];
        
        // Addon-Seite holen
        $addon_page = rex_be_controller::getPageObject('newssync');
        
        if ($addon_page) {
            // Custom Title setzen + Host-Indikator
            $display_title = !empty($custom_title) ? $custom_title : $this->i18n('a1940_defaultTitle');
            if ($host_mode) {
                $display_title .= ' '.$this->i18n('a1940_defaultTitle_ishost');
            }
            $addon_page->setTitle($display_title);
            
            // Host-Seite: Nur sichtbar im Host-Modus
            if ($host_subpage = $addon_page->getSubpage('host')) {
                $host_subpage->setHidden(!$host_mode);
            }
            
            // Client-Seite: Nur sichtbar im Client-Modus
            if ($client_subpage = $addon_page->getSubpage('client')) {
                $client_subpage->setHidden($host_mode);
            }
            
            // API-Keys: Nur im Host-Modus sichtbar
            if ($api_keys_subpage = $addon_page->getSubpage('api_keys')) {
                $api_keys_subpage->setHidden(!$host_mode);
            }
            
            // API-Logs: Nur im Host-Modus sichtbar
            if ($api_logs_subpage = $addon_page->getSubpage('api_logs')) {
                $api_logs_subpage->setHidden(!$host_mode);
            }
        }
    });
}

// Automatische Log-Bereinigung (täglich, nur im Host-Modus)
if (rex::isBackend() && $isHostmode) {
    rex_extension::register('PACKAGES_INCLUDED', function() {
		$config = $this->getConfig('config');
        // (int)@$config[''];
		
        // Prüfen ob Logging aktiviert ist
        $enable_logging = (int)@$config['enable_logging'];
        
        if (!$enable_logging) {
            return; // Logging deaktiviert, nichts zu tun
        }
        
        // Prüfen ob heute schon bereinigt wurde
        $last_cleanup = rex_config::get('newssync', 'last_log_cleanup');
        $today = date('Y-m-d');
        
        if ($last_cleanup === $today) {
            return; // Heute schon bereinigt
        }
        
        // Log-Aufbewahrungsdauer holen (in Monaten)
        $retention_months = (int)@$config['log_retention_months'];
        
        // Alte Logs löschen
        try {
            $sql = rex_sql::factory();
            $sql->setQuery(
                'DELETE FROM ' . rex::getTable('1940_newssync_logs') . ' 
                WHERE request_time < DATE_SUB(NOW(), INTERVAL ? MONTH)',
                [$retention_months]
            );
            
            $deleted_rows = $sql->getRows();
            
            // Cleanup-Zeitstempel speichern
            rex_config::set('newssync', 'last_log_cleanup', $today);
            
            // Nur loggen wenn tatsächlich Logs gelöscht wurden
            if ($deleted_rows > 0) {
                rex_logger::factory()->log(
                    'info',
                    sprintf(
                        'NewsSync: Automatische Log-Bereinigung durchgeführt - %d alte Einträge gelöscht (älter als %d Monate)',
                        $deleted_rows,
                        $retention_months
                    ),
                    [],
                    'newssync'
                );
            }
        } catch (Exception $e) {
            rex_logger::factory()->log(
                'error',
                'NewsSync: Fehler bei automatischer Log-Bereinigung: ' . $e->getMessage(),
                [],
                'newssync'
            );
        }
    });
}

// Zeitbasierter Auto-Sync im Backend (nur für Clients und wenn aktiviert)
if (rex::isBackend() && !$isHostmode) {
    rex_extension::register('PACKAGES_INCLUDED', function() {
		$config = $this->getConfig('config');
		// (int)@$config[''];
		
		$systemlog 	= (int)@$config['systemlog'];
		
        // Prüfen ob Auto-Sync aktiviert ist
        $auto_sync_enabled = (int)@$config['auto_sync_enabled'];
        
        if (!$auto_sync_enabled) {
            return; // Auto-Sync ist deaktiviert
        }
        
        $user = rex::getUser();
        if (!$user) {
            return;
        }
        
        // Konfiguration: Auto-Sync alle X Stunden (Standard: 3 Stunden)
        $sync_interval_hours = (int)@$config['auto_sync_interval'];
        $sync_interval_seconds = $sync_interval_hours * 60 * 60;
        
        // Letzten Sync-Zeitstempel holen
        $last_sync = rex_config::get('newssync', 'last_sync');
        $last_sync_timestamp = $last_sync ? strtotime($last_sync) : 0;
        
        // Zeit seit letztem Sync berechnen
        $time_since_last_sync = time() - $last_sync_timestamp;
        
        // Prüfen ob Sync fällig ist
        if ($time_since_last_sync >= $sync_interval_seconds) {
            $api_key 	= @$config['client_api_key'];
            $host_url 	= @$config['client_host_url'];
            
            if ($api_key && $host_url) {
				if ($systemlog) {
					rex_logger::factory()->log(
						'info', 
						sprintf(
							'Auto-Sync gestartet (letzter Sync vor %d Minuten) von User: %s',
							round($time_since_last_sync / 60),
							$user->getLogin()
						),
						[],
						'newssync'
					);
                }
				
                require_once rex_path::addon('newssync', 'lib/sync.php');
                $result = newssync_perform_sync();
                
                // Flag für Anzeige im Client setzen
                $show_sync_notice_key = 'newssync_show_sync_notice_' . $user->getId();
                $_SESSION[$show_sync_notice_key] = [
                    'success' => $result['success'],
                    'message' => $result['message'],
                    'auto' => true
                ];
                
                // Badge für neue News setzen (wenn erfolgreich)
                if ($result['success']) {
                    $stats = $result['stats'] ?? [];
                    $has_new_content = ($stats['new'] ?? 0) > 0 || ($stats['updated'] ?? 0) > 0;
                    
                    if ($has_new_content) {
                        // Badge für alle User setzen
                        $all_users_sql = rex_sql::factory();
                        $all_users_sql->setQuery('SELECT id FROM ' . rex::getTable('user'));
                        
                        foreach ($all_users_sql as $user_row) {
                            $badge_key = 'newssync_show_badge_' . $user_row->getValue('id');
                            $_SESSION[$badge_key] = true;
                        }
                    }
                }
                
                if (!$result['success']) {
                    rex_logger::factory()->log(
                        'warning',
                        'Auto-Sync fehlgeschlagen: ' . $result['message'],
                        ['user' => $user->getLogin()],
                        'newssync'
                    );
                } else {
					if ($systemlog) {
						rex_logger::factory()->log(
							'info',
							'Auto-Sync erfolgreich: ' . $result['message'],
							$result['stats'] ?? [],
							'newssync'
						);
					}
                }
            }
        }
    });
    
	
    // JavaScript für Badge in Navigation einfügen
    rex_extension::register('OUTPUT_FILTER', function(rex_extension_point $ep) {
        $user = rex::getUser();
        if (!$user) {
            return $ep->getSubject();
        }
        
        $badge_key = 'newssync_show_badge_' . $user->getId();
        $show_badge = isset($_SESSION[$badge_key]) && $_SESSION[$badge_key];
        
        if ($show_badge) {
            $content = $ep->getSubject();
            
            // JavaScript zum Hinzufügen des Badges
            $badge_script = '<script>
jQuery(function($) {
    // Badge zum NewsSync Menüpunkt hinzufügen
    var newsyncLink = $(\'a[href*="page=newssync"]\').first();
    if (newsyncLink.length && !newsyncLink.find(".newssync-badge").length) {
        newsyncLink.append(\'<span class="newssync-badge" style="display:inline-block; width:8px; height:8px; background:#e74c3c; border-radius:50%; margin:8px 0px 0px 5px; vertical-align:middle; float:right;"></span>\').children("i").addClass("rex-offline");
    }
});
</script>';
            
            // Vor </body> einfügen
            $content = str_replace('</body>', $badge_script . '</body>', $content);
            
            return $content;
        }
        
        return $ep->getSubject();
    });
}