<?php
/*
	Redaxo-Addon NewsSync
	Verwaltung: News-Verwaltung (Host)
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
	$isHostmode 	= (int)@$config['host_mode'];
	

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

/**
 * Validiert News-Daten
 */
function validate_news_data($title, $content, $type, $client_keys_str) {
    $errors = [];
    
    // Titel validieren
    $title = trim($title);
    if (empty($title)) {
        $errors[] = 'Titel darf nicht leer sein';
    }
    if (strlen($title) > 255) {
        $errors[] = 'Titel ist zu lang (max. 255 Zeichen)';
    }
    
    // Content validieren
    $content = trim($content);
    if (empty($content)) {
        $errors[] = 'Inhalt darf nicht leer sein';
    }
    if (strlen($content) > 65535) {
        $errors[] = 'Inhalt ist zu lang (max. 65535 Zeichen)';
    }
    
    // Typ validieren
    if (!in_array($type, ['info', 'warning', 'success', 'danger'])) {
        $errors[] = 'Ungültiger Typ';
    }
    
    // Client-Keys String validieren (kommaseparierte IDs oder leer für alle)
    $client_keys_str = trim($client_keys_str);
    
    // Gefährliche Attribute entfernen
    $content = preg_replace('/(on\w+)="[^"]*"/i', '', $content);
    $content = preg_replace('/javascript:/i', '', $content);
    
    // Titel bleibt plain text
    $title = strip_tags($title);
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'data' => [
            'title' => $title,
            'content' => $content,
            'type' => $type,
            'client_keys_str' => $client_keys_str
        ]
    ];
}

// News speichern/aktualisieren
if ($func == 'add' || $func == 'edit') {
    if (rex_request_method() == 'post') {
        $title = rex_request('title', 'string');
        $content = rex_request('content', 'string');
        $type = rex_request('type', 'string');
        $active = rex_request('active', 'boolean', 0);
        
        // Prüfen ob "Alle Clients" aktiv ist
        $all_clients = rex_request('all_clients', 'boolean', 0);
        
        // Client-Keys als kommaseparierter String
        $client_keys_str = '';
        if (!$all_clients) {
            $selected_clients = rex_request('client_key', 'array', []);
            if (!empty($selected_clients)) {
                // Validiere IDs und erstelle String
                $valid_ids = array_filter($selected_clients, function($id) {
                    return is_numeric($id) && (int)$id > 0;
                });
                $client_keys_str = implode(',', $valid_ids);
            }
        }
        // Wenn "Alle Clients": client_key bleibt NULL
        $client_keys_str = $all_clients ? null : $client_keys_str;
        
        $expire_date = rex_request('expire_date', 'string');
        
        // Validierung
        $validation = validate_news_data($title, $content, $type, $client_keys_str);
        
        // Expire-Date validieren (optional)
        $expire_date_value = null;
        if (!empty($expire_date)) {
            $datetime = DateTime::createFromFormat('Y-m-d\TH:i', $expire_date);
            if ($datetime && $datetime->format('Y-m-d\TH:i') === $expire_date) {
                $expire_date_value = $datetime->format('Y-m-d H:i:s');
            } else {
                $validation['valid'] = false;
                $validation['errors'][] = 'Ungültiges Ablaufdatum-Format';
            }
        }
        
        if ($validation['valid']) {
            $data = $validation['data'];
            
            try {
                $sql = rex_sql::factory();
                $sql->setTable(rex::getTable('1940_newssync_news'));
                $sql->setValue('title', $data['title']);
                $sql->setValue('content', $data['content']);
                $sql->setValue('type', $data['type']);
                $sql->setValue('active', $active ? 1 : 0);
                
                // NULL für alle Clients, sonst kommaseparierte IDs
                if ($data['client_keys_str'] === null || $data['client_keys_str'] === '') {
                    $sql->setValue('client_key', null);
                } else {
                    $sql->setValue('client_key', $data['client_keys_str']);
                }
                
                $sql->setValue('expire_date', $expire_date_value);
                $sql->setDateTimeValue('updatedate', time());
                
                if ($func == 'add') {
                    $sql->setDateTimeValue('createdate', time());
                    $sql->insert();
                    echo '<div class="alert alert-success">News wurde erstellt!</div>';
                } else {
                    if ($id > 0) {
                        $sql->setWhere(['id' => $id]);
                        $sql->update();
                        echo '<div class="alert alert-success">News wurde aktualisiert!</div>';
                    } else {
                        echo '<div class="alert alert-danger">Ungültige News-ID</div>';
                    }
                }
                $func = '';
            } catch (Exception $e) {
                echo '<div class="alert alert-danger">Datenbankfehler: ' . htmlspecialchars($e->getMessage()) . '</div>';
            }
        } else {
            echo '<div class="alert alert-danger">Validierungsfehler:<ul>';
            foreach ($validation['errors'] as $error) {
                echo '<li>' . htmlspecialchars($error) . '</li>';
            }
            echo '</ul></div>';
        }
    }
}

// News löschen
if ($func == 'delete' && $id > 0) {
    $sql = rex_sql::factory();
    $sql->setQuery('DELETE FROM ' . rex::getTable('1940_newssync_news') . ' WHERE id = ?', [$id]);
    echo '<div class="alert alert-success">News wurde gelöscht!</div>';
    $func = '';
}

// News aktivieren/deaktivieren
if ($func == 'toggle' && $id > 0) {
    $sql = rex_sql::factory();
    $sql->setQuery('SELECT active FROM ' . rex::getTable('1940_newssync_news') . ' WHERE id = ?', [$id]);
    
    if ($sql->getRows() > 0) {
        $current = $sql->getValue('active');
        
        $sql->setTable(rex::getTable('1940_newssync_news'));
        $sql->setValue('active', $current ? 0 : 1);
        $sql->setDateTimeValue('updatedate', time());
        $sql->setWhere(['id' => $id]);
        $sql->update();
        
        echo '<div class="alert alert-success">Status wurde geändert!</div>';
    } else {
        echo '<div class="alert alert-danger">News nicht gefunden!</div>';
    }
    $func = '';
}

// News Verwaltung
if ($func == 'add' || $func == 'edit') {
	//Editor setzen
	$editor_class = @$config['editor_class'];
		$editor_class = (empty($editor_class)) ? 'form-control' : $editor_class;
	$editor_data = @$config['editor_data'];
	
	
	//News auslesen
    $sql = rex_sql::factory();
    $values = ['title' => '', 'content' => '', 'type' => 'info', 'active' => 1, 'all_clients' => 1, 'client_keys' => [], 'expire_date' => ''];
    
    if ($func == 'edit' && $id > 0) {
        $sql->setQuery('SELECT * FROM ' . rex::getTable('1940_newssync_news') . ' WHERE id = ?', [$id]);
        if ($sql->getRows() > 0) {
            // Expire-Date für datetime-local Input formatieren
            $expire_date = $sql->getValue('expire_date');
            $expire_date_formatted = '';
            if (!empty($expire_date) && $expire_date != '0000-00-00 00:00:00') {
                $dt = DateTime::createFromFormat('Y-m-d H:i:s', $expire_date);
                if ($dt) {
                    $expire_date_formatted = $dt->format('Y-m-d\TH:i');
                }
            }
            
            // Client-Key(s) verarbeiten
            $client_key_str = $sql->getValue('client_key');
            // Legacy "0" auch als "Alle Clients" behandeln
            $all_clients = (empty($client_key_str) || $client_key_str === null || $client_key_str === '0') ? 1 : 0;
            $client_keys = [];
            
            if (!empty($client_key_str) && $client_key_str !== null && $client_key_str !== '0') {
                $client_keys = array_map('intval', explode(',', $client_key_str));
            }
            
            $values = [
                'title' => $sql->getValue('title'),
                'content' => $sql->getValue('content'),
                'type' => $sql->getValue('type'),
                'active' => $sql->getValue('active'),
                'all_clients' => $all_clients,
                'client_keys' => $client_keys,
                'expire_date' => $expire_date_formatted
            ];
        } else {
            echo '<div class="alert alert-danger">News nicht gefunden!</div>';
            $func = '';
        }
    }
    
    if ($func) {
        // API-Keys für Dropdown holen
        $keys_sql = rex_sql::factory();
        $keys_sql->setQuery('SELECT id, client_name FROM ' . rex::getTable('1940_newssync_keys') . ' WHERE active = 1 ORDER BY client_name');
        
        $key_options = '';
        foreach ($keys_sql as $row) {
            $selected = in_array($row->getValue('id'), $values['client_keys']) ? ' selected' : '';
            $key_options .= '<option value="' . (int)$row->getValue('id') . '"' . $selected . '>' 
                . htmlspecialchars($row->getValue('client_name')) . '</option>';
        }
		?>

<section class="rex-page-section">
	<div class="panel panel-edit">
	
		<header class="panel-heading"><div class="panel-title"><?php echo ($func == 'add' ? 'Neue News erstellen' : 'News bearbeiten'); ?></div></header>
		
		<div class="panel-body">

        <form method="post" class="form-horizontal">
            <input type="hidden" name="func" value="<?php echo htmlspecialchars($func); ?>" />
            <?php echo ($func == 'edit' ? '<input type="hidden" name="id" value="' . (int)$id . '" />' : ''); ?>
            
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="active" value="1" <?php echo ($values['active'] ? ' checked' : ''); ?> />
                            <strong>News ist aktiv</strong> (wird synchronisiert)
                        </label>
                    </div>
                </div>
            </div>
            
			
			<dl class="spacerline"></dl>
			
			
            <div class="form-group">
                <label class="col-sm-2 control-label" for="news_title">Titel / Bezeichnung *</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control" id="news_title" name="title" 
                           value="<?php echo htmlspecialchars($values['title']); ?>" 
                           maxlength="255" required />
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-2 control-label" for="news_content">News-Text *</label>
                <div class="col-sm-10">
					<textarea name="content" id="news_content" class="form-control <?php echo $editor_class; ?>" <?php echo $editor_data; ?> required><?php echo htmlspecialchars($values['content']); ?></textarea>
                </div>
            </div>
			
			
			<dl class="spacerline"></dl>
			
            
            <div class="form-group">
                <label class="col-sm-2 control-label" for="news_type">Typ</label>
                <div class="col-sm-10">
                    <select class="form-control" id="news_type" name="type">
                        <option value="info"<?php echo ($values['type'] == 'info' ? ' selected' : ''); ?>>Information</option>
                        <option value="warning"<?php echo ($values['type'] == 'warning' ? ' selected' : ''); ?>>Hinweis</option>
                        <option value="danger"<?php echo ($values['type'] == 'danger' ? ' selected' : ''); ?>>Wichtig</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-2 control-label" for="all_clients">Zielgruppe</label>
                <div class="col-sm-10">
                    <div class="checkbox">
                        <label>
                            <input type="checkbox" name="all_clients" id="all_clients" value="1"<?php echo ($values['all_clients'] ? ' checked' : ''); ?> />
                            <strong>Für alle Clients veröffentlichen</strong>
                        </label>
                    </div>
					
                    
                    <div id="client_selection" style="<?php echo ($values['all_clients'] ? ' display: none;' : ''); ?>">
						<br>
						
                        <select class="form-control" id="news_client" name="client_key[]" multiple size="8">
                            <?php echo $key_options; ?>
                        </select>
						
						<span class="infoblock">
                            <strong>Mehrfachauswahl:</strong> Halte Strg/Cmd gedrückt, um mehrere Clients auszuwählen.
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label class="col-sm-2 control-label" for="expire_date">
                    <i class="rex-icon fa-clock-o"></i> Ablaufdatum (optional)
                </label>
                <div class="col-sm-10">
                    <input type="datetime-local" class="form-control" id="expire_date" name="expire_date" 
                           value="<?php echo htmlspecialchars($values['expire_date']); ?>" />
                    
					<span class="infoblock">
                        News wird nach diesem Zeitpunkt automatisch als inaktiv behandelt beim Sync. 
                        Leer lassen für unbegrenzte Gültigkeit.
                    </span>
                </div>
            </div>
            
			<!--
            <div class="form-group">
                <div class="col-sm-offset-2 col-sm-10">
                    <button type="submit" class="btn btn-primary">
                        <i class="rex-icon fa-save"></i> Speichern
                    </button>
                    <a href="' . rex_url::currentBackendPage() . '" class="btn btn-default">
                        <i class="rex-icon fa-times"></i> Abbrechen
                    </a>
                </div>
            </div>
			-->
		</div>
		
		
		<footer class="panel-footer">
			<div class="rex-form-panel-footer">
				<div class="btn-toolbar">
					<input class="btn btn-save rex-form-aligned" type="submit" name="submit" title="Speichern" value="Speichern" />
					<a href="<?php echo rex_url::currentBackendPage(); ?>" class="btn btn-default">Abbrechen</a>
				</div>
			</div>
		</footer>
		
        </form>		
		
	</div>
</section>

        
<script>
jQuery(function($) {
	$("#all_clients").change(function() {
		if ($(this).is(":checked")) {
			$("#client_selection").slideUp(200);
		} else {
			$("#client_selection").slideDown(200);
		}
	});
});
</script>

<?php
    }
    
} else {
	//Übersichtsliste
	?>
	
	<style>
    /* CSS für sortierbare Spalten */
    .rex-table th.sortable { cursor: pointer; user-select: none; position: relative; }
		.rex-table th.sortable:hover { background-color: #f0f0f0; }
    .rex-table th.sortable.sorted { background-color: #f5f5f5; font-weight: bold; }
		.rex-table th.sortable.sorted a::after { content: ""; margin-left: 8px; color: #3c8dbc; font-weight: bold; display: inline-block; }
		.rex-table th.sortable.sorted-asc a::after { content: "▲"; }
		.rex-table th.sortable.sorted-desc a::after { content: "▼"; }
	
	.info { font-size: 0.825em; font-weight: normal; }
	.info-labels { display: inline-block; padding: 3px 6px; background: #EAEAEA; margin-right: 5px; font-size: 0.80em; }
	.info-green { background: #360; color: #FFF; }
	.info-red { background: #900; color: #FFF; }
	.infoblock { display: block; font-size: 0.825em; margin-top: 7px; }
	.textblock { width: auto !important; font-weight: normal; padding-bottom: 10px; }
	
	.akkordeonstyle {}
	.akkordeonstyle summary { color: #4b9ad9; cursor: pointer; }
		.akkordeonstyle summary:hover { text-decoration: underline;}
	.akkordeonstyle ul { list-style-type: square; margin: 0px; margin-left: 20px; padding: 0px; }
	.akkordeonstyle li { font-size: 0.825em; }
    </style>
	
	
	<a href="<?php echo rex_url::currentBackendPage(['func' => 'add']); ?>" class="btn btn-primary" style="margin-bottom: 15px;">
        <i class="rex-icon fa-plus"></i> Neue News erstellen
    </a>
	
	
	<dl class="spacerline"></dl>
	
	
	<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title"><i class="rex-icon fa-list"></i> Vorhandene News</h3>
		</div>
		<div class="panel-body">
		
	
		<?php    
		// Sortierung und Suche
		$sort = rex_request('sort', 'string', 'updatedate');
		$search = rex_request('search', 'string', '');
		
		// Gültige Sortierungen
		$valid_sorts = ['title', 'updatedate', 'active'];
		if (!in_array($sort, $valid_sorts)) {
			$sort = 'updatedate';
		}
		
		// Base Query mit Suche - SQL-Injection-sicher durch rex_sql::escape
		$where = '';
		if (!empty($search)) {
			$search_escaped = rex_sql::factory()->escape('%' . $search . '%');
			$where = ' WHERE n.title LIKE ' . $search_escaped;
		}
		
		$order = 'n.' . $sort;
		if ($sort == 'updatedate') {
			$order .= ' DESC, n.id DESC';
		} elseif ($sort == 'active') {
			$order .= ' DESC, n.updatedate DESC';
		}
		
		/*
		$query = 'SELECT n.id, n.title, n.type, n.active, n.updatedate, n.expire_date, n.client_key, k.client_name 
			FROM ' . rex::getTable('1940_newssync_news') . ' n 
			LEFT JOIN ' . rex::getTable('1940_newssync_keys') . ' k ON n.client_key = k.id' .
			$where . ' ORDER BY ' . $order;
		*/

		$query = 'SELECT n.id, n.title, n.type, n.active, n.updatedate, n.expire_date, n.client_key
			FROM ' . rex::getTable('1940_newssync_news') . ' n ' .
			$where . ' ORDER BY ' . $order;
		
		$list = rex_list::factory($query, 20, 'newslist');
		
			$list->removeColumn('id');
			$list->removeColumn('active');
			$list->removeColumn('expire_date');

			$list->setColumnLabel('title', 'Titel');
			$list->setColumnLabel('type', 'Typ');
			//$list->setColumnLabel('client_name', 'Clients');
			$list->setColumnLabel('client_key', 'Clients');
			$list->setColumnLabel('updatedate', 'Geändert am');
			
			$list->addTableAttribute('class', 'table-striped');
		
		// Filter- und Sortier-Formular
		echo '<div style="margin-bottom: 15px;">
			<form method="get" action="' . rex_url::currentBackendPage() . '" class="form-inline">
				<input type="hidden" name="page" value="newssync/host" />
				<input type="hidden" name="sort" value="' . htmlspecialchars($sort) . '" />
				<div class="form-group">
					<label for="news-search"><i class="rex-icon fa-search"></i> Suche:</label>
					<input type="text" class="form-control" id="news-search" name="search" 
						   value="' . htmlspecialchars($search) . '" 
						   placeholder="Titel durchsuchen..." style="width: 300px;" />
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
		
		
		// Spaltenüberschriften als Links für Sortierung
		$list->setColumnSortable('title');
		$list->setColumnSortable('updatedate');    
			
			
		$list->setColumnFormat('title', 'custom', function($params) {
			$id 	= $params['list']->getValue('id');
			$expire = $params['list']->getValue('expire_date');
			
			$value = htmlspecialchars($params['value']);
				$value = '<a href="'.rex_url::currentBackendPage(['func' => 'edit', 'id' => $id]).'" title="Bearbeiten">'.$value.'</a>';
			
				if (!empty($expire) && $expire != '0000-00-00 00:00:00'):
					$value .= '<br><span class="infoblock">Ablaufdatum: '.date("d.m.Y - H:i", strtotime($expire)).' Uhr</span>';
				endif;
			
			return $value;
		});
		
		$list->setColumnFormat('type', 'custom', function($params) {
			$types = [
				'info' 		=> ['label' => 'Information', 'class' => 'info', 'icon' => ''],
				'success' 	=> ['label' => 'Erfolg', 'class' => 'success', 'icon' => ''],
				'warning' 	=> ['label' => 'Hinweis', 'class' => 'warning', 'icon' => ''],
				'danger' 	=> ['label' => 'Wichtig', 'class' => 'danger', 'icon' => '']
			];
			
			$type 	= $params['value'];
			$type_config = $types[$type] ?? $types['info'];
			$icon	= (!empty($type_config['icon'])) ? '<i class="rex-icon '.$type_config['icon'].'"></i> ' : '';
			
			return '<span class="label label-' . $type_config['class'] . '">'.$icon.$type_config['label'].'</span>';
		});
		
		//$list->setColumnFormat('client_name', 'custom', function($params) {
		$list->setColumnFormat('client_key', 'custom', function($params) {
			$client_key_str = $params['list']->getValue('client_key');
			
			// Legacy "0" auch als "Alle Clients" behandeln
			if (empty($client_key_str) || $client_key_str === null || $client_key_str === '0') {
				return '<em>Alle Clients</em>';
			}
			
			// Kommaseparierte IDs in Array umwandeln
			$client_ids = array_map('intval', explode(',', $client_key_str));
			
			// Client-Namen aus Datenbank holen
			$sql = rex_sql::factory();
			$placeholders = implode(',', array_fill(0, count($client_ids), '?'));
			$sql->setQuery(
				'SELECT client_name FROM ' . rex::getTable('1940_newssync_keys') . 
				' WHERE id IN (' . $placeholders . ') ORDER BY client_name',
				$client_ids
			);
			
			$names = [];
			foreach ($sql as $row) {
				$names[] = htmlspecialchars($row->getValue('client_name'));
			}
			
			if (empty($names)) {
				return '<em style="color:#999;">Keine gültigen Clients</em>';
			}		

			$id = 'clients_' . $params['list']->getValue('id');
			$html  = '<details class="akkordeonstyle">';
				$html .= '<summary><em>' . count($names) . ' Client(s)</em></summary>';
				
				$html .= '<ul>';
					foreach ($names as $name) {
						$html .= '<li>' . $name . '</li>';
					}
				$html .= '</ul>';
				
			$html .= '</details>';

			return $html;
			
			//return '<em>Nur ausgewählte Clients</em><br><span class="infoblock">'.implode('</span><span class="infoblock">', $names).'</span>';
		});
		
		$list->setColumnFormat('updatedate', 'custom', function($params) {
			$value = htmlspecialchars($params['value']);
			return date("d.m.Y - H:i:s", strtotime($value)).' Uhr';
		});
		
		$list->addColumn('Funktionen', '');
		$list->setColumnFormat('Funktionen', 'custom', function($params) {
			$id = (int)$params['list']->getValue('id');
			$active = $params['list']->getValue('active');
					
			$toggle_label = $active ? 'Deaktivieren' : 'Aktivieren';
			$toggle_class = $active ? 'success' : 'warning';
			
			return '
				<a href="' . rex_url::currentBackendPage(['func' => 'edit', 'id' => $id]) . '" 
				   class="btn btn-primary btn-xs btn-wide" title="Bearbeiten">
					<i class="rex-icon fa-edit"></i>
				</a> 
				<a href="' . rex_url::currentBackendPage(['func' => 'toggle', 'id' => $id]) . '" 
				   class="btn btn-' . $toggle_class . ' btn-xs" title="' . $toggle_label . '">
					<i class="rex-icon fa-' . ($active ? 'toggle-on' : 'toggle-off') . '"></i>
				</a>
				<a href="' . rex_url::currentBackendPage(['func' => 'delete', 'id' => $id]) . '" 
				   data-confirm="Eintrag wirklich löschen?" class="btn btn-danger btn-xs" title="Löschen">
					<i class="rex-icon fa-trash"></i>
				</a>
			';
		});
		
		echo $list->get();
		?>
		
		</div>
	</div>

	<?php
}