<?php
/*
	Redaxo-Addon NewsSync
	Verwaltung: Einstellungen (config)
	v1.0.8
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


//Variablen deklarieren
$mypage = $this->getProperty('package');

$page = rex_request('page', 'string');
$subpage = rex_be_controller::getCurrentPagePart(2);
	$tmp = rex_request('subpage', 'string');
	$subpage = (!empty($tmp)) ? $tmp : $subpage;
$func = rex_request('func', 'string');

$form_error = 0;


//Userrechte prüfen
$isAdmin = ( is_object(rex::getUser()) AND (rex::getUser()->hasPerm($mypage.'[admin]') OR rex::getUser()->isAdmin()) ) ? true : false;


//Formular dieser Seite verarbeiten
if ($func == "save" && isset($_POST['submit'])):
	$oldConfig = $this->getConfig('config');

	//Konfig speichern
	$configData = [
        'host_mode'					=> rex_post('host_mode'),
        'client_api_key'			=> rex_post('client_api_key'),
		'client_host_url'			=> rtrim(rex_post('client_host_url'), '/'),  // Trailing Slash entfernen
		
		'auto_sync_interval'		=> rex_post('auto_sync_interval', 'int'),
		'auto_sync_enabled'			=> rex_post('auto_sync_enabled', 'int'),
		
		'custom_title'				=> newssync_helper::textOnly(rex_post('custom_title')),
		'systemlog'					=> rex_post('systemlog'),
		
		'enable_logging'			=> rex_post('enable_logging'),
		'log_retention_months'		=> rex_post('log_retention_months', 'int'),
		
		'editor_class'				=> rex_post('editor_class'),
		'editor_data'				=> rex_post('editor_data'),
	];
	

	// Custom Title nur im Host-Modus speichern, sonst vom Host übernehmen
	if (rex_post('host_mode')) {
		$configData['custom_title'] = newssync_helper::textOnly(rex_post('custom_title'));
	} else {
		// Im Client-Modus: Behalte den vom Host synchronisierten Wert
		$configData['custom_title'] = @$oldConfig['custom_title'];
	}
	
	$res = $this->setConfig('config', $configData);
	
	
	// Host-spezifische Config-Werte separat speichern
	rex_config::set('newssync', 'rate_limit_per_minute', rex_post('rate_limit_per_minute', 'int', 60));
	rex_config::set('newssync', 'max_news_per_request', rex_post('max_news_per_request', 'int', 1000));

	
	//Rückmeldung
	echo ($res) ? rex_view::info($this->i18n('a1940_settings_saved')) : rex_view::warning($this->i18n('a1940_error'));
	
	
    //Logs löschen
    if (rex_post('enable_logging') != 1):
        $sql = rex_sql::factory();
        $sql->setQuery('TRUNCATE TABLE '.rex::getTable('1940_newssync_logs'));
        
		echo rex_view::info($this->i18n('a1940_logscleared'));		//API-Logging wurde deaktiviert und alle vorhandenen Logs gelöscht.
	endif;
endif;


//load Config
$config = $this->getConfig('config');


//Formular ausgeben
$isHostmode 	= (int)@$config['host_mode'];
$logEnabled 	= (int)@$config['enable_logging'];

$logRetention 	= (int)@$config['log_retention_months'];
	$logRetention = ($logRetention < 1) ? 3 : $logRetention;
$syncInterval 	= (int)@$config['auto_sync_interval'];
	$syncInterval = ($syncInterval < 1) ? 6 : $syncInterval;
	
?>

<style type="text/css">
.settings-section { margin-bottom: 30px; }
.settings-section .panel { border-left: 4px solid #3c8dbc; }
.settings-section .panel-heading { background: linear-gradient(to right, #f8f9fa, #ffffff); font-weight: 600; color: #000; }

.mode-switch {
    padding: 20px;
    background: #f8f9fa;
    border-radius: 4px;
    border-left: 4px solid #17a2b8;
}
.mode-switch label {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 0;
}
.mode-indicator {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 3px;
    font-size: 12px;
    font-weight: bold;
}
.mode-indicator.host {
    background: #324050;
    color: white;
}
.mode-indicator.client {
    background: #4b9ad9;
    color: white;
}

.form-group-enhanced label.control-label { font-weight: 600; color: #333; }

.help-block-enhanced {
    background: #f8f9fa;
    padding: 8px 12px;
    border-radius: 3px;
    border-left: 3px solid #17a2b8;
    margin-top: 8px;
}

.input-icon {
    position: relative;
}

.input-icon .rex-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #999;
}

.input-icon .form-control {
    padding-left: 38px;
}

.save-button-group {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 4px;
    margin-top: 30px;
    text-align: center;
}

/* NEU: CSS für Highlight-Animation */
.field-highlight {
    animation: fieldHighlight 2s ease;
}

@keyframes fieldHighlight {
    0% {
        background-color: #d4edda;
        border-color: #28a745;
    }
    100% {
        background-color: #fff;
        border-color: #7586a0;
    }
}

#paste-config-btn {
    transition: all 0.3s ease;
}
</style>


<script>
// Nur SUCCESS/ERROR Alerts ausblenden, nicht die INFO-Box mit dem Import-Button
setTimeout(function() { 
    jQuery('.alert-success, .alert-danger, .alert-warning').not('.alert-persistent').fadeOut(); 
}, 5000);
</script>


<form action="index.php?page=<?php echo $page; ?>" method="post" enctype="multipart/form-data">
<input type="hidden" name="subpage" value="<?php echo $subpage; ?>" />
<input type="hidden" name="func" value="save" />

<section class="rex-page-section">
	<div class="panel panel-edit">
	
		<header class="panel-heading"><div class="panel-title"><?php echo $this->i18n('a1940_head_config'); ?></div></header>
		
		<div class="panel-body">
		
            <!-- Host/Client Modus -->
			<dl class="rex-form-group form-group">
				<dt>
					<label for=""><?php echo $this->i18n('a1940_config_mode'); ?></label>
					<span class="mode-indicator <?php echo ($isHostmode ? 'host' : 'client'); ?>" id="mode_indicator"><?php echo ($isHostmode ? '<i class="rex-icon fa-server"></i>️ HOST-MODUS' : '<i class="fa-solid fa-desktop"></i> CLIENT-MODUS'); ?></span>
				</dt>
				<dd>
					<div class="checkbox toggle">
						<label for="host_mode">
							<input type="checkbox" name="host_mode" id="host_mode" value="1" <?php echo ($isHostmode ? 'checked' : ''); ?> /> Host-Modus aktivieren
						</label>
					</div>
					<span class="infoblock">
						<strong>Host-Modus:</strong> Dieses System stellt News für andere Systeme bereit.<br>
						<strong>Client-Modus:</strong> Dieses System empfängt News vom Host-System.
					</span>
					
					
					<dl class="spacerline"></dl>
					<dl class="spacerline"></dl>
					<dl class="spacerline"></dl>
					
					
					<div class="settings-section" id="host-settings" style="<?php echo (!$isHostmode ? 'display:none;' : ''); ?>">
						<div class="panel panel-info">
						
							<div class="panel-heading"><strong>Host-Konfiguration</strong></div>
							<div class="panel-body bg-white">
							
								<!-- API Rate Limiting -->
								<dl class="rex-form-group form-group">
									<dt><label for="rate_limit_per_minute">API-Anfragelimit</label></dt>
									<dd>
										<div class="input-group">
											<input class="form-control" type="number" id="rate_limit_per_minute" name="rate_limit_per_minute" 
												   value="<?php echo (int)rex_config::get('newssync', 'rate_limit_per_minute', 20); ?>" 
												   min="5" max="300" step="5" />
											<span class="input-group-addon"><strong>Anfragen pro Minute</strong></span>
										</div>
										
										<span class="infoblock">
											Maximale Anzahl API-Anfragen <strong>pro Minute</strong> & <strong>pro IP-Adresse</strong>.<br>
											<strong>Empfehlung:</strong> 20 für normale Nutzung, 60+ für viele Abfragen (manuell + stündlich).
										</span>
									</dd>
								</dl>
								
								
								<!-- Max News per Request -->
								<dl class="rex-form-group form-group">
									<dt><label for="max_news_per_request">Max. News pro Abfrage</label></dt>
									<dd>
										<div class="input-group">
											<input class="form-control" type="number" id="max_news_per_request" name="max_news_per_request" 
												   value="<?php echo (int)rex_config::get('newssync', 'max_news_per_request', 100); ?>" 
												   min="50" max="5000" step="50" />
											<span class="input-group-addon"><strong>News</strong></span>
										</div>
										
										<span class="infoblock">
											Maximale Anzahl News in einer API-Abfrage.<br>
											<strong>Empfehlung:</strong> 100 für Standard, 50 bei langsamen Servern, 1000+ für leistungsstarke Server.
										</span>
									</dd>
								</dl>
								
								
								<dl class="spacerline"></dl>
								
								
								<!-- API-Logging -->
								<dl class="rex-form-group form-group">
									<dt><label for="">API-Request-Logging</label></dt>
									<dd>
										<label>
											<input type="checkbox" name="enable_logging" value="1" id="enable_logging" <?php echo ($logEnabled ? 'checked' : ''); ?> />
											<strong>API-Request-Logging aktivieren</strong>
										</label>
										
										<span class="infoblock">
											Protokolliert alle API-Anfragen in einer separaten Datenbanktabelle für Audit-Trail und Statistiken.<br>
											Die Logs können unter <strong>API-Logs</strong> eingesehen werden.
										</span>
										
										
										<?php if ($logEnabled): ?>
										<dl class="spacerline"></dl>
										<div class="alert alert-warning">
											<i class="rex-icon fa-exclamation-triangle"></i> Beim Deaktivieren werden alle vorhandenen Logs gelöscht!
										</div>
										<?php endif; ?>
										
										
										<div id="api-logging-group">
											<dl class="spacerline"></dl>
											
											<label for="log_retention_months"><i class="rex-icon fa-clock-o"></i> Log-Aufbewahrung</label>
											<div class="input-group" style="max-width: 300px;">
												<input class="form-control" type="number" id="log_retention_months" name="log_retention_months" value="<?php echo (int)$logRetention; ?>" min="1" max="12" step="1" />
												<span class="input-group-addon"><strong>Monate</strong></span>
											</div>
											
											<span class="infoblock">
												Logs, die älter als die angegebene Zeitspanne sind, werden automatisch gelöscht.<br>
												<strong>Empfehlung:</strong> 3 Monate für normale Nutzung, 6-12 Monate für Compliance-Anforderungen.
											</span>
											
										</div>
										
									</dd>
								</dl>

								
								<dl class="spacerline"></dl>
								

								<dl class="rex-form-group form-group">
									<dt><label for="">Fließtext / WYSIWYG-Editor</label></dt>
									<dd>
										<label><?php echo $this->i18n('a1940_config_editor'); ?></label>
									
										<div class="mb-fieldset-inline">

											<dl class="w300">
												<dt><?php echo $this->i18n('a1940_config_editor_class'); ?></dt>
												<dd>
													<input type="text" size="25" name="editor_class" id="editor_class" value="<?php echo newssync_helper::maskChar(@$config['editor_class']); ?>" maxlength="200" class="form-control" placeholder="<?php echo $this->i18n('a1940_config_editor_class_example'); ?>" />
												</dd>
											</dl>


											<dl class="w300">
												<dt><?php echo $this->i18n('a1940_config_editor_data'); ?></dt>
												<dd>
													<input type="text" size="25" name="editor_data" id="editor_data" value="<?php echo newssync_helper::maskChar(@$config['editor_data']); ?>" maxlength="200" class="form-control" placeholder="<?php echo $this->i18n('a1940_config_editor_data_example'); ?>" />
												</dd>
											</dl>

										</div>

									</dd>
								</dl>								
								
							</div>
						</div>
					</div>
					

					<div class="settings-section" id="client-settings" style="<?php echo ($isHostmode ? 'display:none;' : ''); ?>">
						<div class="panel panel-info">
							<div class="panel-heading"><strong class="panel-title">Client-Konfiguration</strong></div>
							<div class="panel-body bg-white">
							
								<!-- NEU: Import-Button - nur anzeigen wenn Felder leer sind -->
								<?php if (empty(@$config['client_host_url']) && empty(@$config['client_api_key'])): ?>
								<div class="alert alert-info alert-persistent" style="margin-bottom: 20px;">
									<i class="rex-icon fa-info-circle"></i>
									<strong>Import der Konfiguration:</strong> 									
									<button type="button" class="btn btn-sm btn-success" id="paste-config-btn" style="margin-left: 10px;">
										<i class="rex-icon fa-paste"></i> Konfiguration aus Zwischenablage einfügen
									</button>
								</div>
								<?php endif; ?>
							
								<dl class="rex-form-group form-group">
									<dt><label for="client_host_url">Host-URL *</label></dt>
									<dd>
										<input class="form-control" type="url" id="client_host_url" name="client_host_url" value="<?php echo htmlspecialchars(@$config['client_host_url']); ?>" placeholder="https://host-domain.de" />
										
										<span class="infoblock">
											Die vollständige URL des Host-Systems (ohne abschließenden Slash).<br>
											<strong>Beispiel:</strong> https://news.unternehmen.de
										</span>
									</dd>
								</dl>
							
							
								<dl class="rex-form-group form-group">
									<dt><label for="client_api_key">API-Key *</label></dt>
									<dd>
										<input class="form-control" type="text" id="client_api_key" name="client_api_key" value="<?php echo htmlspecialchars(@$config['client_api_key']); ?>" placeholder="Dein 64-stelliger API-Key vom Host-Administrator" />
										
										<span class="infoblock">
											Der API-Key wird vom Host-Administrator erstellt und ist 64 Zeichen lang.<br>
											<strong>Wichtig:</strong> Behandle diesen Key wie ein Passwort!
										</span>
									</dd>
								</dl>
								
								
								<dl class="spacerline"></dl>
								
								
								<dl class="rex-form-group form-group">
									<dt><label for="auto_sync_enabled">Auto-Sync</label></dt>
									<dd>
										<div class="checkbox">
											<label>
												<input type="checkbox" name="auto_sync_enabled" value="1" id="auto_sync_enabled" <?php echo (@$config['auto_sync_enabled'] ? 'checked' : ''); ?> />
												<strong><i class="rex-icon fa-refresh"></i> Automatische Synchronisation aktivieren</strong>
											</label>
										</div>

										<div id="auto-sync-interval-group">
											<dl class="spacerline"></dl>
											
											<label for="auto_sync_interval"><i class="rex-icon fa-clock-o"></i> Sync-Intervall</label>
											<div class="input-group" style="max-width: 300px;">
												<input class="form-control" type="number" id="auto_sync_interval" name="auto_sync_interval" 
													   value="<?php echo (int)$syncInterval; ?>" 
													   min="1" max="24" step="1" />
												<span class="input-group-addon"><strong>Stunden</strong></span>
											</div>
											
											<span class="infoblock">
												News werden automatisch synchronisiert, wenn der letzte Sync länger zurückliegt.<br>
												<strong>Empfehlung:</strong> 1-3 Stunden für häufige Updates, 6-12 Stunden für gelegentliche Updates.
											</span>											
										</div>
										
										<?php 
										$last_sync = rex_config::get('newssync', 'last_sync', '');
										
										if ($last_sync) {
											$minutes_ago = floor((time() - strtotime($last_sync)) / 60);
											if ($minutes_ago < 60) {
												$time_label = '<span class="label label-success">vor ' . $minutes_ago . ' Minute' . ($minutes_ago > 1 ? 'n' : '') . '</span>';
											} elseif ($minutes_ago < 1440) {
												$hours = floor($minutes_ago / 60);
												$time_label = '<span class="label label-info">vor ' . $hours . ' Stunde' . ($hours > 1 ? 'n' : '') . '</span>';
											} else {
												$days = floor($minutes_ago / 1440);
												$time_label = '<span class="label label-warning">vor ' . $days . ' Tag' . ($days > 1 ? 'en' : '') . '</span>';
											}
											
											echo '<div style="margin-top: 20px;">
												<i class="rex-icon fa-info-circle"></i>
												<strong>Letzte Synchronisation:</strong> '.$time_label.' '.date('d.m.Y H:i:s', strtotime($last_sync)).'</div>';
										}
										?>
									
									</dd>
								</dl>
								
							</div>
						</div>
					</div>						
					
					
				</dd>
			</dl>
			
			
			<dl class="rex-form-group form-group">
				<dt>System-LOG Meldungen</dt>
				<dd>			
			
					<div class="checkbox toggle">
						<label for="systemlog">
							<input type="checkbox" name="systemlog" id="systemlog" value="1" <?php echo (@$config['systemlog'] ? 'checked' : ''); ?> /> System-LOG & Debug-Meldungen aktivieren
						</label>
						<span class="infoblock">
							<strong>Hinweis:</strong> Fehler- und Sicherheitsmeldungen werden immer im System-LOG ausgegeben!
						</span>
					</div>
				</dd>
			</dl>


			<dl class="spacerline"></dl>

            
            <!-- Sonstiges -->
			<?php if ($isHostmode): ?>
            <legend><?php echo $this->i18n('a1940_subheader_config2'); ?> &nbsp; (<a class="cur-p" data-toggle="collapse" data-target="#options2"><?php echo $this->i18n('a1940_showbox'); ?></a>)</legend>
			<div id="options2" class="collapse">

				<!-- Custom Title nur im Host-Modus editierbar !!! -->
				<dl class="rex-form-group form-group">
					<dt><?php echo $this->i18n('a1940_config_custom_title'); ?></dt>
					<dd>
						<input name="custom_title" type="text" class="form-control" value="<?php echo @$config['custom_title']; ?>" maxlength="50" placeholder="<?php echo $this->i18n('a1940_config_custom_title_default').' '.$this->i18n('a1940_defaultTitle'); ?>" />
						
						<span class="infoblock">
							Der hier gesetzte Name wird automatisch auch an alle Clients übertragen.
						</span>						
					</dd>
				</dl>
				
				
				<dl class="spacerline"></dl>

			</div>
			
			<?php else: ?>
			
			<input type="hidden" name="custom_title" value="<?php echo !empty(@$config['custom_title']) ? @$config['custom_title'] : $this->i18n('a1940_defaultTitle'); ?>" />
			
			<?php endif; ?>

		</div>
                
		
		<footer class="panel-footer">
			<div class="rex-form-panel-footer">
				<div class="btn-toolbar">
					<input class="btn btn-save rex-form-aligned" type="submit" name="submit" title="<?php echo $this->i18n('a1940_save'); ?>" value="<?php echo $this->i18n('a1940_save'); ?>" />
				</div>
			</div>
		</footer>
		
	</div>
</section>
	
</form>



<script>
jQuery(function($) {
    // NEU: Paste-Config-Button Handler - DIREKTER Zugriff auf Zwischenablage
    $("#paste-config-btn").click(function() {
        var $btn = $(this);
        var originalHtml = $btn.html();
        
        // Button Status auf "Laden..."
        $btn.html('<i class="rex-icon fa-spinner fa-spin"></i> Lese Zwischenablage...').prop('disabled', true);
        
        // DIREKTER Zugriff auf Zwischenablage (kein Prompt mehr!)
        if (navigator.clipboard && navigator.clipboard.readText) {
            navigator.clipboard.readText()
                .then(function(text) {
                    processConfigData(text, $btn, originalHtml);
                })
                .catch(function(err) {
                    // Nur bei Fehler (z.B. keine Berechtigung) Prompt als Fallback
                    $btn.html(originalHtml).prop('disabled', false);
                    
                    if (confirm("Automatischer Zugriff auf Zwischenablage fehlgeschlagen.\n\nMöchten Sie die Daten manuell einfügen?")) {
                        var text = prompt("Bitte fügen Sie die Konfigurationsdaten ein (JSON-Format):");
                        if (text) {
                            processConfigData(text, $btn, originalHtml);
                        }
                    }
                });
        } else {
            // Browser unterstützt kein Clipboard API - Fallback zu Prompt
            $btn.html(originalHtml).prop('disabled', false);
            var text = prompt("Ihr Browser unterstützt keinen direkten Zugriff auf die Zwischenablage.\n\nBitte fügen Sie die Daten manuell ein:");
            if (text) {
                processConfigData(text, $btn, originalHtml);
            }
        }
    });
    
    function processConfigData(text, $btn, originalHtml) {
        try {
            // JSON parsen
            var config = JSON.parse(text);
            
            // Validierung
            if (!config.api_key || !config.host_url) {
                throw new Error("Ungültiges Format: api_key oder host_url fehlt");
            }
            
            // API-Key Format prüfen (64 Zeichen Hex)
            if (!/^[a-f0-9]{64}$/i.test(config.api_key)) {
                throw new Error("Ungültiges API-Key Format (muss 64 Hex-Zeichen sein)");
            }
            
            // Host-URL validieren und Trailing Slash entfernen
            try {
                new URL(config.host_url);
                config.host_url = config.host_url.replace(/\/$/, '');
            } catch (e) {
                throw new Error("Ungültige Host-URL");
            }
            
            // Werte in Felder eintragen
            $("#client_host_url").val(config.host_url).addClass("field-highlight");
            $("#client_api_key").val(config.api_key).addClass("field-highlight");
            
            // Success-Feedback
            $btn.html('<i class="rex-icon fa-check"></i> Erfolgreich eingefügt!').addClass("btn-success").prop('disabled', false);
            
            // Highlight-Animation für eingefügte Felder
            setTimeout(function() {
                $("#client_host_url, #client_api_key").removeClass("field-highlight");
            }, 2000);
            
            // Button nach 3 Sekunden zurücksetzen
            setTimeout(function() {
                $btn.html(originalHtml).removeClass("btn-success");
            }, 3000);
            
        } catch (e) {
            $btn.html(originalHtml).prop('disabled', false);
            alert("Fehler beim Importieren der Konfiguration:\n\n" + e.message + "\n\nBitte stellen Sie sicher, dass Sie die Daten vom Host-Administrator korrekt kopiert haben.");
            console.error("Config Import Error:", e);
        }
    }
    
    // Mode-Switch Animation
    $("#host_mode").change(function() {
        var isHost = $(this).is(":checked");
        var indicator = $("#mode_indicator");
        
        if (isHost) {
            $("#client-settings").slideUp(300);
            $("#host-settings").slideDown(300);
            indicator.removeClass("client").addClass("host").html('<i class="rex-icon fa-server"></i>️ HOST-MODUS');
        } else {
            $("#client-settings").slideDown(300);
            $("#host-settings").slideUp(300);
            indicator.removeClass("host").addClass("client").html('<i class="fa-solid fa-desktop"></i> CLIENT-MODUS');
        }
    });
    
    // Logging Checkbox Toggle
    $("#enable_logging").change(function() {
        if ($(this).is(":checked")) {
            $("#log-retention-group").slideDown(200);
        } else {
            $("#log-retention-group").slideUp(200);
        }
    }).trigger("change");
    
    // Auto-Sync Checkbox Toggle
    $("#auto_sync_enabled").change(function() {
        if ($(this).is(":checked")) {
            $("#auto-sync-interval-group").slideDown(200);
        } else {
            $("#auto-sync-interval-group").slideUp(200);
        }
    }).trigger("change");
     
    // Api-logging Checkbox Toggle
    $("#enable_logging").change(function() {
        if ($(this).is(":checked")) {
            $("#api-logging-group").slideDown(200);
        } else {
            $("#api-logging-group").slideUp(200);
        }
    }).trigger("change");
   
    // Input-Validierung
    $("#client_host_url").on("blur", function() {
        var url = $(this).val().trim();
        if (url && !url.match(/^https?:\/\//)) {
            $(this).val("https://" + url);
        }
        // Trailing Slash entfernen
        $(this).val($(this).val().replace(/\/$/, ""));
    });
});
</script>