<?php
/*
	Redaxo-Addon NewsSync
	Verwaltung: index
	v1.0.7
	by Falko Müller @ 2026
*/

/** RexStan: Vars vom Check ausschließen */
/** @var rex_addon $this */
/** @var array $config */
/** @var string $func */
/** @var string $page */
/** @var string $subpage */


$addon = rex_addon::get('newssync');
$mypage = $this->getProperty('package');
$config = $this->getConfig('config');

//dump($this->getConfig());

// Custom Title für Header
$custom_title 	= @$config['custom_title'];
$host_mode 		= (int)@$config['host_mode'];

$display_title 	= (!empty($custom_title)) ? $custom_title : $this->i18n('a1940_defaultTitle');
	// Host-Modus Indikator hinzufügen
	if ($host_mode) {
		$display_title .= ' '.$this->i18n('a1940_defaultTitle_ishost');
	}

echo rex_view::title($display_title . '<span class="addonversion">' . $addon->getProperty('version') . '</span>');
?>


<style type="text/css">
input.rex-form-submit { margin-left: 190px !important; }	/* Rex-Button auf neue (Labelbreite +10) verschieben */
td.name { position: relative; padding-right: 20px !important; }
.nowidth { width: auto !important; }
.togglebox { display: none; margin-top: 8px; font-size: 90%; color: #666; line-height: 130%; }
.toggler { width: 15px; height: 12px; position: absolute; top: 10px; right: 3px; }
.toggler a { display: block; height: 11px; background-image: url(../assets/addons/<?php echo $mypage; ?>/arrows.png); background-repeat: no-repeat; background-position: center -6px; cursor: pointer; }
.required { font-weight: bold; }
.nobold { font-weight: normal; }
.inlinelabel { display: inline !important; width: auto !important; float: none !important; clear: none !important; padding: 0px  !important; margin: 0px !important; font-weight: normal !important; }
.inlineform { display: inline-block !important; }
.form_auto { width: auto !important; }
.form_plz { width: 25%px !important; margin-right: 6px; }
.form_ort { width: 73%px !important; }
.form_25perc { width: 25% !important; min-width: 120px; }
.form_50perc { width: 50% !important; min-width: 120px; }
.form_75perc { width: 75% !important; }
.form_content { display: block; padding-top: 5px; }
.form_readonly { background-color: #EEE; color: #999; }
.form_isoffline { color: #A00; }
.addonversion { margin-left: 7px; }
.radio label, .checkbox label { margin-right: 20px; }
.spacerline { display: block; height: 7px; margin-bottom: 15px; }
.cur-p { cursor: pointer; }
.cur-d { cursor: default; }

.bg-white { background-color: #FFF !important; }

textarea.onlyvertical { resize: vertical; }
.form_2spaltig > div { display: inline-block; width: 49%; }

.datepicker-widget { display: inline-block; vertical-align: middle; /*margin-right: 10px;*/ }
	.datepicker-widget-spacer { display: inline-block; vertical-align: middle; padding: 0px 5px 0px 15px; }
.daterangepicker { box-shadow: 3px 3px 10px 0px rgb(0,0,0, 0.2); }
.daterangepicker .calendar-table th, .daterangepicker .calendar-table td { padding: 2px; /*line-height: 20px;*/ }
@media (max-width: 768px){ .datepicker-widget { margin-right: 0px; } .datepicker-widget-spacer { display: block; margin-top: 7px; padding: 0px; } }

.addon_failed, .addonfailed { color: #F00; font-weight: bold; margin-bottom: 15px; }
.addon_search { width: 100%; background-color: #EEE; }
.addon_search .searchholder { position: relative; display: inline-block; }
	.addon_search .searchholder a { position: absolute; top: 0px; right: 0px; bottom: 0px; cursor: pointer; padding: 5px 3px 0px; }
		.addon_search .searchholder img { vertical-align: top; }
	@-moz-document url-prefix('') { .addon_search .searchholder a { top: 0px; } /* FF-only */ }
.addon_search .border-top { border-top: 1px solid #DFE9E9; }
.addon_search td { width: 46%; padding: 9px !important; font-size: 90%; color: #333; border: none !important; vertical-align: top !important; }
	.addon_search td.td2 { width: 8%; text-align: center; }
	.addon_search td.td3 { text-align: right; }

.addon_search .form-control { padding: 1px 8px; font-size: 13px; float: none; }
.addon_search .form-control-btn { padding: 2px 8px; font-size: 12px; }

.addon_search .input-group.sbeg { margin-left: auto; }
@media (min-width: 768px){ .addon_search .input-group.sbeg { max-width: 180px; } }
		
.addon_search select { margin: 0px; width: 100%; max-width: 230px; display: inline-block; }
	.addon_search select.multiple { height: 60px !important; }
	.addon_search select.form_auto { width: auto !important; max-width: 634px; }
.addon_search input.checkbox { display: inline-block; width: auto; margin: 0px 6px !important; padding: 0px !important; height: auto !important; }
.addon_search input.button { font-weight: bold; margin: 0px !important; width: auto; padding: 0px 4px !important; height: 22px !important; font-size: 0.9em; background: #FFF; border: 1px solid #323232; }
.addon_search label { display: inline-block; width: 90px !important; font-weight: normal; }
	.addon_search label.multiple { vertical-align: top !important; }
	.addon_search label.form_auto { width: auto !important; }
.addon_search a.moreoptions { display: inline-block; vertical-align: sub; }
.addon_search .rightmargin { margin-right: 7px !important; }
.addon_search .btn-group-xs { margin-right: 7px; }

.addon_inlinegroup { display: inline-block; }
.addon_input-group { display: table; }
	.addon_input-group > * { display: table-cell; border-radius: 0px; border: 1px solid #7586a0; margin-left: -1px; }
	.addon_input-group > *:first-child { margin: 0px; }
	.addon_input-group > *:last-child { border-radius: 0px 2px 2px 0px; }
.addon_input-group-field {}
.addon_input-group-btn {}

.addon_search .btn-group-xs { margin-right: 7px; }

.mb-fieldset-inline dl { display: inline-block; width: 100%; max-width: 200px; vertical-align: top; margin: 0px 15px 7px 0px; }
	.mb-fieldset-inline dl.fullwidth { max-width: none; }
	
	.mb-fieldset-inline dl.w200 { max-width: 200px; }
	.mb-fieldset-inline dl.w300 { max-width: 300px; }
	.mb-fieldset-inline dl.w400 { max-width: 400px; }
	.mb-fieldset-inline dl.w500 { max-width: 500px; }
	.mb-fieldset-inline dl.w600 { max-width: 600px; }
	
	.mb-fieldset-inline dl.w50p { max-width: calc(50% - 15px - 5px); min-width: 300px; }		/* margin-right + 5px wegen inline-block */
	
.mb-fieldset-inline dt { display: block; width: auto; min-width: 0px; padding: 0px !important; }
.mb-fieldset-inline dt label { font-weight: normal; margin-bottom: 2px; min-width: 130px; }

.info { font-size: 0.825em; font-weight: normal; }
.info-labels { display: inline-block; padding: 3px 6px; background: #EAEAEA; margin-right: 5px; font-size: 0.80em; }
	.info-green { background: #360; color: #FFF; }
	.info-red { background: #900; color: #FFF; }
.infoblock { display: block; font-size: 0.825em; margin-top: 7px; }
.textblock { width: auto !important; font-weight: normal; padding-bottom: 10px; }
.charlimitreached { background-color: rgba(255,0,0, 0.15) !important; }
a.copyfromabove { cursor: pointer; }
a.openerlink { display: inline-block; }
	@media (min-width: 992px){ a.openerlink { margin-top: 7px; } }

.checkbox.toggle label input, .radio.toggle label input { -webkit-appearance: none; -moz-appearance: none; appearance: none; width: 3em; height: 1.5em; background: #ddd; vertical-align: middle; border-radius: 1.6em; position: relative; outline: 0; margin-top: -3px; margin-right: 10px; cursor: pointer; transition: background 0.1s ease-in-out; }
	.checkbox.toggle-dark label input, .radio.toggle-dark label input { background: #CCC; }
	.checkbox.toggle label input::after, .radio.toggle label input::after, .radio.switch label input::before { content: ''; width: 1.5em; height: 1.5em; background: white; position: absolute; border-radius: 1.2em; transform: scale(0.7); left: 0; box-shadow: 0 1px rgba(0, 0, 0, 0.5); transition: left 0.1s ease-in-out; }
.checkbox.toggle label input:checked, .radio.toggle label input:checked { background: #5791CE; }
	.checkbox.toggle label input:checked::after { left: 1.5em; }
.checkbox.toggle label { padding-left: 60px; }
.checkbox.toggle input { margin-left: -59px; }

.radio.switch label { margin-right: 1.5em; }
.radio.switch label input { width: 1.5em; margin-right: 5px; }
	.radio.switch label input:checked::after { transform: scale(0.5); }
.radio.switch label input::before { background: #5791CE; opacity: 0; box-shadow: none; }
	.radio.switch label input:checked::before { animation: radioswitcheffect 0.65s; }
@keyframes radioswitcheffect { 0% { opacity: 0.75; } 100% { opacity: 0; transform: scale(2.5); } }

/* Checkbox-Toggler small */
.cb-small { zoom: 0.75; }
.cb-small label { margin-right: 0px !important; }
.cb-small label input[type=checkbox].toggle { margin-right: 8px; }


.optionsblock { display: inline-block; vertical-align: top; min-width: 182px; margin: 0px 23px 18px 0px; padding: 7px 14px; transition: all .3s ease; }
	.optionsblock:hover { background: #FFF; }
.optionsblock label { margin-right: 0px !important; }
.optionsblock ul { list-style: none; margin: 0px; padding: 0px;}
.optionsblock li { margin: 0px 0px 5px; }

.field-disabled { pointer-events: none; user-select: none; opacity: 0.5; }

.removeMargin { margin: 0px !important; }
.removeTopMargin { margin-top: 0px; }
.removeBottomMargin { margin-bottom: 0px !important; }
.removePadding { padding: 0px; }
.addTopPadding { padding-top: 10px; }

code { padding: 3px 5px; }
code.small { font-size: 11px; }
</style>


<?php
// Subpage einbinden - Redirect passiert in den einzelnen Seiten
rex_be_controller::includeCurrentPageSubPath();