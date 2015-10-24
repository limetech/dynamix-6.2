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

	$arrValidMachineTypes = getValidMachineTypes();
	$arrValidGPUDevices = getValidGPUDevices();
	$arrValidAudioDevices = getValidAudioDevices();
	$arrValidOtherDevices = getValidOtherDevices();
	$arrValidUSBDevices = getValidUSBDevices();
	$arrValidDiskDrivers = getValidDiskDrivers();
	$arrValidBridges = getNetworkBridges();
	$strCPUModel = getHostCPUModel();

	$arrOpenELECVersions = [
		'5.95.3_1' => [
			'name' => '6.0.0 Beta3',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-5.95.3_1.tar.xz',
			'size' => 153990180,
			'md5' => '8936cda74c28ddcaa165cc49ff2a477a',
			'localpath' => '',
			'valid' => '0'
		],
		'5.95.2_1' => [
			'name' => '6.0.0 Beta2',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-5.95.2_1.tar.xz',
			'size' => 156250392,
			'md5' => 'ac70048eecbda4772e386c6f271cb5e9',
			'localpath' => '',
			'valid' => '0'
		]
	];

	// Read localpaths in from openelec.cfg
	$strOpenELECConfig = "/boot/config/plugins/dynamix.vm.manager/openelec.cfg";
	$arrOpenELECConfig = [];

	if (file_exists($strOpenELECConfig)) {
		$arrOpenELECConfig = parse_ini_file($strOpenELECConfig);
	} elseif (!file_exists(dirname($strOpenELECConfig))) {
		@mkdir(dirname($strOpenELECConfig), 0777, true);
	}


	// Compare openelec.cfg and populate 'localpath' in $arrOEVersion
	foreach ($arrOpenELECConfig as $strID => $strLocalpath) {
		if (array_key_exists($strID, $arrOpenELECVersions)) {
			$arrOpenELECVersions[$strID]['localpath'] = $strLocalpath;
			if (file_exists($strLocalpath)) {
				$arrOpenELECVersions[$strID]['valid'] = '1';
			}
		}
	}

	if (array_key_exists('delete_version', $_POST)) {

		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));

		$arrDeleteOpenELEC = [];
		if (array_key_exists($_POST['delete_version'], $arrOpenELECVersions)) {
			$arrDeleteOpenELEC = $arrOpenELECVersions[$_POST['delete_version']];
		}

		$arrResponse = [];

		if (empty($arrDeleteOpenELEC)) {
			$arrResponse = ['error' => 'Unknown version: ' . $_POST['delete_version']];
		} else {
			// delete img file
			@unlink($arrDeleteOpenELEC['localpath']);

			// Save to strOpenELECConfig
			unset($arrOpenELECConfig[$_POST['delete_version']]);
			$text = '';
			foreach ($arrOpenELECConfig as $key => $value) $text .= "$key=\"$value\"\n";
			file_put_contents($strOpenELECConfig, $text);

			$arrResponse = ['status' => 'ok'];
		}

		echo json_encode($arrResponse);
		exit;
	}

	if (array_key_exists('download_path', $_POST)) {

		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));

		$arrDownloadOpenELEC = [];
		if (array_key_exists($_POST['download_version'], $arrOpenELECVersions)) {
			$arrDownloadOpenELEC = $arrOpenELECVersions[$_POST['download_version']];
		}

		if (empty($arrDownloadOpenELEC)) {
			$arrResponse = ['error' => 'Unknown version: ' . $_POST['download_version']];
		} else {
			@mkdir($_POST['download_path'], 0777, true);
			$_POST['download_path'] = realpath($_POST['download_path']) . '/';


			$strInstallScript = '/tmp/OpenELEC_' . $_POST['download_version'] . '_install.sh';
			$strLogFile = $_POST['download_path'] . basename($arrDownloadOpenELEC['url']) . '.log';
			$strTempFile = $_POST['download_path'] . basename($arrDownloadOpenELEC['url']);
			$strMD5File = $strTempFile . '.md5';
			$strMD5StatusFile = $strTempFile . '.md5status';
			$strExtractedFile = $_POST['download_path'] . basename($arrDownloadOpenELEC['url'], 'tar.xz') . 'img';


			// Save to strOpenELECConfig
			$arrOpenELECConfig[$_POST['download_version']] = $strExtractedFile;
			$text = '';
			foreach ($arrOpenELECConfig as $key => $value) $text .= "$key=\"$value\"\n";
			file_put_contents($strOpenELECConfig, $text);


			$strDownloadCmd = 'wget -nv -O ' . escapeshellarg($strTempFile) . ' ' . escapeshellarg($arrDownloadOpenELEC['url']);
			$strDownloadPgrep = '-f "wget.*' . $strTempFile . '.*' . $arrDownloadOpenELEC['url'] . '"';

			$strVerifyCmd = 'md5sum -c ' . escapeshellarg($strMD5File);
			$strVerifyPgrep = '-f "md5sum.*' . $strMD5File . '"';

			$strExtractCmd = 'tar Jxf ' . escapeshellarg($strTempFile) . ' -C ' . escapeshellarg(dirname($strTempFile));
			$strExtractPgrep = '-f "tar.*' . $strTempFile . '.*' . dirname($strTempFile) . '"';

			$strCleanCmd = '(chmod 777 ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strExtractedFile) . '; chown nobody:users ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strExtractedFile) . '; rm ' . escapeshellarg($strTempFile) . ' ' . escapeshellarg($strTempFile.'.md5') . ' ' . escapeshellarg($strTempFile.'.md5status') . ' ' . escapeshellarg($strTempFile.'.log') . ')';
			$strCleanPgrep = '-f "chmod.*chown.*rm.*' . $strTempFile . '"';

			$strAllCmd = "#!/bin/bash\n\n";
			$strAllCmd .= $strDownloadCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'echo "' . $arrDownloadOpenELEC['md5'] . '  ' . $strTempFile . '" > ' . escapeshellarg($strMD5File) . ' && ';
			$strAllCmd .= $strVerifyCmd . ' >' . escapeshellarg($strMD5StatusFile) . ' 2>/dev/null && ';
			$strAllCmd .= $strExtractCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= $strCleanCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'rm ' . escapeshellarg($strInstallScript);

			$arrResponse = [];

			if (file_exists($strExtractedFile)) {

				if (!file_exists($strTempFile)) {

					// Status = done
					$arrResponse['status'] = 'Done';
					$arrResponse['localpath'] = $strExtractedFile;
					$arrResponse['localfolder'] = dirname($strExtractedFile);

				} else {
					if (pgrep($strExtractPgrep)) {

						// Status = running extract
						$arrResponse['status'] = 'Extracting ... ';

					} else {

						// Status = cleanup
						$arrResponse['status'] = 'Cleanup ... ';

					}
				}

			} else if (file_exists($strTempFile)) {

				if (pgrep($strDownloadPgrep)) {

					// Get Download percent completed
					$intSize = filesize($strTempFile);
					$strPercent = 0;
					if ($intSize > 0) {
						$strPercent = round(($intSize / $arrDownloadOpenELEC['size']) * 100);
					}

					$arrResponse['status'] = 'Downloading ... ' . $strPercent . '%';

				} else if (pgrep($strVerifyPgrep)) {

					// Status = running md5 check
					$arrResponse['status'] = 'Verifying ... ';

				} else if (file_exists($strMD5StatusFile)) {

					// Status = running extract
					$arrResponse['status'] = 'Extracting ... ';

					if (!pgrep($strExtractPgrep)) {
						// Examine md5 status
						$strMD5StatusContents = file_get_contents($strMD5StatusFile);

						if (strpos($strMD5StatusContents, ': FAILED') !== false) {

							// ERROR: MD5 check failed
							unset($arrResponse['status']);
							$arrResponse['error'] = 'MD5 verification failed, your download is incomplete or corrupted.';

						}
					}

				} else if (!file_exists($strMD5File)) {

					// Status = running md5 check
					$arrResponse['status'] = 'Downloading ... 100%';

				}

			} else {

				if (!pgrep($strDownloadPgrep)) {

					// Run all commands
					file_put_contents($strInstallScript, $strAllCmd);
					chmod($strInstallScript, 0777);
					exec($strInstallScript . ' >/dev/null 2>&1 &');

				}

				$arrResponse['status'] = 'Downloading ... ';

			}

		}

		echo json_encode($arrResponse);
		exit;
	}

	$arrOpenELECVersion = reset($arrOpenELECVersions);
	$strOpenELECVersionID = key($arrOpenELECVersions);

	$arrConfigDefaults = [
		'template' => [
			'os' => 'openelec',
			'name' => 'OpenELEC',
			'icon' => 'openelec.png',
			'openelec' => $strOpenELECVersionID
		],
		'domain' => [
			'persistent' => 1,
			'uuid' => $lv->domain_generate_uuid(),
			'clock' => 'utc',
			'arch' => 'x86_64',
			'machine' => getLatestMachineType('q35'),
			'mem' => 512 * 1024,
			'maxmem' => 512 * 1024,
			'password' => '',
			'cpumode' => 'host-passthrough',
			'vcpus' => 1,
			'vcpu' => [0],
			'hyperv' => 0,
			'ovmf' => 0
		],
		'media' => [
			'cdrom' => '',
			'drivers' => ''
		],
		'disk' => [
			[
				'image' => $arrOpenELECVersion['localpath'],
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda',
				'readonly' => 1
			]
		],
		'gpu' => [
			[
				'id' => '',
				'keymap' => 'en-us'
			]
		],
		'audio' => [
			[
				'id' => ''
			]
		],
		'pci' => [],
		'nic' => [
			[
				'network' => $domain_bridge,
				'mac' => $lv->generate_random_mac_addr()
			]
		],
		'usb' => [],
		'shares' => [
			[
				'source' => (is_dir('/mnt/user/appdata') ? '/mnt/user/appdata/OpenELEC/' : ''),
				'target' => 'appconfig'
			]
		]
	];

	// If we are editing a existing VM load it's existing configuration details
	$arrExistingConfig = (!empty($_GET['uuid']) ? domain_to_config($_GET['uuid']) : []);

	// Active config for this page
	$arrConfig = array_replace_recursive($arrConfigDefaults, $arrExistingConfig);

	if (array_key_exists($arrConfig['template']['openelec'], $arrOpenELECVersions)) {
		$arrConfigDefaults['disk'][0]['image'] = $arrOpenELECVersions[$arrConfig['template']['openelec']]['localpath'];
	}

	$boolRunning = (!empty($arrConfig['domain']['state']) && $arrConfig['domain']['state'] == 'running');


	if (array_key_exists('createvm', $_POST)) {
		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));
		file_put_contents('/tmp/debug_libvirt_newxml.xml', $lv->config_to_xml($_POST));

		if (!empty($_POST['shares'][0]['source'])) {
			@mkdir($_POST['shares'][0]['source'], 0777, true);
		}

		$tmp = $lv->domain_new($_POST);
		if (!$tmp){
			$arrResponse = ['error' => $lv->get_last_error()];
		} else {
			$arrResponse = ['success' => true];
		}

		echo json_encode($arrResponse);
		exit;
	}

	if (array_key_exists('updatevm', $_POST)) {
		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));
		file_put_contents('/tmp/debug_libvirt_updatexml.xml', $lv->config_to_xml($_POST));

		if (!empty($_POST['shares'][0]['source'])) {
			@mkdir($_POST['shares'][0]['source'], 0777, true);
		}

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
		$tmp = $lv->domain_new($_POST);
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

<style type="text/css">
	.four label {
		float: left;
		display: table-cell;
		width: 25%;
	}
	.four label:nth-child(4n+4) {
		float: none;
		clear: both;
	}
	.mac_generate {
		cursor: pointer;
		margin-left: -8px;
		color: #08C;
		font-size: 1.1em;
	}
	#openelec_image {
		color: #BBB;
		display: none;
	}
	.delete_openelec_image {
		cursor: pointer;
		margin-left: 4px;
		margin-right: 4px;
		color: #CC0011;
		font-size: 1.1em;
	}
</style>

<input type="hidden" name="domain[persistent]" value="<?=$arrConfig['domain']['persistent']?>">
<input type="hidden" name="domain[uuid]" value="<?=$arrConfig['domain']['uuid']?>">
<input type="hidden" name="domain[clock]" id="domain_clock" value="<?=$arrConfig['domain']['clock']?>">
<input type="hidden" name="domain[arch]" value="<?=$arrConfig['domain']['arch']?>">
<input type="hidden" name="template[os]" value="<?=$arrConfig['template']['os']?>">

<input type="hidden" name="disk[0][image]" id="disk_0" value="<?=$arrConfig['disk'][0]['image']?>">
<input type="hidden" name="disk[0][dev]" value="<?=$arrConfig['disk'][0]['dev']?>">
<input type="hidden" name="disk[0][readonly]" value="1">


<table>
	<tr>
		<td>OpenELEC Version:</td>
		<td>
			<select name="template[openelec]" id="template_openelec" class="narrow" title="Select the OpenELEC version to use">
			<?php
				foreach ($arrOpenELECVersions as $strOEVersion => $arrOEVersion) {
					$strLocalFolder = ($arrOEVersion['localpath'] == '' ? '' : dirname($arrOEVersion['localpath']));
					echo mk_option($arrConfig['template']['openelec'], $strOEVersion, $arrOEVersion['name'], 'localpath="' . $arrOEVersion['localpath'] . '" localfolder="' . $strLocalFolder . '" valid="' . $arrOEVersion['valid'] . '"');
				}
			?>
			</select> <i class="fa fa-trash delete_openelec_image installed" title="Remove OpenELEC image"></i> <span id="openelec_image" class="installed"></span>
		</td>
	</tr>
</table>
<blockquote class="inline_help">
	<p>Select which OpenELEC version to download or use for this VM</p>
</blockquote>


<div class="available">
	<table>
		<tr>
			<td>Download Folder:</td>
			<td>
				<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="" id="download_path" placeholder="e.g. /mnt/user/domains/" title="Folder to save the OpenELEC image to" />
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Choose a folder where the OpenELEC image will downloaded to</p>
	</blockquote>

	<table>
		<tr>
			<td></td>
			<td>
				<input type="button" value="Download" busyvalue="Downloading..." readyvalue="Download" id="btnDownload" />
				<br>
				<div id="download_status"></div>
			</td>
		</tr>
	</table>
</div>


<div class="installed">
	<table>
		<tr>
			<td>Config Folder:</td>
			<td>
				<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="<?=$arrConfig['shares'][0]['source']?>" name="shares[0][source]" placeholder="e.g. /mnt/user/appdata/openelec" title="path on unRAID share to save OpenELEC settings" />
				<input type="hidden" value="<?=$arrConfig['shares'][0]['target']?>" name="shares[0][target]" />
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Choose a folder or type in a new name off of an existing folder to specify where OpenELEC will save configuration files.  If you create multiple OpenELEC VMs, these Config Folders must be unique for each instance.</p>
	</blockquote>

	<table>
		<tr class="advanced">
			<td>CPU Mode:</td>
			<td>
				<select name="domain[cpumode]" title="define type of cpu presented to this vm">
				<?php mk_dropdown_options(['host-passthrough' => 'Host Passthrough (' . $strCPUModel . ')', 'emulated' => 'Emulated (QEMU64)'], $arrConfig['domain']['cpumode']); ?>
				</select>
			</td>
		</tr>
	</table>
	<div class="advanced">
		<blockquote class="inline_help">
			<p>There are two CPU modes available to choose:</p>
			<p>
				<b>Host Passthrough</b><br>
				With this mode, the CPU visible to the guest should be exactly the same as the host CPU even in the aspects that libvirt does not understand.  For the best possible performance, use this setting.
			</p>
			<p>
				<b>Emulated</b><br>
				If you are having difficulties with Host Passthrough mode, you can try the emulated mode which doesn't expose the guest to host-based CPU features.  This may impact the performance of your VM.
			</p>
		</blockquote>
	</div>

	<table>
		<tr>
			<td>CPUs:</td>
			<td>
				<div class="textarea four">
				<?php
					for ($i = 0; $i < $maxcpu; $i++) {
						$extra = '';
						if (in_array($i, $arrConfig['domain']['vcpu'])) {
							$extra .= ' checked="checked"';
							if (count($arrConfig['domain']['vcpu']) == 1) {
								$extra .= ' disabled="disabled"';
							}
						}
					?>
					<label for="vcpu<?=$i?>"><input type="checkbox" name="domain[vcpu][]" class="domain_vcpu" id="vcpu<?=$i?>" value="<?=$i?>" <?=$extra;?>/> Core <?=$i?></label>
				<?php } ?>
				</div>
			</td>
		</tr>
	</table>
	<div class="advanced">
		<blockquote class="inline_help">
			<p>By default, VMs created will be pinned to physical CPU cores to improve performance.  From this view, you can adjust which actual CPU cores a VM will be pinned (minimum 1).</p>
		</blockquote>
	</div>

	<table>
		<tr>
			<td><span class="advanced">Initial </span>Memory:</td>
			<td>
				<select name="domain[mem]" id="domain_mem" class="narrow" title="define the amount memory">
				<?php
					for ($i = 1; $i <= ($maxmem*2); $i++) {
						$label = ($i * 512) . ' MB';
						$value = $i * 512 * 1024;
						echo mk_option($arrConfig['domain']['mem'], $value, $label);
					}
				?>
				</select>
			</td>

			<td class="advanced">Max Memory:</td>
			<td class="advanced">
				<select name="domain[maxmem]" id="domain_maxmem" class="narrow" title="define the maximum amount of memory">
				<?php
					for ($i = 1; $i <= ($maxmem*2); $i++) {
						$label = ($i * 512) . ' MB';
						$value = $i * 512 * 1024;
						echo mk_option($arrConfig['domain']['maxmem'], $value, $label);
					}
				?>
				</select>
			</td>
			<td></td>
		</tr>
	</table>
	<div class="basic">
		<blockquote class="inline_help">
			<p>Select how much memory to allocate to the VM at boot.</p>
		</blockquote>
	</div>
	<div class="advanced">
		<blockquote class="inline_help">
			<p>Select how much memory to allocate to the VM at boot (cannot be more than Max. Mem).</p>
		</blockquote>
	</div>

	<table>
		<tr class="advanced">
			<td>Machine:</td>
			<td>
				<select name="domain[machine]" class="narrow" id="domain_machine" title="Select the machine model.  i440fx will work for most.  Q35 for a newer machine model with PCIE">
				<?php mk_dropdown_options($arrValidMachineTypes, $arrConfig['domain']['machine']); ?>
				</select>
			</td>
		</tr>
	</table>
	<div class="advanced">
		<blockquote class="inline_help">
			<p>The machine type option primarily affects the success some users may have with various hardware and GPU pass through.  For more information on the various QEMU machine types, see these links:</p>
			<a href="http://wiki.qemu.org/Documentation/Platforms/PC" target="_blank">http://wiki.qemu.org/Documentation/Platforms/PC</a><br>
			<a href="http://wiki.qemu.org/Features/Q35" target="_blank">http://wiki.qemu.org/Features/Q35</a><br>
			<p>As a rule of thumb, try to get your configuration working with i440fx first and if that fails, try adjusting to Q35 to see if that changes anything.</p>
		</blockquote>
	</div>

	<table>
		<tr class="advanced">
			<td>BIOS:</td>
			<td>
				<select name="domain[ovmf]" id="domain_ovmf" class="narrow" title="Select the BIOS.  SeaBIOS will work for most.  OVMF requires a UEFI-compatable OS (e.g. Windows 8/2012, newer Linux distros) and if using graphics device passthrough it too needs UEFI">
				<?php
					echo mk_option($arrConfig['domain']['ovmf'], '0', 'SeaBIOS');

					if (file_exists('/usr/share/qemu/ovmf-x64/OVMF-pure-efi.fd')) {
						echo mk_option($arrConfig['domain']['ovmf'], '1', 'OVMF');
					} else {
						echo mk_option('', '0', 'OVMF (Not Available)', 'disabled="disabled"');
					}
				?>
				</select>
			</td>
		</tr>
	</table>
	<div class="advanced">
		<blockquote class="inline_help">
			<p>
				<b>SeaBIOS</b><br>
				is the default virtual BIOS used to create virtual machines and is compatible with all guest operating systems (Windows, Linux, etc.).
			</p>
			<p>
				<b>OVMF</b><br>
				(Open Virtual Machine Firmware) adds support for booting VMs using UEFI, but virtual machine guests must also support UEFI.  Assigning graphics devices to a OVMF-based virtual machine requires that the graphics device also support UEFI.
			</p>
			<p>
				Once a VM is created this setting cannot be adjusted.
			</p>
		</blockquote>
	</div>


	<? foreach ($arrConfig['gpu'] as $i => $arrGPU) {
		$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

		?>
		<table data-category="Graphics_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidGPUDevices)?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
			<tr>
				<td>Graphics Card:</td>
				<td>
					<select name="gpu[<?=$i?>][id]" class="gpu narrow">
					<?
						if ($i > 0) {
							// Only additional video card can be none
							echo mk_option($arrGPU['id'], '', 'None');
						}

						foreach($arrValidGPUDevices as $arrDev) {
							echo mk_option($arrGPU['id'], $arrDev['id'], $arrDev['name']);
						}
					?>
					</select>
				</td>
			</tr>
		</table>
		<? if ($i == 0) { ?>
		<blockquote class="inline_help">
			<p>
				<b>Graphics Card</b><br>
				If you wish to assign a graphics card to the VM, select it from this list.
			</p>
			<? if (count($arrValidGPUDevices) > 1) { ?>
			<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
			<? } ?>
		</blockquote>
		<? } ?>
	<? } ?>
	<script type="text/html" id="tmplGraphics_Card">
		<table>
			<tr>
				<td>Graphics Card:</td>
				<td>
					<select name="gpu[{{INDEX}}][id]" class="gpu narrow">
					<?php
						echo mk_option('', '', 'None');

						foreach($arrValidGPUDevices as $arrDev) {
							echo mk_option('', $arrDev['id'], $arrDev['name']);
						}
					?>
					</select>
				</td>
			</tr>
		</table>
	</script>


	<? foreach ($arrConfig['audio'] as $i => $arrAudio) {
		$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

		?>
		<table data-category="Sound_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidAudioDevices)?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
			<tr>
				<td>Sound Card:</td>
				<td>
					<select name="audio[<?=$i?>][id]" class="audio narrow">
					<?php
						echo mk_option($arrAudio['id'], '', 'None');

						foreach($arrValidAudioDevices as $arrDev) {
							echo mk_option($arrAudio['id'], $arrDev['id'], $arrDev['name']);
						}
					?>
					</select>
				</td>
			</tr>
		</table>
		<?php if ($i == 0) { ?>
		<blockquote class="inline_help">
			<p>Select a sound device to assign to your VM.  Most modern GPUs have a built-in audio device, but you can also select the on-board audio device(s) if present.</p>
			<? if (count($arrValidAudioDevices) > 1) { ?>
			<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
			<? } ?>
		</blockquote>
		<? } ?>
	<? } ?>
	<script type="text/html" id="tmplSound_Card">
		<table>
			<tr>
				<td>Sound Card:</td>
				<td>
					<select name="audio[{{INDEX}}][id]" class="audio narrow">
					<?php
						foreach($arrValidAudioDevices as $arrDev) {
							echo mk_option('', $arrDev['id'], $arrDev['name']);
						}
					?>
					</select>
				</td>
			</tr>
		</table>
	</script>


	<? foreach ($arrConfig['nic'] as $i => $arrNic) {
		$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

		?>
		<table data-category="Network" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
			<tr class="advanced">
				<td>Network MAC:</td>
				<td>
					<input type="text" name="nic[<?=$i?>][mac]" class="narrow" value="<?=$arrNic['mac']?>" title="random mac, you can supply your own" /> <i class="fa fa-refresh mac_generate" title="re-generate random mac address"></i>
				</td>
			</tr>

			<tr class="advanced">
				<td>Network Bridge:</td>
				<td>
					<select name="nic[<?=$i?>][network]">
					<?php
						foreach ($arrValidBridges as $strBridge) {
							echo mk_option($arrNic['network'], $strBridge, $strBridge);
						}
					?>
					</select>
				</td>
			</tr>
		</table>
		<?php if ($i == 0) { ?>
		<div class="advanced">
			<blockquote class="inline_help">
				<p>
					<b>Network MAC</b><br>
					By default, a random MAC address will be assigned here that conforms to the standards for virtual network interface controllers.  You can manually adjust this if desired.
				</p>

				<p>
					<b>Network Bridge</b><br>
					The default libvirt managed network bridge (virbr0) will be used, otherwise you may specify an alternative name for a private network bridge to the host.
				</p>

				<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
			</blockquote>
		</div>
		<? } ?>
	<? } ?>
	<script type="text/html" id="tmplNetwork">
		<table>
			<tr class="advanced">
				<td>Network MAC:</td>
				<td>
					<input type="text" name="nic[{{INDEX}}][mac]" class="narrow" value="" title="random mac, you can supply your own" /> <i class="fa fa-refresh mac_generate" title="re-generate random mac address"></i>
				</td>
			</tr>

			<tr class="advanced">
				<td>Network Bridge:</td>
				<td>
					<select name="nic[{{INDEX}}][network]">
					<?php
						foreach ($arrValidBridges as $strBridge) {
							echo mk_option($domain_bridge, $strBridge, $strBridge);
						}
					?>
					</select>
				</td>
			</tr>
		</table>
	</script>


	<table>
		<tr>
			<td>USB Devices:</td>
			<td>
				<div class="textarea">
				<?php
					if (!empty($arrValidUSBDevices)) {
						foreach($arrValidUSBDevices as $i => $arrDev) {
						?>
						<label for="usb<?=$i?>"><input type="checkbox" name="usb[]" id="usb<?=$i?>" value="<?=$arrDev['id']?>" <?php if (count(array_filter($arrConfig['usb'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) echo 'checked="checked"'; ?>/> <?=$arrDev['name']?></label><br/>
						<?php
						}
					} else {
						echo "<i>None available</i>";
					}
				?>
				</div>
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>If you wish to assign any USB devices to your guest, you can select them from this list.<br>
		NOTE:  USB hotplug support is not yet implemented, so devices must be attached before the VM is started to use them.</p>
	</blockquote>

	<table>
		<tr>
			<td></td>
			<td>
			<? if (!$boolRunning) { ?>
				<? if (!empty($arrConfig['domain']['name'])) { ?>
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
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Click Create to return to the Virtual Machines page where your new VM will be created.</p>
	</blockquote>
</div>

<script type="text/javascript">
$(function() {
	var initComplete = false;
	var vmicon = 'openelec.png';

	$("#form_content #domain_mem").change(function changeMemEvent() {
		$("#domain_maxmem").val($(this).val());
	});

	$("#form_content .domain_vcpu").change(function changeVCPUEvent() {
		var $cores = $(".domain_vcpu:checked");

		if ($cores.length == 1) {
			$cores.prop("disabled", true);
		} else {
			$(".domain_vcpu").prop("disabled", false);
		}
	});

	$("#form_content #domain_maxmem").change(function changeMaxMemEvent() {
		if (parseFloat($(this).val()) < parseFloat($("#domain_mem").val())) {
			$("#domain_mem").val($(this).val());
		}
	});

	$("#form_content").on("change keyup", ".disk", function changeDiskEvent() {
		var $input = $(this);
		var config = $input.data();

		if (config.hasOwnProperty('pickfilter')) {
			var $other_sections = $input.closest('table').find('.disk_file_options');

			$.get("/plugins/dynamix.vm.manager/VMajax.php?action=file-info&file=" + encodeURIComponent($input.val()), function( info ) {
				if (info.isfile || info.isblock) {
					slideUpRows($other_sections);

					$other_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');

					$input.attr('name', $input.attr('name').replace('new', 'image'));
				} else {
					$other_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');

					slideDownRows($other_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));

					$input.attr('name', $input.attr('name').replace('image', 'new'));
				}
			}, "json");
		}
	});

	$("#form_content").on("change", ".gpu", function changeGPUEvent() {
		var myvalue = $(this).val();

		$(".gpu").not(this).each(function () {
			if (myvalue == $(this).val()) {
				$(this).prop("selectedIndex", 0).change();
			}
		});
	});

	$("#form_content input[data-pickroot]").fileTreeAttach();

	$("#form_content").on("click", ".mac_generate", function generateMac() {
		var $input = $(this).prev('input');

		$.get("/plugins/dynamix.vm.manager/VMajax.php?action=generate-mac", function( data ) {
			if (data.mac) {
				$input.val(data.mac);
			}
		}, "json");
	});

	$("#form_content #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $form = $('#domain_template').closest('form');

		//TODO: form validation

		$form.find('input').prop('disabled', false); // enable all inputs otherwise they wont post

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
				$("#form_content .domain_vcpu").change(); // restore the cpu checkbox disabled states
				$button.val($button.attr('readyvalue'));
			}
		}, "json");
	});

	$("#form_content #btnCancel").click(done);


	$("#form_content #btnDownload").click(function frmDownload() {
		var $button = $("#form_content #btnDownload");
		var $form = $('#domain_template').closest('form');

		var postdata = {
			download_version: $('#template_openelec').val(),
			download_path: $('#download_path').val()
		};

		$form.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));

		$.post("/plugins/dynamix.vm.manager/templates/<?=basename(__FILE__)?>", postdata, function( data ) {
			if (data.error) {
				$("#download_status").html($("#download_status").html() + '<br><span style="color: red">' + data.error + '</span>');
			} else {
				var old_list = $("#download_status").html().split('<br>');

				if (old_list.pop().split(' ... ').shift() == data.status.split(' ... ').shift()) {
					old_list.push(data.status);
					$("#download_status").html(old_list.join('<br>'));
				} else {
					$("#download_status").html($("#download_status").html() + '<br>' + data.status);
				}

				if (data.status != 'Done') {
					setTimeout(frmDownload, 1000);
					return;
				}

				$("#template_openelec").find('option:selected').attr({
					localpath: data.localpath,
					localfolder:  data.localfolder,
					valid: '1'
				});
				$("#template_openelec").change();
			}

			$button.val($button.attr('readyvalue'));
			$form.find('input').prop('disabled', false);
		}, "json");
	});


	// Fire events below once upon showing page
	$("#template_openelec").change(function changeOpenELECVersion() {
		$selected = $(this).find('option:selected');

		if ($selected.attr('valid') === '0') {
			$(".available").slideDown('fast');
			$(".installed").slideUp('fast');
			$("#download_status").html('');
			$("#download_path").val($selected.attr('localfolder'));
			if ($selected.attr('localpath') !== '') {
				// auto click download button to see status of current running job
				$("#btnDownload").click();
			}
		} else {
			$(".available").slideUp('fast');
			$(".installed").slideDown('fast', function () {
				$("#form_content .domain_vcpu").change(); // restore the cpu checkbox disabled states

				// attach delete openelec image onclick event
				$("#form_content .delete_openelec_image").off().click(function deleteOEVersion() {
          swal({title:"Are you sure?",text:"Remove this OpenELEC file: ",type:"warning",showCancelButton:true},function() {
						$.post("/plugins/dynamix.vm.manager/templates/<?=basename(__FILE__)?>", {delete_version: $selected.val()}, function(data) {
							if (data.error) {
                swal({title:"VM image deletion error",text:data.error,type:"error"});
							} else if (data.status == 'ok') {
								$selected.attr({
									localpath: '',
									valid: '0'
								});
							}
							$("#template_openelec").change();
						}, "json");
					});
				}).hover(function () {
					$("#openelec_image").css('color', '#666');
				}, function () {
					$("#openelec_image").css('color', '#BBB');
				});
			});
			$("#form_content #disk_0").val($selected.attr('localpath'));
			$("#form_content #openelec_image").html($selected.attr('localpath'));
		}
	}).change(); // Fire now too!

	$("#form_content table[data-category]").each(function () {
		var category = $(this).data('category');

		updatePrefixLabels(category);
		bindSectionEvents(category);
	});

	$("#form_content .disk").not("[value='']")
		.attr('name', function(){ return $(this).attr('name').replace('new', 'image'); })
		.closest('table').find('.disk_file_options').hide()
		.filter('.advanced').removeClass('advanced').addClass('wasadvanced');

	$('#template_icon').val(vmicon);
	$('#template_img').prop('src', '/plugins/dynamix.vm.manager/templates/images/' + vmicon);

	initComplete = true;
});
</script>
