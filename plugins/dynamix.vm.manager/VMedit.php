<?PHP
/* Copyright 2015-2016, Lime Technology
 * Copyright 2015-2016, Derek Macias, Eric Schultz, Jon Panozzo.
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

$strSelectedTemplate = array_keys($arrAllTemplates)[1];
if (!empty($_GET['template']) && !(empty($arrAllTemplates[$_GET['template']]))) {
	$strSelectedTemplate = $_GET['template'];
}

$arrLoad = [
	'name' => '',
	'icon' => $arrAllTemplates[$strSelectedTemplate]['icon'],
	'autostart' => false,
	'form' => $arrAllTemplates[$strSelectedTemplate]['form']
];
$strIconURL = '/plugins/dynamix.vm.manager/templates/images/'.$arrLoad['icon'];

if (!empty($_GET['uuid'])) {
	// Edit VM mode
	$res = $lv->domain_get_domain_by_uuid($_GET['uuid']);

	if ($res === false) {
		echo "<p class='notice'>Invalid VM to edit.</p>";
		return;
	}

	$strIcon = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@icon');

	if (!empty($strIcon)) {
		if (is_file($strIcon)) {
			$strIconURL = $strIcon;
		} else if (is_file('/usr/local/emhttp/plugins/dynamix.vm.manager/templates/images/' . $strIcon)) {
			$strIconURL = '/plugins/dynamix.vm.manager/templates/images/' . $strIcon;
		}
	} else {
		$strIcon = ($lv->domain_get_clock_offset($res) == 'localtime' ? 'windows.png' : 'linux.png');
		$strIconURL = '/plugins/dynamix.vm.manager/templates/images/' . $strIcon;
	}

	$arrLoad = [
		'name' => $lv->domain_get_name($res),
		'icon' => $strIcon,
		'autostart' => $lv->domain_get_autostart($res),
		'form' => $arrAllTemplates[$strSelectedTemplate]['form']
	];

	if (empty($_GET['template'])) {
		$strTemplateOS = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@os');
		if (empty($strTemplateOS)) {
			$strTemplate = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
			if (!empty($strTemplate)) {
				$strSelectedTemplate = $strTemplate;
			}
		} else {
			// Legacy VM support for <6.2 but need it going forward too
			foreach ($arrAllTemplates as $strName => $arrTemplate) {
				if (!empty($arrTemplate) && !empty($arrTemplate['os']) && $arrTemplate['os'] == $strTemplateOS) {
					$strSelectedTemplate = $strName;
					break;
				}
			}
		}
		if (empty($strSelectedTemplate) || empty($arrAllTemplates[$strSelectedTemplate])) {
			$strSelectedTemplate = 'Custom';
		}
	}
	$arrLoad['form'] = $arrAllTemplates[$strSelectedTemplate]['form'];
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
	#vmform table {
		margin-top: 0;
	}
	#vmform div#title + table {
		margin-top: -21px;
	}
	#vmform table tr {
		vertical-align: top;
		line-height: 24px;
	}
	#vmform table tr td:nth-child(odd) {
		width: 150px;
		text-align: right;
		padding-right: 10px;
	}
	#vmform table tr td:nth-child(even) {
		width: 80px;
	}
	#vmform table tr td:last-child {
		width: inherit;
	}
	#vmform .multiple {
		position: relative;
	}
	#vmform .sectionbutton {
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
	#vmform .sectionbutton.remove { top: 0; opacity: 0.3; }
	#vmform .sectionbutton.add { bottom: 0; }
	#vmform .sectionbutton:hover { opacity: 1.0; }
	#vmform .sectiontab {
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
	#vmform .multiple:hover .sectionbutton {
		opacity: 0.7;
		left: 4px;
	}
	#vmform .multiple:hover .sectionbutton.remove {
		opacity: 0.6;
	}
	#vmform .multiple:hover .sectiontab {
		background-color: #CCCCCC;
		width: 8px;
	}

	#vmform table.multiple {
		margin: 10px 0;
		<?if ($display['theme'] == 'black'):?>
		background:linear-gradient(90deg, #0A0A0A, #000000);
		<?else:?>
		background:linear-gradient(90deg, #F5F5F5, #FFFFFF);
		<?endif;?>
		background-size: 600px 100%;
		background-position: -600px;
		background-repeat: no-repeat;
		background-clip: content-box;
		transition: background 0.3s linear;
	}
	#vmform table.multiple:hover {
		background-position: 0 0;
	}
	#vmform table.multiple td {
		padding: 5px 0;
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
	#template_img {
		cursor: pointer;
	}
	#template_img:hover {
		opacity: 0.5;
	}
	#template_img:hover i {
		opacity: 1.0;
	}
	.template_img_chooser_inner {
		display: inline-block;
		width: 80px;
		margin-bottom: 15px;
		margin-right: 10px;
		text-align: center;
	}
	.template_img_chooser_inner img {
		width: 48px;
		height: 48px;
	}
	.template_img_chooser_inner p {
		text-align: center;
		line-height: 8px;
	}
	#template_img_chooser {
		width: 560px;
		height: 300px;
		overflow: scroll;
		position: relative;
	}
	#template_img_chooser div:hover {
		background-color: #eee;
		cursor: pointer;
	}
	#template_img_chooser_outer {
		position: absolute;
		display: none;
		border-radius:5px;
		<?if ($display['theme'] == 'black'):?>
		border:1px solid #202020;
		background:-webkit-radial-gradient(#303030,#101010);
		background:linear-gradient(#303030,#101010);
		<?else:?>
		border:1px solid #D0D0D0;
		background:-webkit-radial-gradient(#B0B0B0,#F0F0F0);
		background:linear-gradient(#B0B0B0,#F0F0F0);
		<?endif;?>
		z-index: 10;
	}
	#form_content {
		display: none;
	}

	#vmform .four label {
		float: left;
		display: table-cell;
		width: 25%;
	}
	#vmform .four label:nth-child(4n+4) {
		float: none;
		clear: both;
	}
	#vmform .mac_generate {
		cursor: pointer;
		margin-left: -5px;
		color: #08C;
		font-size: 1.3em;
		transform: translate(0px, 2px);
	}
	#vmform .disk {
		display: none;
	}
	#vmform .disk_preview {
		display: inline-block;
		color: #BBB;
		transform: translate(0px, 1px);
	}

	<?if ($display['theme'] == 'black'):?>
	span#dropbox{border:1px solid #202020;border-radius:5px;background:-webkit-radial-gradient(#303030,#101010);background:linear-gradient(#303030,#101010);padding:28px 12px;line-height:72px;margin-right:16px;}
	<?else:?>
	span#dropbox{border:1px solid #D0D0D0;border-radius:5px;background:-webkit-radial-gradient(#B0B0B0,#F0F0F0);background:linear-gradient(#B0B0B0,#F0F0F0);padding:28px 12px;line-height:72px;margin-right:16px;}
	<?endif;?>
</style>
<span class="status advancedview_panel" style="margin-top:-44px;"><input type="checkbox" class="advancedview"></span>
<div id="content" style="margin-top:-21px;margin-left:0px">
	<form id="vmform" method="POST">
	<input type="hidden" name="domain[type]" value="kvm" />
	<input type="hidden" name="template[name]" value="<?=$strSelectedTemplate?>" />

	<table>
		<tr>
			<td>Icon:</td>
			<td>
				<input type="hidden" name="template[icon]" id="template_icon" value="<?=$arrLoad['icon']?>" />
				<img id="template_img" src="<?=htmlentities($strIconURL)?>" width="48" height="48" title="Change Icon..."/>
				<div id="template_img_chooser_outer">
					<div id="template_img_chooser">
					<?
						$arrImagePaths = [
							'/usr/local/emhttp/plugins/dynamix.vm.manager/templates/images/*.png' => '/plugins/dynamix.vm.manager/templates/images/',
							'/usr/local/emhttp/boot/config/plugins/dynamix.vm.manager/templates/images/*.png' => '/boot/config/plugins/dynamix.vm.manager/templates/images/'
						];
						foreach ($arrImagePaths as $strGlob => $strIconURLBase) {
							foreach (glob($strGlob) as $png_file) {
								echo '<div class="template_img_chooser_inner"><img src="'.$strIconURLBase.basename($png_file).'" basename="'.basename($png_file).'"><p>'.basename($png_file,'.png').'</p></div>';
							}
						}
					?>
					</div>
				</div>
			</td>
		</tr>
	</table>

	<table>
		<tr style="line-height: 15px; vertical-align: middle;">
			<td>Autostart:</td>
			<td><div style="margin-left: -10px"><input type="checkbox" id="domain_autostart" name="domain[autostart]" style="display: none" class="autostart" value="1" <? if ($arrLoad['autostart']) echo 'checked'; ?>></div></td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>If you want this VM to start with the array, set this to yes.</p>
	</blockquote>

	<div id="form_content"><? include('/usr/local/emhttp/plugins/dynamix.vm.manager/templates/'.$arrLoad['form']); ?></div>

	</form>
</div>

<script src="/webGui/javascript/jquery.filedrop.js"></script>
<script src="/webGui/javascript/jquery.filetree.js"></script>
<script src="/webGui/javascript/jquery.switchbutton.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/dynamix.vm.manager.js"></script>
<script>
function isVMAdvancedMode() {
	return ($.cookie('vmmanager_listview_mode') == 'advanced');
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

	$('#template_img').click(function (){
		var p = $(this).position();
		p.left -= 4;
		p.top -= 4;
		$('#template_img_chooser_outer').css(p);
		$('#template_img_chooser_outer').slideDown();
	});
	$('#template_img_chooser').on('click', 'div', function (){
		$('#template_img').attr('src', $(this).find('img').attr('src'));
		$('#template_icon').val($(this).find('img').attr('basename'));
		$('#template_img_chooser_outer').slideUp();
	});
	$(document).keyup(function(e) {
		if (e.which == 27) $('#template_img_chooser_outer').slideUp();
	});

	$("#vmform table[data-category]").each(function () {
		var category = $(this).data('category');

		updatePrefixLabels(category);
		bindSectionEvents(category);
	});

	$("#vmform input[data-pickroot]").fileTreeAttach();

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

	$("#vmform #btnCancel").click(function (){
		done();
	});

	$('#form_content').fadeIn('fast');
});
</script>
