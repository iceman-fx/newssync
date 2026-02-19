<?php
/*
	Redaxo-Addon NewsSync
	Verwaltung: News-Ausgabe (Client)
	v1.0.9
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
	


// WICHTIG: Prüfen ob Host-Modus aktiv ist - dann zur Host-Seite weiterleiten
if ($isHostmode) {
    rex_response::sendRedirect(rex_url::backendPage('newssync/host'));
    exit;
}

$api_key 	= @$config['client_api_key'];
$host_url 	= @$config['client_host_url'];

if (!$api_key || !$host_url) {
    echo '<div class="alert alert-warning">
        Bitte konfiguriere erst den API-Key und die Host-URL in den 
        <a href="' . rex_url::backendPage('newssync/settings') . '">Einstellungen</a>.
    </div>';
    exit;
}

// Manuelle Synchronisation
if (rex_request('sync', 'boolean')) {
    require_once rex_path::addon('newssync', 'lib/sync.php');
    $result = newssync_perform_sync();
    
    // Session-Flag zurücksetzen damit Auto-Sync wieder funktioniert (für alle User)
    $user = rex::getUser();
    if ($user) {
        $session_key = 'newssync_auto_synced_' . $user->getId();
        unset($_SESSION[$session_key]);
    }
    
    if ($result['success']) {
        echo '<div class="alert alert-success">' . $result['message'] . '</div>';
    } else {
        echo '<div class="alert alert-danger">' . $result['message'] . '</div>';
    }
}

// Auto-Sync Status anzeigen (wenn gerade durchgeführt)
$sync_notice = null;
$user = rex::getUser();
if ($user) {
    $show_sync_notice_key = 'newssync_show_sync_notice_' . $user->getId();
    
    if (isset($_SESSION[$show_sync_notice_key])) {
        $sync_notice = $_SESSION[$show_sync_notice_key];
        unset($_SESSION[$show_sync_notice_key]); // Nur einmal anzeigen
    }
}

// CSS für Kachel-Layout und Modal
?>

<style>
.news-grid {
	--grid-layout-gap: 20px;
	--grid-column-count: 4;
	--grid-item-min-width: 320px;

	/* Calculated values */
	--gap-count: calc(var(--grid-column-count) - 1);
	
    display: grid;
	grid-template-columns: repeat(auto-fill, minmax(max(var(--grid-item-min-width), calc((100% - calc(var(--gap-count) * var(--grid-layout-gap)) ) / var(--grid-column-count)) ), 1fr));
    grid-gap: var(--grid-layout-gap);
    margin-bottom: 30px;
}

/* Modalfenster */
.news-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.8); animation: fadeIn 0.3s; }
	@keyframes fadeIn {
		from { opacity: 0; }
		to { opacity: 1; }
	}
.news-modal-content { background-color: #fff; margin: 3% auto; padding: 0; border-radius: 8px; width: 90%; max-width: 1000px; max-height: 85vh; display: flex; flex-direction: column; animation: slideIn 0.3s; }
	@keyframes slideIn {
		from { transform: translateY(-50px); opacity: 0; }
		to { transform: translateY(0); opacity: 1; }
	}
.news-modal-header { padding: 20px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
.news-modal-body { padding: 25px; overflow-y: auto; flex: 1; }
.news-modal-footer { padding: 15px 25px; background: #f9f9f9; border-top: 1px solid #eee; font-size: 13px; color: #666; flex-shrink: 0; }

.news-modal-header h3 { margin: 0; font-size: 20px; font-weight: bold; }
.news-modal-close { color: #999; font-size: 32px; font-weight: bold; line-height: 1; cursor: pointer; border: none; background: none; padding: 0; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; transition: color 0.2s; }
	.news-modal-close:hover, .news-modal-close:focus { color: #000; }

/* News-Cards */
.news-card.hidden-news { display: none; }

.news-card-inner { border: 1px solid #ddd; border-radius: 4px; background: #fff; box-shadow: 0 2px 6px rgba(0,0,0,0.1); transition: all 0.3s ease; display: flex; flex-direction: column; }
	.news-card-inner:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.15); transform: translateY(-5px); }
.news-card-header { padding: 10px 15px; font-weight: bold; font-size: 16px; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px; position: relative; }
.news-card-footer { padding: 10px 15px; background: #f9f9f9; border-top: 1px solid #eee; font-size: 12px; color: #999; display: flex; justify-content: space-between; align-items: center; }

/*.news-card:not(.expanded) .news-card-inner,*/
.news-card .news-card-header,
.news-card .news-card-footer { cursor: pointer; }

.news-card-header .badges { position: absolute; right: 15px; top: -15px; }
.news-card-header .badges > span+span { margin-left: 5px; }
.news-card-header .btn-modal-open { position: absolute; bottom: -13px; top: auto; right: 15px; display: none; }

.news-card.type-info .news-card-header { background: #d9edf7; border-left: 4px solid #31708f; }
.news-card.type-success .news-card-header { background: #dff0d8; border-left: 4px solid #3c763d; }
.news-card.type-warning .news-card-header { background: #fcf8e3; border-left: 4px solid #8a6d3b; }
.news-card.type-danger .news-card-header { background: #f2dede; border-left: 4px solid #a94442; }

.news-card-preview img, .news-card-full img { max-width: 100%; height: auto; }

.news-card-preview { padding: 15px; color: #666; flex: 1; overflow: hidden; }
.news-card-preview.collapsed { min-height: 120px; position: relative; max-height: 120px; }
	.news-card-preview.collapsed:after { content: ""; position: absolute; bottom: 0; left: 0; right: 0; height: 75px; background: linear-gradient(transparent, white); }
.news-card.expanded .news-card-preview { display: none; }

.news-card-full { padding: 15px; display: none; }
.news-card.expanded .news-card-full { display: block; max-height: 60vh; overflow: auto; min-height: 120px; }

.news-card-toggle { font-size: 11px; color: #337ab7; cursor: pointer; }
	.news-card-toggle:hover { text-decoration: underline; }

.btn-syncit { color: #FFF !important; font-size: 12px; padding: 3px 6px; margin-left: 15px; float: right; }

.load-more-container { text-align: center; margin: 30px 0; }
.load-more-container .btn { min-width: 200px; }

.status-box { min-height: 120px; display: flex; align-items: center; justify-content: center;}

.linkstyle { text-decoration: underline; }
	.linkstyle:hover { text-decoration: none; }


@media (max-width: 768px) {
    .news-grid { grid-template-columns: 1fr; }
	.news-card.expanded .news-card-full { max-height: none; overflow: visible; }
    .news-modal-content {
        width: 95%;
        margin: 5% auto;
        max-height: 90vh;
    }
}
</style>


<section class="rex-page-section">
	<div class="panel panel-default">
	
		<header class="panel-heading"><div class="panel-title"><i class="rex-icon fa-newspaper-o"></i> Aktuelle News <a href="<?php echo rex_url::currentBackendPage(['sync' => 1]); ?>" class="btn btn-primary btn-syncit"><i class="rex-icon fa-refresh"></i> Jetzt synchronisieren</a></div></header>
        
		<div class="panel-body">

			<dl class="spacerline"></dl>
			
			<?php
			$sql = rex_sql::factory();
			$sql->setQuery('
				SELECT * FROM ' . rex::getTable('1940_newssync_cache') . ' 
				WHERE (expire_date IS NULL OR expire_date = "0000-00-00 00:00:00" OR expire_date > NOW())
				ORDER BY updatedate DESC
			');

			// Letzten Besuch des Users holen für "Neu" Badge
			$user = rex::getUser();
			$last_visit_key = 'newssync_last_visit_' . ($user ? $user->getId() : 'guest');
			$last_visit = $_SESSION[$last_visit_key] ?? 0;

			// Aktuellen Besuch speichern
			$_SESSION[$last_visit_key] = time();

			// Hilfsfunktion zum Ersetzen von Medienpfaden
			function newssync_fix_media_urls($content, $host_url) {
				$host_url = rtrim($host_url, '/');
				
				// Alle src-Attribute in img, video, source und iframe Tags anpassen
				$content = preg_replace_callback(
					'/<(img|video|source|iframe)([^>]*?)src=["\'](?!https?:\/\/)([^"\']+)["\']([^>]*?)>/i',
					function($matches) use ($host_url) {
						$tag = $matches[1];
						$before = $matches[2];
						$src = $matches[3];
						$after = $matches[4];
						
						$src = (strpos($src, '/') === 0) ? $host_url . $src : $host_url . '/' . $src;
						
						return '<' . $tag . $before . 'src="' . $src . '"' . $after . '>';
					},
					$content
				);
				
				// Alle Links mit target="_blank" versehen und relative URLs korrigieren
				$content = preg_replace_callback(
					'/<a([^>]*?)>/i',
					function($matches) use ($host_url) {
						$attributes = $matches[1];
						
						// href-Attribut anpassen wenn es relativ ist
						if (preg_match('/href\s*=\s*["\']([^"\']+)["\']/', $attributes, $hrefMatch)) {
							$href = $hrefMatch[1];
							
							// Nur anpassen wenn nicht bereits eine vollständige URL (kein http://, https://, mailto:, tel:, #)
							if (!preg_match('/^(https?:\/\/|mailto:|tel:|#|javascript:)/i', $href)) {
								$new_href = (strpos($href, '/') === 0) ? $host_url . $href : $host_url . '/' . $href;
								$attributes = preg_replace('/href\s*=\s*["\'][^"\']+["\']/', 'href="' . $new_href . '"', $attributes);
							}
						}
						
						// Prüfen ob bereits ein target vorhanden ist
						if (preg_match('/target\s*=\s*["\']([^"\']*)["\']/', $attributes, $targetMatch)) {
							// Target vorhanden - auf _blank ändern wenn es nicht _blank ist
							if ($targetMatch[1] !== '_blank') {
								$attributes = preg_replace('/target\s*=\s*["\'][^"\']*["\']/', 'target="_blank"', $attributes);
							}
						} else {
							// Kein target vorhanden - hinzufügen
							$attributes .= ' target="_blank"';
						}
						
						return '<a' . $attributes . '>';
					},
					$content
				);
				
				return $content;
			}

			if ($sql->getRows() == 0) {
				echo '<div class="alert alert-info">Noch keine News vorhanden. Führen Sie eine Synchronisation durch.</div>';
			} else {
				$total_news = $sql->getRows();
				$initial_show = 8; //max. Anzahl der angezeigten News bei Start
				
				echo '<div class="news-grid">';
				
				$counter = 0;
				foreach ($sql as $row):
					$counter++;
					
					$type 		= newssync_helper::textOnly($row->getValue('type'));
					$title 		= newssync_helper::textOnly($row->getValue('title'));
					
					// Content ist bereits in API bereinigt - jetzt Medienpfade anpassen
					$content = $row->getValue('content');
					$content = newssync_fix_media_urls($content, $host_url);
					
					$preview = (mb_strlen(strip_tags($content)) > 200) ? newssync_helper::subStr($content, 200) : $content;
						
					$update_time= strtotime(newssync_helper::textOnly($row->getValue('updatedate')));
					$create_time= strtotime(newssync_helper::textOnly($row->getValue('createdate')));
					$sync_time 	= strtotime(newssync_helper::textOnly($row->getValue('synced_at')));
					$expire_date = newssync_helper::textOnly($row->getValue('expire_date'));
					
					$state 		= intval($row->getValue('state'));
					
					
					//Badges
					$time_since_create = time() - $create_time;
					$days_since_create = floor($time_since_create / 86400);
					
					// Prüfen ob News NEU ist für diesen User
					//$is_new_for_user = ($create_time > $last_visit) && ($days_since_create <= 3);
					//$is_updated = ($update_time > $sync_time - 60) && (abs($create_time - $update_time) > 60); // 60 Sek Toleranz
					
					$badges = $badges_top = '';					
						// "Neu" Badge
						if ($state == 1) {
							$badges_top .= '<span class="label label-success">Neu</span> ';
						}
						
						// "Aktualisiert" Badge
						if ($state > 1) {
							$badges_top .= '<span class="label label-warning label-updated">Aktualisiert</span> ';
						}
						
					$badges_top = (!empty($badges_top)) ? '<span class="badges">'.$badges_top.'</span>' : $badges_top;
					
					$card_id = 'news-' . $row->getValue('id');
					
					
					// Nach den ersten x News hidden-news Klasse hinzufügen
					$hidden_class = $counter > $initial_show ? ' hidden-news' : '';
					?>
					
					<div class="news-card type-<?php echo $type.$hidden_class; ?>" id="<?php echo $card_id; ?>">
						<div class="news-card-inner">
						<!--  onclick="if (!document.getElementById('<?php echo $card_id; ?>').classList.contains('expanded')) { toggleNews('<?php echo $card_id; ?>'); }" -->
							<div class="news-card-header" onclick="toggleNews('<?php echo $card_id; ?>')">
								<span style="flex: 1;"><?php echo $title; ?></span>
								<?php echo $badges.$badges_top; ?>
								
								<button class="btn btn-primary btn-syncit btn-modal-open" onclick="openNewsModal('<?php echo $card_id; ?>'); event.stopPropagation();" title="Vollbild">
									<i class="rex-icon fa-expand"></i> Vollbild
								</button>					
							</div>
							
							<div class="news-card-preview collapsed"><?php echo $preview; ?></div>
							<div class="news-card-full"><?php echo $content; ?></div>
							
							<div class="news-card-footer" onclick="toggleNews('<?php echo $card_id; ?>')">
								<span><i class="rex-icon fa-clock-o"></i> <?php echo date('d.m.Y H:i', $update_time); ?> Uhr</span>
								<span class="news-card-toggle">
									<span class="toggle-expand"><i class="rex-icon fa-chevron-down"></i> Mehr anzeigen</span>
									<span class="toggle-collapse" style="display:none;"><i class="rex-icon fa-chevron-up"></i> Weniger anzeigen</span>
								</span>
							</div>
						</div>
					</div>

					<?php
				endforeach;
				
				echo '</div>';
				
				// "Mehr laden" Button nur anzeigen wenn mehr als 8 News vorhanden
				if ($total_news > $initial_show) {
					echo '<div class="load-more-container">
						<button class="btn btn-primary" id="load-more-btn" onclick="loadMoreNews()">
							<i class="rex-icon fa-chevron-down"></i> Mehr laden (' . ($total_news - $initial_show) . ' weitere News)
						</button>
					</div>';
				}
				
				// Modal HTML
				?>
				
				<div id="newsModal" class="news-modal" onclick="closeNewsModal(event)">
					<div class="news-modal-content" onclick="event.stopPropagation()">
						<div class="news-modal-header">
							<h3 id="modalTitle"></h3>
							<button class="news-modal-close" onclick="closeNewsModal()">&times;</button>
						</div>
						<div class="news-modal-body" id="modalBody"></div>
						<div class="news-modal-footer" id="modalFooter"></div>
					</div>
				</div>
				
				<?php
			}
			?>
        
		</div>
	</div>
</section>

<script>
function toggleNews(cardId) {
    var $card = $('#' + cardId);
    var isExpanded = $card.hasClass("expanded");
    
    if (isExpanded) {
        $card.removeClass("expanded");
        $card.find(".toggle-expand").css("display", "inline");
        $card.find(".toggle-collapse").css("display", "none");
        $card.find(".btn-modal-open").css("display", "none");
    } else {
        $card.addClass("expanded");
        $card.find(".toggle-expand").css("display", "none");
        $card.find(".toggle-collapse").css("display", "inline");
        $card.find(".btn-modal-open").css("display", "inline-block");
    }
    
    event.stopPropagation();
}

function loadMoreNews() {
    var $hiddenCards = $(".news-card.hidden-news");
    var $loadMoreBtn = $("#load-more-btn");
    
    $hiddenCards.removeClass("hidden-news");
    
    if ($loadMoreBtn.length) {
        $loadMoreBtn.parent().remove();
    }
}

function openNewsModal(cardId) {
    var $card = $('#' + cardId);
    var $modal = $("#newsModal");
    
    // Titel, Content und Footer holen
    var title = $card.find(".news-card-header span").html();
    var content = $card.find(".news-card-full").html();
    var footer = $card.find(".news-card-footer span:first-child").html();
    
    // Modal befüllen
    $("#modalTitle").html(title);
    $("#modalBody").html(content);
    $("#modalFooter").html(footer);
    
    // Modal anzeigen
    $modal.css("display", "block");
    $("body").css("overflow", "hidden");
}

function closeNewsModal(event) {
    var $modal = $("#newsModal");
		$modal.css("display", "none");
    $("body").css("overflow", "auto");
}

// ESC-Taste zum Schließen
$(document).on("keydown", function(event) {
    if (event.key === "Escape") { closeNewsModal(); }
});
</script>


<?php
// Alle News-Badges entfernen bei Seitenaufruf, wenn älter als 1 Tag
$sql = rex_sql::factory();
$sql->setQuery('UPDATE ' . rex::getTable('1940_newssync_cache') . ' SET state = 0 WHERE state >= 1 AND synced_at < DATE_SUB(NOW(), INTERVAL 1 DAY)');


// Auto-Sync Status anzeigen UNTERHALB der News (wenn gerade durchgeführt)
/*
if ($sync_notice) {
    $auto_text = isset($sync_notice['auto']) && $sync_notice['auto'] ? ' (automatisch)' : '';
    if ($sync_notice['success']) {
        echo '<div class="alert alert-success" style="margin-top: 20px;">
            <strong><i class="rex-icon fa-check-circle"></i> Synchronisation erfolgreich' . $auto_text . '</strong><br>
            ' . htmlspecialchars($sync_notice['message']) . '
        </div>';
    } else {
        echo '<div class="alert alert-warning" style="margin-top: 20px;">
            <strong><i class="rex-icon fa-exclamation-triangle"></i> Synchronisation fehlgeschlagen' . $auto_text . '</strong><br>
            ' . htmlspecialchars($sync_notice['message']) . '
        </div>';
    }
}
*/

// Sync-Statistiken holen
$last_sync 			= rex_config::get('newssync', 'last_sync', '');
$last_sync_stats 	= rex_config::get('newssync', 'last_sync_stats', []);
$sync_error 		= rex_config::get('newssync', 'last_sync_error', '');

// Anzahl lokaler News
$sql = rex_sql::factory();
$sql->setQuery('SELECT COUNT(*) as count FROM ' . rex::getTable('1940_newssync_cache'));
$total_news = $sql->getValue('count');

// Prüfen ob User Admin ist
$is_admin = rex::getUser() && rex::getUser()->isAdmin();

// Badge in Hauptnavigation für neue News löschen (User hat Seite aufgerufen)
// Wird bereits oben erledigt durch $_SESSION[$last_visit_key] = time()
$user = rex::getUser();
if ($user) {
    $badge_key = 'newssync_show_badge_' . $user->getId();
    unset($_SESSION[$badge_key]);
}

// Berechne Zeit seit letztem Sync
$sync_age = '';
$sync_age_class = 'info';
if ($last_sync) {
    $last_sync_time = strtotime($last_sync);
    $minutes_ago = floor((time() - $last_sync_time) / 60);
    
    if ($minutes_ago < 1) {
        $sync_age = 'gerade eben';
        $sync_age_class = 'success';
    } elseif ($minutes_ago < 60) {
        $sync_age = 'vor ' . $minutes_ago . ' Minute' . ($minutes_ago > 1 ? 'n' : '');
        $sync_age_class = $minutes_ago < 30 ? 'success' : 'info';
    } elseif ($minutes_ago < 1440) {
        $hours = floor($minutes_ago / 60);
        $sync_age = 'vor ' . $hours . ' Stunde' . ($hours > 1 ? 'n' : '');
        $sync_age_class = $hours < 6 ? 'info' : 'warning';
    } else {
        $days = floor($minutes_ago / 1440);
        $sync_age = 'vor ' . $days . ' Tag' . ($days > 1 ? 'en' : '');
        $sync_age_class = 'danger';
    }
}



// Sync-Status Dashboard
?>

<dl class="spacerline"></dl>
<dl class="spacerline"></dl>


<section class="rex-page-section">
	<div class="panel panel-default">
	
		<header class="panel-heading">
			<div class="panel-title"><i class="rex-icon fa-sync"></i> Sync-Status &nbsp; (<a class="linkstyle cur-p" data-toggle="collapse" data-target="#syncstatus">anzeigen/ausblenden</a>)</div>
		</header>
        
		<div class="panel-body collapse" id="syncstatus">
		
			<div class="row">
			
				<div class="col-sm-3">
					<div class="panel panel-<?php echo $sync_age_class; ?> status-box">
						<div class="panel-body text-center">
							<p>Letzte Synchronisation</p>
							<h4>
								<?php echo ($last_sync ? '<strong>' . $sync_age . '</strong><br><small>' . date('d.m.Y H:i:s', strtotime($last_sync)) . ' Uhr</small>' : '<strong><em>Noch nie</em></strong>'); ?>
							</h4>
						</div>
					</div>
				</div>
				
				<div class="col-sm-3">
					<div class="panel panel-info status-box">
						<div class="panel-body text-center">
							<p style="margin-top: 0;">Gespeicherte News</p>
							<h4><strong><?php echo $total_news; ?></strong></h4>
						</div>
					</div>
				</div>
				
				<?php if ($is_admin): ?>
				<div class="col-sm-6">
					<div class="panel panel-default">
						<div class="panel-body">
							<h4 style="margin-top: 0;">Letzte Synchronisation</h4>

							<?php if (!empty($last_sync_stats)): ?>
								<table class="table table-condensed" style="margin: 0;">
									<tr>
										<td><i class="rex-icon fa-plus text-success"></i> Neu hinzugefügt:</td>
										<td><strong><?php echo (int)($last_sync_stats['new'] ?? 0); ?></strong></td>
									</tr>
									<tr>
										<td><i class="rex-icon fa-edit text-info"></i> Aktualisiert:</td>
										<td><strong><?php echo (int)($last_sync_stats['updated'] ?? 0); ?></strong></td>
									</tr>
									<tr>
										<td><i class="rex-icon fa-trash text-danger"></i> Gelöscht:</td>
										<td><strong><?php echo (int)($last_sync_stats['deleted'] ?? 0); ?></strong></td>
									</tr>
									<tr>
										<td><i class="rex-icon fa-exclamation-triangle text-warning"></i> Übersprungen:</td>
										<td><strong><?php echo (int)($last_sync_stats['skipped'] ?? 0); ?></strong></td>
									</tr>
								</table>
							
							<?php else: ?>
							
								<p><em>Keine Statistiken verfügbar</em></p>
								
							<?php endif; ?>
							

						</div>
					</div>
				</div>
				<?php endif; ?>
		
			</div>
			
			
			<?php if ($sync_error): ?>
			<dl class="spacerline"></dl>
			
			<div class="alert alert-danger">
				<strong><i class="rex-icon fa-exclamation-circle"></i> Letzter Fehler:</strong><br>
				<?php echo htmlspecialchars($sync_error); ?>
			</div>
			<?php endif; ?>

			<span class="text-muted">
				<i class="rex-icon fa-info-circle"></i> 
				Automatische Synchronisation alle <?php echo @$config['auto_sync_interval']; ?> Stunde(n)
			</span>
			
		</div>
	</div>
</section>
			