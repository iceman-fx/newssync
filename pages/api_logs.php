<?php
/*
	Redaxo-Addon NewsSync
	Verwaltung: API-Logs (Host)
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

// Logs löschen
if ($func == 'delete_logs') {
    $days = rex_request('days', 'int', 30);
    
    if ($days > 0) {
        $sql = rex_sql::factory();
        $sql->setQuery(
            'DELETE FROM ' . rex::getTable('1940_newssync_logs') . ' 
            WHERE request_time < DATE_SUB(NOW(), INTERVAL ? DAY)',
            [$days]
        );
        
        $affected = $sql->getRows();
        echo '<div class="alert alert-success">
            <i class="rex-icon fa-check"></i> ' . $affected . ' Log-Einträge älter als ' . $days . ' Tage wurden gelöscht.
        </div>';
    }
    $func = '';
}

// Alle Logs löschen
if ($func == 'delete_all_logs') {
    $sql = rex_sql::factory();
    $sql->setQuery('TRUNCATE TABLE ' . rex::getTable('1940_newssync_logs'));
    echo '<div class="alert alert-success">
        <i class="rex-icon fa-check"></i> Alle Log-Einträge wurden gelöscht.
    </div>';
    $func = '';
}

// Statistiken berechnen
$stats_sql = rex_sql::factory();

// Gesamtanzahl Requests
$stats_sql->setQuery('SELECT COUNT(*) as total FROM ' . rex::getTable('1940_newssync_logs'));
$total_requests = $stats_sql->getValue('total');

// Requests letzte 24h
$stats_sql->setQuery('
    SELECT COUNT(*) as total 
    FROM ' . rex::getTable('1940_newssync_logs') . ' 
    WHERE request_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)
');
$requests_24h = $stats_sql->getValue('total');

// Fehlerhafte Requests
$stats_sql->setQuery('
    SELECT COUNT(*) as total 
    FROM ' . rex::getTable('1940_newssync_logs') . ' 
    WHERE http_code >= 400
');
$error_requests = $stats_sql->getValue('total');

// Top Clients
$stats_sql->setQuery('
    SELECT k.client_name, COUNT(l.id) as request_count
    FROM ' . rex::getTable('1940_newssync_logs') . ' l
    LEFT JOIN ' . rex::getTable('1940_newssync_keys') . ' k ON l.client_key_id = k.id
    WHERE l.client_key_id IS NOT NULL
    GROUP BY l.client_key_id
    ORDER BY request_count DESC
    LIMIT 5
');

$top_clients = [];
foreach ($stats_sql as $row) {
    $top_clients[] = [
        'name' => $row->getValue('client_name') ?: 'Unbekannt',
        'count' => (int)$row->getValue('request_count')
    ];
}

// Durchschnittliche News pro Request
$stats_sql->setQuery('
    SELECT AVG(news_count) as avg_news 
    FROM ' . rex::getTable('1940_newssync_logs') . ' 
    WHERE http_code = 200
');
$avg_news = round($stats_sql->getValue('avg_news'), 1);
?>

<div class="panel panel-default">
	<div class="panel-heading">
		<h3 class="panel-title"><i class="rex-icon fa-chart-line"></i> API-Statistiken</h3>
	</div>
	<div class="panel-body">

	<?php
	echo '<div class="row">
		<div class="col-sm-3">
			<div class="panel panel-default">
				<div class="panel-body text-center">
					<h4 style="margin-top: 0;">Gesamt-Requests</h4>
					<p style="font-size: 36px; margin: 10px 0; font-weight: bold; color: #3c8dbc;">' . number_format($total_requests, 0, ',', '.') . '</p>
				</div>
			</div>
		</div>
		
		<div class="col-sm-3">
			<div class="panel panel-success">
				<div class="panel-body text-center">
					<h4 style="margin-top: 0;">Letzte 24h</h4>
					<p style="font-size: 36px; margin: 10px 0; font-weight: bold; color: #5cb85c;">' . number_format($requests_24h, 0, ',', '.') . '</p>
				</div>
			</div>
		</div>
		
		<div class="col-sm-3">
			<div class="panel panel-danger">
				<div class="panel-body text-center">
					<h4 style="margin-top: 0;">Fehler</h4>
					<p style="font-size: 36px; margin: 10px 0; font-weight: bold; color: #d9534f;">' . number_format($error_requests, 0, ',', '.') . '</p>
				</div>
			</div>
		</div>
		
		<div class="col-sm-3">
			<div class="panel panel-info">
				<div class="panel-body text-center">
					<h4 style="margin-top: 0;">Ø News/Request</h4>
					<p style="font-size: 36px; margin: 10px 0; font-weight: bold; color: #5bc0de;">' . $avg_news . '</p>
				</div>
			</div>
		</div>
	</div>';
	?>
	
	</div>
</div>


<div class="row">
	<?php if (!empty($top_clients)): ?>
	<div class="col-sm-6">
	
	<?php
	echo '<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title"><i class="rex-icon fa-users"></i> Top 5 Clients</h3>
		</div>
		<div class="panel-body">
			<table class="table table-striped">
				<thead>
					<tr>
						<th>Client</th>
						<th style="text-align: right;">Requests</th>
					</tr>
				</thead>
				<tbody>';
	
	foreach ($top_clients as $client) {
		echo '<tr>
			<td><strong>' . htmlspecialchars($client['name']) . '</strong></td>
			<td style="text-align: right;">' . number_format($client['count'], 0, ',', '.') . '</td>
		</tr>';
	}
	
	echo '      </tbody>
			</table>
		</div>
	</div>';
	?>
	
	</div>
	<?php endif; ?>
	
	<div class="col-sm-6">
	
	<?php
	// Log-Verwaltung
	echo '<div class="panel panel-default">
		<div class="panel-heading">
			<h3 class="panel-title"><i class="rex-icon fa-cog"></i> Log-Verwaltung</h3>
		</div>
		<div class="panel-body">
			<form method="post" class="form-inline" style="margin-bottom: 15px;">
				<input type="hidden" name="func" value="delete_logs" />
				
				<label for="days">Logs löschen älter als:</label>&nbsp;&nbsp; 
				<div class="form-group">					
					<input type="number" class="form-control" id="days" name="days" value="30" min="1" max="365" style="width: 80px;" />
					<span>Tage</span>
					<button type="submit" class="btn btn-warning" onclick="return confirm(\'Wirklich alle Logs älter als X Tage löschen?\')">
					alte Logs löschen
					</button>
				</div>
				
			</form>
			
			<dl class="spacerline"></dl>
			
			<form method="post" class="form-inline">
				<input type="hidden" name="func" value="delete_all_logs" />
				<button type="submit" class="btn btn-danger" onclick="return confirm(\'ACHTUNG: Wirklich ALLE Logs löschen? Dies kann nicht rückgängig gemacht werden!\')">
					<i class="rex-icon fa-trash-o"></i> Alle Logs löschen (alte & neue Logs)
				</button>
			</form>
		</div>
	</div>';
	?>

	</div>
</div>


<dl class="spacerline"></dl>
<dl class="spacerline"></dl>


<div class="panel panel-default">
	<div class="panel-heading">
		<h3 class="panel-title"><i class="rex-icon fa-list"></i> API-Request-Historie</h3>
	</div>
	<div class="panel-body">

	<?php
	// Filter
	$filter_client = rex_request('filter_client', 'int', 0);
	$filter_status = rex_request('filter_status', 'string', '');
	$filter_days = rex_request('filter_days', 'int', 7);

	// Query zusammenbauen
	$where = [];
	$params = [];

	if ($filter_client > 0) {
		$where[] = 'l.client_key_id = ?';
		$params[] = $filter_client;
	}

	if ($filter_status === 'success') {
		$where[] = 'l.http_code = 200';
	} elseif ($filter_status === 'error') {
		$where[] = 'l.http_code >= 400';
	}

	if ($filter_days > 0) {
		/*
		$where[] = 'l.request_time > DATE_SUB(NOW(), INTERVAL ? DAY)';
		$params[] = $filter_days;
		*/
		
		$filter_days = (int) $filter_days;
		$where[] = 'l.request_time > DATE_SUB(NOW(), INTERVAL ' . $filter_days . ' DAY)';	
	}

	$where_clause = !empty($where) ? ' WHERE ' . implode(' AND ', $where) : '';

	$query = 'SELECT l.*, k.client_name 
		FROM ' . rex::getTable('1940_newssync_logs') . ' l
		LEFT JOIN ' . rex::getTable('1940_newssync_keys') . ' k ON l.client_key_id = k.id' .
		$where_clause . ' ORDER BY l.request_time DESC';



	$list = rex_list::factory(
		$query, 				//SQL-Query
		20, 					//Pagination mit max. 00 Einträgen je Seite
		'apiloglist', 			//eindeutiger Listenbezeichner
		false,					//Debug an/aus
		1,						//DB-Konnektion
		$params					//Filter-Parameter
	);

		$list->removeColumn('id');
		$list->removeColumn('client_key_id');
		$list->removeColumn('createuser');
		$list->removeColumn('updateuser');
		$list->removeColumn('createdate');
		$list->removeColumn('updatedate');

		$list->setColumnLabel('request_time', 'Zeitpunkt');
		$list->setColumnLabel('client_name', 'Client');
		$list->setColumnLabel('ip_address', 'IP-Adresse');
		$list->setColumnLabel('http_code', 'Status');
		$list->setColumnLabel('news_count', 'News');
		$list->setColumnLabel('request_id', 'Request-ID');

		$list->addTableAttribute('class', 'table-striped');



	// Filter-Formular
	echo '<div style="margin-bottom: 15px;">
		<form method="get" action="' . rex_url::currentBackendPage() . '" class="form-inline">
			<input type="hidden" name="page" value="newssync/api_logs" />
			
			<div class="form-group">
				<label for="filter_days">Zeitraum:</label>
				<select class="form-control" id="filter_days" name="filter_days">
					<option value="1"' . ($filter_days == 1 ? ' selected' : '') . '>Letzter Tag</option>
					<option value="7"' . ($filter_days == 7 ? ' selected' : '') . '>Letzte 7 Tage</option>
					<option value="30"' . ($filter_days == 30 ? ' selected' : '') . '>Letzter Monat</option>
					<option value="90"' . ($filter_days == 90 ? ' selected' : '') . '>Letzte 3 Monate</option>
					<option value="0"' . ($filter_days == 0 ? ' selected' : '') . '>Alle</option>
				</select>
			</div>
			
			<div class="form-group">
				<label for="filter_status">Status:</label>
				<select class="form-control" id="filter_status" name="filter_status">
					<option value="">Alle</option>
					<option value="success"' . ($filter_status == 'success' ? ' selected' : '') . '>✓ Erfolgreich</option>
					<option value="error"' . ($filter_status == 'error' ? ' selected' : '') . '>✗ Fehler</option>
				</select>
			</div>
			
			<div class="form-group">
				<label for="filter_client">Client:</label>
				<select class="form-control" id="filter_client" name="filter_client">
					<option value="0">Alle Clients</option>';

	// Client-Dropdown
	$clients_sql = rex_sql::factory();
	$clients_sql->setQuery('SELECT id, client_name FROM ' . rex::getTable('1940_newssync_keys') . ' ORDER BY client_name');
	foreach ($clients_sql as $row) {
		$selected = $row->getValue('id') == $filter_client ? ' selected' : '';
		echo '<option value="' . (int)$row->getValue('id') . '"' . $selected . '>' 
			. htmlspecialchars($row->getValue('client_name')) . '</option>';
	}

	echo '          </select>
			</div>
			
			<button type="submit" class="btn btn-primary">
				<i class="rex-icon fa-filter"></i> Filtern
			</button>
			
			<a href="' . rex_url::currentBackendPage() . '" class="btn btn-default">
				<i class="rex-icon fa-times"></i> Zurücksetzen
			</a>
		</form>
	</div>';



	$list->setColumnFormat('request_time', 'custom', function($params) {
		$value = htmlspecialchars($params['value']);
		return date("d.m.Y - H:i:s", strtotime($value)).' Uhr';
	});

	$list->setColumnFormat('client_name', 'custom', function($params) {
		return $params['value'] ? htmlspecialchars($params['value']) : '<em style="color:#999;">Nicht authentifiziert</em>';
	});

	$list->setColumnFormat('http_code', 'custom', function($params) {
		$code = (int)$params['value'];
		$class = 'success';
		$icon = 'check';
		
		if ($code >= 400) {
			$class = 'danger';
			$icon = 'times';
		} elseif ($code >= 300) {
			$class = 'warning';
			$icon = 'exclamation';
		}
		
		return '<span class="label label-' . $class . '">
			<i class="rex-icon fa-' . $icon . '"></i> ' . $code . '
		</span>';
	});

	$list->setColumnFormat('news_count', 'custom', function($params) {
		$count = (int)$params['value'];
		return $count > 0 ? '<strong>' . $count . '</strong>' : '<span style="color:#999;">0</span>';
	});

	$list->setColumnFormat('request_id', 'custom', function($params) {
		return '<code class="small">' . htmlspecialchars($params['value']) . '</code>';
	});

	echo $list->get();
	?>
	
	</div>
</div>