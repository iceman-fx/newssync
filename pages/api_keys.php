<?php
/*
	Redaxo-Addon NewsSync
	Verwaltung: API-Key-Verwaltung (Host)
	v1.0.7
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


$config = $this->getConfig('config');
$isHostmode = (int)@$config['host_mode'];

// Nur für Admins
if (!rex::getUser() || !rex::getUser()->isAdmin()) {
    echo '<div class="alert alert-danger">Diese Seite ist nur für Administratoren zugänglich.</div>';
    exit;
}

// WICHTIG: Prüfen ob Client-Modus aktiv ist - dann zur Client-Seite weiterleiten
if (!$isHostmode) {
    rex_response::sendRedirect(rex_url::backendPage('newssync/client'));
    exit;
}

$func = rex_request('func', 'string');
$id = rex_request('id', 'int');

/**
 * Validiert den Client-Namen
 */
function validate_client_name($name) {
    $name = trim($name);
    if (empty($name)) {
        return ['valid' => false, 'error' => 'Client-Name darf nicht leer sein'];
    }
    if (strlen($name) > 255) {
        return ['valid' => false, 'error' => 'Client-Name ist zu lang (max. 255 Zeichen)'];
    }
    if (preg_match('/[<>"\']/', $name)) {
        return ['valid' => false, 'error' => 'Client-Name enthält ungültige Zeichen'];
    }
    return ['valid' => true, 'value' => $name];
}

// NEU: Host-URL für Export ermitteln (mit korrektem Protokoll) - NACH OBEN VERSCHOBEN
$host_url = rex::getServer();

// HTTPS-Korrektur: rex::getServer() gibt manchmal http zurück, auch wenn https aktiv ist
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    $host_url = str_replace('http://', 'https://', $host_url);
} elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    $host_url = str_replace('http://', 'https://', $host_url);
} elseif (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
    $host_url = str_replace('http://', 'https://', $host_url);
}

// API-Key generieren
if ($func == 'generate_key') {
    $client_name = rex_request('client_name', 'string');
    
    $validation = validate_client_name($client_name);
    
    if ($validation['valid']) {
        $api_key = bin2hex(random_bytes(32));
        
        $sql = rex_sql::factory();
        $sql->setTable(rex::getTable('1940_newssync_keys'));
        $sql->setValue('client_name', $validation['value']);
        $sql->setValue('api_key', $api_key);
        $sql->setValue('active', 1);
        $sql->setDateTimeValue('createdate', time());
        
        try {
            $sql->insert();
            
            // JSON-Daten für Copy (API-Key + Host-URL)
            $copy_config_data = json_encode([
                'api_key' => $api_key,
                'host_url' => $host_url
            ], JSON_UNESCAPED_SLASHES);
            ?>
			
            <div class="alert alert-success">
                <strong>API-Key erfolgreich erstellt!</strong><br>
                Client: <?php echo htmlspecialchars($validation['value']); ?><br>
                API-Key: <code><?php echo htmlspecialchars($api_key); ?></code>
				
                <button class="btn btn-xs btn-success copy-new-key-btn" data-key="<?php echo htmlspecialchars($api_key); ?>" title="API-Key in Zwischenablage kopieren" style="position: relative; margin-left: 10px; border-color: #FFF;">
                    <i class="rex-icon fa-copy"></i>
                    <span class="copy-tooltip">✓ Kopiert!</span>
                </button>
                <button class="btn btn-xs btn-success copy-new-config-btn" data-config='<?php echo htmlspecialchars($copy_config_data, ENT_QUOTES); ?>' title="API-Key & Host-URL in Zwischenablage kopieren" style="position: relative; border-color: #FFF;">
                    <i class="rex-icon fa-copy"></i> + Host
                    <span class="copy-tooltip">✓ Konfiguration kopiert!</span>
                </button>
            </div>
			
			<?php
        } catch (Exception $e) {
            echo '<div class="alert alert-danger">Fehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    } else {
        echo '<div class="alert alert-danger">' . htmlspecialchars($validation['error']) . '</div>';
    }
    $func = '';
}

// Client-Name bearbeiten
if ($func == 'edit_name' && $id > 0) {
    if (rex_request_method() == 'post') {
        $new_name = rex_request('client_name', 'string');
        
        $validation = validate_client_name($new_name);
        
        if ($validation['valid']) {
            $sql = rex_sql::factory();
            $sql->setTable(rex::getTable('1940_newssync_keys'));
            $sql->setValue('client_name', $validation['value']);
            $sql->setWhere(['id' => $id]);
            
            try {
                $sql->update();
                echo '<div class="alert alert-success">Client-Name wurde erfolgreich geändert!</div>';
                $func = '';
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Fehler beim Aktualisieren: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            echo '<div class="alert alert-danger">' . htmlspecialchars($validation['error']) . '</div>';
        }
    }
}

// API-Key löschen
if ($func == 'delete_key' && $id > 0) {
    $sql = rex_sql::factory();
    
    // Prüfen ob der Key existiert und Details holen
    $sql->setQuery('SELECT client_name FROM ' . rex::getTable('1940_newssync_keys') . ' WHERE id = ?', [$id]);
    
    if ($sql->getRows() > 0) {
        $client_name = $sql->getValue('client_name');
        
        // Löschen
        $sql->setQuery('DELETE FROM ' . rex::getTable('1940_newssync_keys') . ' WHERE id = ?', [$id]);
        echo '<div class="alert alert-success">API-Key für Client "' . htmlspecialchars($client_name) . '" wurde gelöscht!</div>';
    } else {
        echo '<div class="alert alert-danger">API-Key nicht gefunden!</div>';
    }
    $func = '';
}

// API-Key aktivieren/deaktivieren
if ($func == 'toggle_key' && $id > 0) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT active, client_name FROM ' . rex::getTable('1940_newssync_keys') . ' WHERE id = ?', [$id]);
    
    if ($sql->getRows() > 0) {
        $current = (int)$sql->getValue('active');
        $client_name = $sql->getValue('client_name');
        $new_status = $current ? 0 : 1;
        
        $update_sql = rex_sql::factory();
        $update_sql->setTable(rex::getTable('1940_newssync_keys'));
        $update_sql->setValue('active', $new_status);
        $update_sql->setWhere(['id' => $id]);
        $update_sql->update();
        
        $status = $new_status ? 'aktiviert' : 'deaktiviert';
        echo '<div class="alert alert-success">API-Key für Client "' . htmlspecialchars($client_name) . '" wurde ' . $status . '!</div>';
    } else {
        echo '<div class="alert alert-danger">API-Key nicht gefunden!</div>';
    }
    $func = '';
}

// Export API-Keys
if ($func == 'export_keys') {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT client_name, api_key, active FROM ' . rex::getTable('1940_newssync_keys') . ' ORDER BY client_name');
    
    $export_data = [];
    foreach ($sql as $row) {
        $export_data[] = [
            'client_name' => $row->getValue('client_name'),
            'api_key' => $row->getValue('api_key'),
            'active' => (int)$row->getValue('active')
        ];
    }
    
    $json = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
    if (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="newssync_api_keys_' . date('Y-m-d_H-i-s') . '.json"');
    echo $json;
    exit;
}

// Import API-Keys
if ($func == 'import_keys') {
    if (isset($_FILES['import_file']) && $_FILES['import_file']['error'] === UPLOAD_ERR_OK) {
        $json = file_get_contents($_FILES['import_file']['tmp_name']);
        $import_data = json_decode($json, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($import_data)) {
            $count_imported = 0;
            $count_skipped = 0;
            
            foreach ($import_data as $item) {
                if (!isset($item['client_name']) || !isset($item['api_key'])) {
                    $count_skipped++;
                    continue;
                }
                
                $check_sql = rex_sql::factory();
                $check_sql->setQuery(
                    'SELECT id FROM ' . rex::getTable('1940_newssync_keys') . ' WHERE api_key = ?',
                    [$item['api_key']]
                );
                
                if ($check_sql->getRows() > 0) {
                    $count_skipped++;
                    continue;
                }
                
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('1940_newssync_keys'));
                $sql->setValue('client_name', $item['client_name']);
                $sql->setValue('api_key', $item['api_key']);
                $sql->setValue('active', isset($item['active']) ? (int)$item['active'] : 1);
                $sql->setDateTimeValue('createdate', time());
                
                try {
                    $sql->insert();
                    $count_imported++;
                } catch (Exception $e) {
                    $count_skipped++;
                }
            }
            
            echo '<div class="alert alert-success">
                <strong>Import erfolgreich!</strong><br>
                ' . $count_imported . ' API-Keys importiert, ' . $count_skipped . ' übersprungen.
            </div>';
        } else {
            echo '<div class="alert alert-danger">Ungültige JSON-Datei!</div>';
        }
    } else {
        echo '<div class="alert alert-danger">Fehler beim Hochladen der Datei!</div>';
    }
    $func = '';
}

// Bearbeitungsformular für Client-Namen
if ($func == 'edit_name' && $id > 0 && rex_request_method() != 'post') {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT client_name FROM ' . rex::getTable('1940_newssync_keys') . ' WHERE id = ?', [$id]);
    
    if ($sql->getRows() > 0) {
        $client_name = $sql->getValue('client_name');
        
        echo '<div class="panel panel-edit">
            <div class="panel-heading">
                <h3 class="panel-title">Client-Namen bearbeiten</h3>
            </div>
            <div class="panel-body">
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="func" value="edit_name" />
                    <input type="hidden" name="id" value="' . (int)$id . '" />
                    
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="client_name_edit">Client-Name *</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="client_name_edit" name="client_name" 
                                   value="' . htmlspecialchars($client_name) . '" 
                                   maxlength="255" required pattern="[^<>\'&quot;]+" 
                                   placeholder="z.B. Kundenname oder domain.de" autofocus />
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="submit" class="btn btn-primary">
                                <i class="rex-icon fa-save"></i> Speichern
                            </button>
                            <a href="' . rex_url::currentBackendPage() . '" class="btn btn-default">
                                <i class="rex-icon fa-times"></i> Abbrechen
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>';
        exit;
    } else {
        echo '<div class="alert alert-danger">API-Key nicht gefunden!</div>';
        $func = '';
    }
}

// Formular anzeigen
echo '<div class="row" style="margin-bottom: 20px;">
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-key"></i> Neuen API-Key erstellen</h3>
            </div>
            <div class="panel-body">
                <form method="post" class="form-horizontal">
                    <input type="hidden" name="func" value="generate_key" />
                    <div class="form-group">
                        <label class="col-sm-3 control-label" for="client_name">Client-Name *</label>
                        <div class="col-sm-9">
                            <input type="text" class="form-control" id="client_name" name="client_name" 
                                   maxlength="255" required pattern="[^<>\'&quot;]+" 
                                   placeholder="z.B. Kundenname GmbH oder Projekt XY" />
                            <p class="help-block">Beschreibender Name für den Client (keine HTML-Zeichen)</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-3 col-sm-9">
                            <button type="submit" class="btn btn-primary">
                                <i class="rex-icon fa-key"></i> API-Key generieren
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="rex-icon fa-exchange"></i> Import / Export</h3>
            </div>
            <div class="panel-body">
                <p><strong>Export:</strong></p>
                <a href="' . rex_url::currentBackendPage(['func' => 'export_keys']) . '" class="btn btn-success btn-block" style="margin-bottom: 15px;">
                    <i class="rex-icon fa-download"></i> Alle Keys exportieren
                </a>
                
                <hr>
                
                <p><strong>Import:</strong></p>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="func" value="import_keys" />
                    <div class="form-group">
                        <input type="file" name="import_file" accept=".json" required class="form-control" />
                        <p class="help-block">
                            <small>JSON-Datei mit exportierten API-Keys</small>
                        </p>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="rex-icon fa-upload"></i> Keys importieren
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Kopier-Tooltip -->
<style>
.copy-api-key-btn,
.copy-config-btn,
.copy-new-key-btn,
.copy-new-config-btn {
    position: relative;
}
.copy-tooltip {
    position: absolute;
    padding: 8px 12px;
    background: #5cb85c;
    color: white;
    border-radius: 4px;
    font-size: 13px;
    font-weight: bold;
    z-index: 10000;
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    white-space: nowrap;
    left: 50%;
    transform: translateX(-50%);
    bottom: calc(100% + 10px);
}
.copy-tooltip.show {
    opacity: 1;
}
</style>

<div class="panel panel-default" id="keylist">
    <div class="panel-heading">
        <h3 class="panel-title"><i class="rex-icon fa-list"></i> Vorhandene API-Keys</h3>
    </div>
    <div class="panel-body">';

// Sortierung und Suche
$sort = rex_request('sort', 'string', 'client_name');
$sorttype = htmlspecialchars(rex_request('sorttype', 'string', 'asc'));
$search = rex_request('search', 'string', '');

$valid_sorts = ['client_name', 'createdate'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'client_name';
}

$where = '';
if (!empty($search)) {
    $search_escaped = rex_sql::factory()->escape('%' . $search . '%');
    $where = ' WHERE client_name LIKE ' . $search_escaped;
}

$query = 'SELECT id, client_name, api_key, active, createdate, last_used, request_count 
    FROM ' . rex::getTable('1940_newssync_keys') . 
    $where . ' ORDER BY ' . $sort . ' ' . strtoupper($sorttype);

$list = rex_list::factory($query, 20, 'apikeys');

$list->removeColumn('id');
$list->removeColumn('active');

$list->setColumnLabel('client_name', 'Client-Name');
$list->setColumnLabel('api_key', 'API-Key');
$list->setColumnLabel('createdate', 'Erstellt am');
$list->setColumnLabel('last_used', 'Zuletzt verwendet');
$list->setColumnLabel('request_count', 'Anfragen');

$list->addTableAttribute('class', 'table-striped');
$list->addTableAttribute('class', 'sorttype-'.$sorttype);

$list->addFormAttribute('method', 'get');
$list->addFormAttribute('action', rex_url::currentBackendPage());

// Suchfeld
echo '<div style="margin-bottom: 15px;">
    <form method="get" action="' . rex_url::currentBackendPage() . '" class="form-inline">
        <input type="hidden" name="page" value="newssync/api_keys" />
        <input type="hidden" name="sort" value="' . htmlspecialchars($sort) . '" />
        <input type="hidden" name="sorttype" value="' . htmlspecialchars($sorttype) . '" />
        <div class="form-group">
            <label for="api-search"><i class="rex-icon fa-search"></i> Suche:</label>
            <input type="text" class="form-control" id="api-search" name="search" 
                   value="' . htmlspecialchars($search) . '" 
                   placeholder="Client-Name durchsuchen..." style="width: 300px;" />
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="rex-icon fa-search"></i> Suchen
        </button>';
        
if ($search) {
    echo '<a href="' . rex_url::currentBackendPage(['sort' => $sort]) . '" class="btn btn-default">
            <i class="rex-icon fa-times"></i> Zurücksetzen
        </a>';
}

echo '    </form>
</div>';

echo '<style>
.rex-table th.sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
}
.rex-table th.sortable:hover {
    background-color: #f0f0f0;
}
.rex-table th.sortable.sorted {
    background-color: #f5f5f5;
    font-weight: bold;
}
.rex-table th.sortable.sorted a::after {
    content: "";
    margin-left: 8px;
    color: #3c8dbc;
    font-weight: bold;
    display: inline-block;
}
.rex-table th.sortable.sorted-asc a::after {
    content: "▲";
}
.rex-table th.sortable.sorted-desc a::after {
    content: "▼";
}
</style>';

$list->setColumnSortable('client_name');
$list->setColumnSortable('createdate');

$list->setColumnFormat('client_name', 'custom', function($params) {
    $id = (int)$params['list']->getValue('id');
    return '<a href="' . rex_url::currentBackendPage(['func' => 'edit_name', 'id' => $id]) . '" 
               title="Client-Namen bearbeiten">' . htmlspecialchars($params['value']) . '</a>';
});

// API-Key Column mit BEIDEN Buttons:
$list->setColumnFormat('api_key', 'custom', function($params) use ($host_url) {
    $key = $params['value'];
    $masked = substr($key, 0, 8) . '••••••••••••••••' . substr($key, -8);
    
    // JSON-Daten für Copy (API-Key + Host-URL)
    $copy_data = json_encode([
        'api_key' => $key,
        'host_url' => $host_url
    ], JSON_UNESCAPED_SLASHES);
    
    return '<code class="small">' . htmlspecialchars($masked) . '</code>
            <button class="btn btn-xs btn-default copy-api-key-btn" 
                    data-key="' . htmlspecialchars($key) . '" 
                    title="API-Key in Zwischenablage kopieren" 
                    style="position: relative;">
                <i class="rex-icon fa-copy"></i>
                <span class="copy-tooltip">✓ Kopiert!</span>
            </button>
            <button class="btn btn-xs btn-default copy-config-btn" 
                    data-config=\'' . htmlspecialchars($copy_data, ENT_QUOTES) . '\' 
                    title="API-Key & Host-URL in Zwischenablage kopieren" 
                    style="position: relative;">
                <i class="rex-icon fa-copy"></i> + Host
                <span class="copy-tooltip">✓ Konfiguration kopiert!</span>
            </button>';
});

$list->setColumnFormat('request_count', 'custom', function($params) {
    $count = (int)$params['value'];
    return number_format($count, 0, ',', '.');
});

$list->setColumnFormat('createdate', 'custom', function($params) {
    $value = htmlspecialchars($params['value']);
    return date("d.m.Y - H:i:s", strtotime($value)).' Uhr';
});

$list->setColumnFormat('last_used', 'custom', function($params) {
    $value = htmlspecialchars($params['value']);
    return (empty($value) || $value == '0000-00-00 00:00:00') ? '-' : date("d.m.Y - H:i:s", strtotime($value)).' Uhr';
});

$list->addColumn('Funktionen', '');
$list->setColumnFormat('Funktionen', 'custom', function($params) {
    $id = (int)$params['list']->getValue('id');
    $active = $params['list']->getValue('active');
    
    $toggle_label = $active ? 'Deaktivieren' : 'Aktivieren';
    $toggle_class = $active ? 'success' : 'warning';
    $toggle_icon = $active ? 'toggle-on' : 'toggle-off';
    
    return '
        <a href="' . rex_url::currentBackendPage(['func' => 'edit_name', 'id' => $id]) . '" 
           class="btn btn-primary btn-xs" title="Client-Namen bearbeiten">
            <i class="rex-icon fa-edit"></i>
        </a>
        <a href="' . rex_url::currentBackendPage(['func' => 'toggle_key', 'id' => $id]) . '" 
           class="btn btn-' . $toggle_class . ' btn-xs" title="' . $toggle_label . '">
            <i class="rex-icon fa-' . $toggle_icon . '"></i>
        </a>
        <a href="' . rex_url::currentBackendPage(['func' => 'delete_key', 'id' => $id]) . '" 
           data-confirm="API-Key wirklich löschen?" 
           class="btn btn-danger btn-xs" title="Löschen">
            <i class="rex-icon fa-trash"></i>
        </a>
    ';
});

echo $list->get();

echo '    </div>
</div>

<script>
jQuery(function($) {
    // Event-Handler für einfache API-Key Kopier-Buttons
    $(document).on("click", ".copy-api-key-btn", function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $tooltip = $btn.find(".copy-tooltip");
        var apiKey = $btn.data("key");
        
        copyToClipboard(apiKey, $tooltip);
    });
    
    // Event-Handler für Konfigurations-Export (API-Key + Host-URL) in Tabelle
    $(document).on("click", ".copy-config-btn", function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $tooltip = $btn.find(".copy-tooltip");
        var configData = $btn.data("config");
        
        // Als JSON-String kopieren
        var jsonString = typeof configData === "string" ? configData : JSON.stringify(configData);
        
        copyToClipboard(jsonString, $tooltip);
    });
    
    // Event-Handler für Kopier-Button beim neuen API-Key (nur Key)
    $(document).on("click", ".copy-new-key-btn", function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $tooltip = $btn.find(".copy-tooltip");
        var apiKey = $btn.data("key");
        
        copyToClipboard(apiKey, $tooltip);
    });
    
    // NEU: Event-Handler für Konfigurations-Export beim neuen API-Key (Key + Host)
    $(document).on("click", ".copy-new-config-btn", function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $tooltip = $btn.find(".copy-tooltip");
        var configData = $btn.data("config");
        
        // Als JSON-String kopieren
        var jsonString = typeof configData === "string" ? configData : JSON.stringify(configData);
        
        copyToClipboard(jsonString, $tooltip);
    });
    
    function copyToClipboard(text, $tooltip) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopyTooltip($tooltip);
            }).catch(function() {
                fallbackCopy(text, $tooltip);
            });
        } else {
            fallbackCopy(text, $tooltip);
        }
    }
    
    function showCopyTooltip($tooltip) {
        $tooltip.addClass("show");
        setTimeout(function() {
            $tooltip.removeClass("show");
        }, 2000);
    }
    
    function fallbackCopy(text, $tooltip) {
        var $temp = $("<textarea>");
        $("body").append($temp);
        $temp.val(text).select();
        
        try {
            document.execCommand("copy");
            showCopyTooltip($tooltip);
        } catch (err) {
            alert("Konnte nicht in Zwischenablage kopieren. Bitte manuell kopieren:\n\n" + text);
        }
        
        $temp.remove();
    }
});
</script>';