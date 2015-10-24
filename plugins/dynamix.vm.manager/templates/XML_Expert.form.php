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

	$strXML = '';
	$strUUID = '';
	$boolRunning = false;

	// If we are editing a existing VM load it's existing configuration details
	if (!empty($_GET['uuid'])) {
		$strUUID = $_GET['uuid'];
		$res = $lv->domain_get_name_by_uuid($strUUID);
		$dom = $lv->domain_get_info($res);

		$strXML = $lv->domain_get_xml($res);
		$boolRunning = ($lv->domain_state_translate($dom['state']) == 'running');
	}


	if (array_key_exists('createvm', $_POST)) {
		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));
		file_put_contents('/tmp/debug_libvirt_newxml.xml', $_POST['xmldesc']);

		$tmp = $lv->domain_define($_POST['xmldesc']);
		if (!$tmp){
			$arrResponse = ['error' => $lv->get_last_error()];
		} else {
			$lv->domain_set_autostart($tmp, $_POST['domain']['autostart'] == 1);

			$arrResponse = ['success' => true];
		}

		echo json_encode($arrResponse);
		exit;
	}

	if (array_key_exists('updatevm', $_POST)) {
		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));
		file_put_contents('/tmp/debug_libvirt_updatexml.xml', $_POST['xmldesc']);

		// Backup xml for existing domain in ram
		$strOldXML = '';
		$boolOldAutoStart = false;
		$res = $lv->domain_get_name_by_uuid($_POST['domain']['uuid']);
		if ($res) {
			$strOldXML = $lv->domain_get_xml($res);
			$boolOldAutoStart = $lv->domain_get_autostart($res);

			//DEBUG
			file_put_contents('/tmp/debug_libvirt_oldxml.xml', $strOldXML);
		}

		// Remove existing domain
		$lv->domain_undefine($res);

		// Save new domain
		$tmp = $lv->domain_define($_POST['xmldesc']);
		if (!$tmp){
			$strLastError = $lv->get_last_error();

			// Failure -- try to restore existing domain
			$tmp = $lv->domain_define($strOldXML);
			if ($tmp) $lv->domain_set_autostart($tmp, $boolOldAutoStart);

			$arrResponse = ['error' => $strLastError];
		} else {
			$lv->domain_set_autostart($tmp, $_POST['domain']['autostart'] == 1);

			$arrResponse = ['success' => true];
		}

		echo json_encode($arrResponse);
		exit;
	}

?>
<link rel="stylesheet" href="/plugins/dynamix.vm.manager/scripts/codemirror/lib/codemirror.css">
<link rel="stylesheet" href="/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/show-hint.css">
<style type="text/css">
	.CodeMirror { border: 1px solid #eee; cursor: text; }
	.CodeMirror pre.CodeMirror-placeholder { color: #999; }
</style>

<input type="hidden" name="domain[uuid]" value="<?=$strUUID?>">

<textarea id="addcode" name="xmldesc" placeholder="Copy &amp; Paste Domain XML Configuration Here." autofocus><?= htmlspecialchars($strXML); ?></textarea>


<? if (!$boolRunning) { ?>
	<? if (!empty($strXML)) { ?>
		<input type="hidden" name="updatevm" value="1" />
		<input type="button" value="Update" busyvalue="Updating..." readyvalue="Update" id="btnSubmit" />
	<? } else { ?>
		<label for="domain_start"><input type="checkbox" name="domain[startnow]" id="domain_start" value="1" checked="checked"/> Start VM after creation</label>
		<br>
		<input type="hidden" name="createvm" value="1" />
		<input type="button" value="Create" busyvalue="Creating..." readyvalue="Create" id="btnSubmit" />
	<? } ?>
		<input type="button" value="Cancel" id="btnCancel" />
<? } else { ?>
	<input type="button" value="Done" id="btnCancel" />
<? } ?>


<script src="/plugins/dynamix.vm.manager/scripts/codemirror/lib/codemirror.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/codemirror/addon/display/placeholder.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/codemirror/addon/fold/foldcode.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/show-hint.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/xml-hint.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/codemirror/addon/hint/libvirt-schema.js"></script>
<script src="/plugins/dynamix.vm.manager/scripts/codemirror/mode/xml/xml.js"></script>
<script>
$(function() {
	function completeAfter(cm, pred) {
		var cur = cm.getCursor();
		if (!pred || pred()) setTimeout(function() {
			if (!cm.state.completionActive)
				cm.showHint({completeSingle: false});
		}, 100);
		return CodeMirror.Pass;
	}

	function completeIfAfterLt(cm) {
		return completeAfter(cm, function() {
			var cur = cm.getCursor();
			return cm.getRange(CodeMirror.Pos(cur.line, cur.ch - 1), cur) == "<";
		});
	}

	function completeIfInTag(cm) {
		return completeAfter(cm, function() {
			var tok = cm.getTokenAt(cm.getCursor());
			if (tok.type == "string" && (!/['"]/.test(tok.string.charAt(tok.string.length - 1)) || tok.string.length == 1)) return false;
			var inner = CodeMirror.innerMode(cm.getMode(), tok.state).state;
			return inner.tagName;
		});
	}

	var editor = CodeMirror.fromTextArea(document.getElementById("addcode"), {
		mode: "xml",
		lineNumbers: true,
		foldGutter: true,
		gutters: ["CodeMirror-linenumbers", "CodeMirror-foldgutter"],
		extraKeys: {
			"'<'": completeAfter,
			"'/'": completeIfAfterLt,
			"' '": completeIfInTag,
			"'='": completeIfInTag,
			"Ctrl-Space": "autocomplete"
		},
		hintOptions: {schemaInfo: getLibvirtSchema()}
	});

	setTimeout(function() {
    	editor.refresh();
	}, 1);

	$("#form_content #btnSubmit").click(function frmSubmit() {
		var $button = $(this);

		editor.save();

		var $form = $('#domain_template').closest('form');
		var postdata = $form.serialize().replace(/'/g,"%27");

		$form.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));

		$.post("/plugins/dynamix.vm.manager/templates/<?=basename(__FILE__)?>", postdata, function( data ) {
			if (data.success) {
				done();
			}
			if (data.error) {
        swal({title:"VM creation error",text:data.error,type:"error"});
				$form.find('input').prop('disabled', false);
				$button.val($button.attr('readyvalue'));
			}
		}, "json");
	});

	$("#form_content #btnCancel").click(done);
});
</script>
