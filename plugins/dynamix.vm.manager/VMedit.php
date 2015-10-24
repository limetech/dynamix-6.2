<?PHP
/* Copyright 2015, Lime Technology
 * Copyright 2015, Derek Macias, Eric Schultz, Jon Panozzo.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
require_once('/usr/local/emhttp/webGui/include/Helpers.php');
require_once('/usr/local/emhttp/plugins/dynamix.vm.manager/classes/libvirt.php');
require_once('/usr/local/emhttp/plugins/dynamix.vm.manager/classes/libvirt_helpers.php');

$arrLoad = [
	'name' => '',
	'icon' => 'default.png',
	'desc' => '',
	'autostart' => false
];

$strSelectedTemplate = 'Custom';

if (!empty($_GET['uuid'])) {
	// Edit VM mode
	$res = $lv->domain_get_domain_by_uuid($_GET['uuid']);

	$strIcon = $lv->_get_single_xpath_result($res, '//domain/metadata/vmtemplate/@icon');

	if (!empty($strIcon)) {
		if (file_exists($strIcon)) {
			$strIconURL = $strIcon;
		} else if (file_exists('/usr/local/emhttp/plugins/dynamix.vm.manager/templates/images/' . $strIcon)) {
			$strIconURL = '/plugins/dynamix.vm.manager/templates/images/' . $strIcon;
		}
	} else {
		$strIcon = ($lv->domain_get_clock_offset($res) == 'localtime' ? 'windows.png' : 'linux.png');
		$strIconURL = '/plugins/dynamix.vm.manager/templates/images/' . $strIcon;
	}

	$arrLoad = [
		'name' => $lv->domain_get_name($res),
		'icon' => $strIcon,
		'desc' => $lv->domain_get_description($res),
		'autostart' => $lv->domain_get_autostart($res)
	];

	if (!empty($_GET['template'])) {
		$strSelectedTemplate = $_GET['template'];
	} else {
		$strTemplate = $lv->_get_single_xpath_result($res, '//domain/metadata/vmtemplate/@name');
		if (!empty($strTemplate)) {
			$strSelectedTemplate = $strTemplate;
		}
	}
} else {
	// New VM mode
	$strIcon = 'windows.png';
	$strIconURL = '/plugins/dynamix.vm.manager/templates/images/windows.png';

	$arrLoad['icon'] = $strIcon;

	if (!empty($_GET['template'])) {
		$strSelectedTemplate = $_GET['template'];
	}
}

$arrTemplates = array();

// Read files from the templates folder
foreach (glob('plugins/dynamix.vm.manager/templates/*.form.php') as $template) {
	$arrTemplates[] = basename($template, '.form.php');
}

if (!empty($arrTemplates) && !in_array($strSelectedTemplate, $arrTemplates)) {
	$strSelectedTemplate = $arrTemplates[0];
}

?>
<link type="text/css" rel="stylesheet" href="/plugins/dynamix.vm.manager/styles/dynamix.vm.manager.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.filetree.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/jquery.switchbutton.css">
<style type="text/css">
	body { -webkit-overflow-scrolling: touch;}
	.fileTree {
		width: 305px;
		max-height: 150px;
		overflow: scroll;
		position: absolute;
		z-index: 100;
		display: none;
	}
	#createform table {
		margin-top: 0;
	}
	#createform div#title + table {
		margin-top: -21px;
	}
	#createform table tr {
		vertical-align: top;
		line-height: 24px;
	}
	#createform table tr td:nth-child(odd) {
		width: 150px;
		text-align: right;
		padding-right: 10px;
	}
	#createform table tr td:nth-child(even) {
		width: 80px;
	}
	#createform table tr td:last-child {
		width: inherit;
	}
	#createform .multiple {
		position: relative;
	}
	#createform .sectionbutton {
		position: absolute;
		left: 2px;
		cursor: pointer;
		opacity: 0.4;
		font-size: 15px;
		line-height: 17px;
		z-index: 10;
		transition-property: opacity, left;
		transition-duration: 0.1s;
		transition-timing-function: linear;
	}
	#createform .sectionbutton.remove { top: 0; opacity: 0.3; }
	#createform .sectionbutton.add { bottom: 0; }
	#createform .sectionbutton:hover { opacity: 1.0; }
	#createform .sectiontab {
		position: absolute;
		top: 2px;
		bottom: 2px;
		left: 0;
		width: 6px;
		border-radius: 3px;
		background-color: #DDDDDD;
		transition-property: background, width;
		transition-duration: 0.1s;
		transition-timing-function: linear;
	}
	#createform .multiple:hover .sectionbutton {
		opacity: 0.7;
		left: 4px;
	}
	#createform .multiple:hover .sectionbutton.remove {
		opacity: 0.6;
	}
	#createform .multiple:hover .sectiontab {
		background-color: #CCCCCC;
		width: 8px;
	}
	#form_content {
		margin-top: -21px;
	}
	span.advancedview_panel {
		display: none;
		line-height: 16px;
		margin-top: 1px;
	}
	.basic {
		/*Empty placeholder*/
	}
	.advanced {
		display: none;
	}
	.switch-button-label.off {
		color: inherit;
	}
</style>

<div id="content" style="margin-top:-21px;margin-left:0px">
	<form id="createform" method="POST">
	<input type="hidden" name="domain[type]" value="kvm" />

	<table>
		<tr <? if (!empty($arrLoad['name'])) echo 'style="display: none"'; ?>>
			<td>Template:</td>
			<td>
				<select id="domain_template" name="template[name]" class="narrow" title="Choose a preconfigured template or select Custom to create your own from scratch">
					<?php
						foreach ($arrTemplates as $strTemplate) {
							echo mk_option($strSelectedTemplate, $strTemplate, str_replace('_', ' ', $strTemplate));
						}
					?>
				</select><img src="/webGui/images/spinner.gif" style="display: none">
			</td>
		</tr>
	</table>
	<div <? if (!empty($arrLoad['name'])) echo 'style="display: none"'; ?>>
		<blockquote class="inline_help">
			<p>Choose a preconfigured template or select Custom to create your own from scratch.</p>
		</blockquote>
	</div>

	<table>
		<tr>
			<td>Icon:</td>
			<td><input type="hidden" name="template[icon]" id="template_icon" value="<?=$arrLoad['icon']?>" /><img id="template_img" src="<?=htmlentities($strIconURL)?>" width="48" height="48" /></td>
		</tr>
	</table>

	<table>
		<tr class="non_expert_xml">
			<td>Name:</td>
			<td><input type="text" name="domain[name]" class="textTemplate" title="Name of virtual machine" placeholder="e.g. My Workstation" value="<?=htmlentities($arrLoad['name'])?>" /></td>
		</tr>
	</table>
	<div class="non_expert_xml">
		<blockquote class="inline_help">
			<p>Give the VM a name (e.g. Work, Gaming, Media Player, Firewall, Bitcoin Miner)</p>
		</blockquote>
	</div>

	<table>
		<tr class="non_expert_xml">
			<td>Description:</td>
			<td><input type="text" name="domain[desc]" title="description of virtual machine" placeholder="description of virtual machine (optional)" value="<?=htmlentities($arrLoad['desc'])?>" /></td>
		</tr>
	</table>
	<div class="non_expert_xml">
		<blockquote class="inline_help">
			<p>Give the VM a brief description (optional field).</p>
		</blockquote>
	</div>

	<table>
		<tr style="line-height: 15px; vertical-align: middle;">
			<td>Autostart:</td>
			<td><div style="margin-left: -10px"><input type="checkbox" id="domain_autostart" name="domain[autostart]" style="display: none" class="autostart" value="1" <? if ($arrLoad['autostart']) echo 'checked'; ?>></div></td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>If you want this VM to start with the array, set this to yes.</p>
	</blockquote>

	<div id="title">
		<span class="left"><img src="/plugins/dynamix.docker.manager/icons/preferences.png" class="icon">Template Settings</span>
		<span class="status advancedview_panel"><input type="checkbox" class="advancedview"></span>
	</div>

	<div id="form_content"></div>

	</form>
</div>

<script src="/webGui/javascript/jquery.filetree.js"></script>
<script src="/webGui/javascript/jquery.switchbutton.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/dynamix.vm.manager.js"></script>
<script>
function isVMAdvancedMode() {
	return ($.cookie('vmmanager_listview_mode') == 'advanced');
}
function checkForAdvancedMode() {
	var $el = $('#form_content');

	var $advanced = $el.find('.advanced');
	var $basic = $el.find('.basic');

	if ($advanced.length || $basic.length) {
		$('.advancedview_panel').fadeIn('fast');
		if (isVMAdvancedMode()) {
			$('.basic').hide();
			$('.advanced').filter(function() {
				return (($(this).prop('style').display + '') === '');
			}).show();
		} else {
			$('.advanced').hide();
			$('.basic').filter(function() {
				return (($(this).prop('style').display + '') === '');
			}).show();
		}
	} else {
		$('.advancedview_panel').fadeOut('fast');
	}
}

$(function() {
	$('.autostart').switchButton({
		on_label: 'Yes',
		off_label: 'No',
		labels_placement: "right"
	});
	$('.autostart').change(function () {
		$('#domain_autostart').prop('checked', $(this).is(':checked'));
	});

	$('.advancedview').switchButton({
		labels_placement: "left",
		on_label: 'Advanced View',
  		off_label: 'Basic View',
  		checked: isVMAdvancedMode()
	});
	$('.advancedview').change(function () {
		toggleRows('advanced', $(this).is(':checked'), 'basic');
		$.cookie('vmmanager_listview_mode', $(this).is(':checked') ? 'advanced' : 'basic', { expires: 3650 });
	});

  	$('#domain_template').change(function loadDomainTemplate(){
		var $el = $('#form_content');
		var templateName = $(this).val();

		toggleRows('non_expert_xml', (templateName != 'XML_Expert'));

		$el.fadeOut(100, function(){
			$el.html('<div style="padding-left: 80px; padding-top: 20px; font-size: 1.3em"><img src="/webGui/images/spinner.gif"> Loading...</div>').fadeIn('fast');
		});

		$.get('/plugins/dynamix.vm.manager/templates/' + templateName + '.form.php' + location.search, function(data) {
			$el.stop(true, false).fadeOut(100, function() {
				$el.html(data);

				checkForAdvancedMode();

				if ($.cookie('help')=='help') {
					$('.inline_help').show();
				}

				$el.fadeIn('fast');
			});
		});
  	}).change(); // Fire now too
});
</script>
