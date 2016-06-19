<?PHP
/* Copyright 2016, Lime Technology
 * Copyright 2016, Derek Macias, Eric Schultz, Jon Panozzo.
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

	// Read localpaths in from libreelec.cfg
	$strLibreELECConfig = "/boot/config/plugins/dynamix.vm.manager/libreelec.cfg";
	$arrLibreELECConfig = [];

	if (file_exists($strLibreELECConfig)) {
		$arrLibreELECConfig = parse_ini_file($strLibreELECConfig);
	} elseif (!file_exists(dirname($strLibreELECConfig))) {
		@mkdir(dirname($strLibreELECConfig), 0777, true);
	}


	// Compare libreelec.cfg and populate 'localpath' in $arrOEVersion
	foreach ($arrLibreELECConfig as $strID => $strLocalpath) {
		if (array_key_exists($strID, $arrLibreELECVersions)) {
			$arrLibreELECVersions[$strID]['localpath'] = $strLocalpath;
			if (file_exists($strLocalpath)) {
				$arrLibreELECVersions[$strID]['valid'] = '1';
			}
		}
	}

	if (array_key_exists('delete_version', $_POST)) {

		$arrDeleteLibreELEC = [];
		if (array_key_exists($_POST['delete_version'], $arrLibreELECVersions)) {
			$arrDeleteLibreELEC = $arrLibreELECVersions[$_POST['delete_version']];
		}

		$arrResponse = [];

		if (empty($arrDeleteLibreELEC)) {
			$arrResponse = ['error' => 'Unknown version: ' . $_POST['delete_version']];
		} else {
			// delete img file
			@unlink($arrDeleteLibreELEC['localpath']);

			// Save to strLibreELECConfig
			unset($arrLibreELECConfig[$_POST['delete_version']]);
			$text = '';
			foreach ($arrLibreELECConfig as $key => $value) $text .= "$key=\"$value\"\n";
			file_put_contents($strLibreELECConfig, $text);

			$arrResponse = ['status' => 'ok'];
		}

		echo json_encode($arrResponse);
		exit;
	}

	if (array_key_exists('download_path', $_POST)) {

		$arrDownloadLibreELEC = [];
		if (array_key_exists($_POST['download_version'], $arrLibreELECVersions)) {
			$arrDownloadLibreELEC = $arrLibreELECVersions[$_POST['download_version']];
		}

		if (empty($arrDownloadLibreELEC)) {
			$arrResponse = ['error' => 'Unknown version: ' . $_POST['download_version']];
		} else if (empty($_POST['download_path'])) {
			$arrResponse = ['error' => 'Please choose a folder the LibreELEC image will download to'];
		} else {
			@mkdir($_POST['download_path'], 0777, true);
			$_POST['download_path'] = realpath($_POST['download_path']) . '/';

			$boolCheckOnly = !empty($_POST['checkonly']);

			$strInstallScript = '/tmp/LibreELEC_' . $_POST['download_version'] . '_install.sh';
			$strInstallScriptPgrep = '-f "LibreELEC_' . $_POST['download_version'] . '_install.sh"';
			$strTempFile = $_POST['download_path'] . basename($arrDownloadLibreELEC['url']);
			$strLogFile = $strTempFile . '.log';
			$strMD5File = $strTempFile . '.md5';
			$strMD5StatusFile = $strTempFile . '.md5status';
			$strExtractedFile = $_POST['download_path'] . basename($arrDownloadLibreELEC['url'], 'tar.xz') . 'img';


			// Save to strLibreELECConfig
			$arrLibreELECConfig[$_POST['download_version']] = $strExtractedFile;
			$text = '';
			foreach ($arrLibreELECConfig as $key => $value) $text .= "$key=\"$value\"\n";
			file_put_contents($strLibreELECConfig, $text);


			$strDownloadCmd = 'wget -nv -c -O ' . escapeshellarg($strTempFile) . ' ' . escapeshellarg($arrDownloadLibreELEC['url']);
			$strDownloadPgrep = '-f "wget.*' . $strTempFile . '.*' . $arrDownloadLibreELEC['url'] . '"';

			$strVerifyCmd = 'md5sum -c ' . escapeshellarg($strMD5File);
			$strVerifyPgrep = '-f "md5sum.*' . $strMD5File . '"';

			$strExtractCmd = 'tar Jxf ' . escapeshellarg($strTempFile) . ' -C ' . escapeshellarg(dirname($strTempFile));
			$strExtractPgrep = '-f "tar.*' . $strTempFile . '.*' . dirname($strTempFile) . '"';

			$strCleanCmd = '(chmod 777 ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strExtractedFile) . '; chown nobody:users ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strExtractedFile) . '; rm ' . escapeshellarg($strTempFile) . ' ' . escapeshellarg($strMD5File) . ' ' . escapeshellarg($strMD5StatusFile) . ')';
			$strCleanPgrep = '-f "chmod.*chown.*rm.*' . $strMD5StatusFile . '"';

			$strAllCmd = "#!/bin/bash\n\n";
			$strAllCmd .= $strDownloadCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'echo "' . $arrDownloadLibreELEC['md5'] . '  ' . $strTempFile . '" > ' . escapeshellarg($strMD5File) . ' && ';
			$strAllCmd .= $strVerifyCmd . ' >' . escapeshellarg($strMD5StatusFile) . ' 2>/dev/null && ';
			$strAllCmd .= $strExtractCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= $strCleanCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'rm ' . escapeshellarg($strLogFile) . ' && ';
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
						$strPercent = round(($intSize / $arrDownloadLibreELEC['size']) * 100);
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

					if (!pgrep($strInstallScriptPgrep) && !$boolCheckOnly) {

						// Run all commands
						file_put_contents($strInstallScript, $strAllCmd);
						chmod($strInstallScript, 0777);
						exec($strInstallScript . ' >/dev/null 2>&1 &');

					}

				}

			} else if (!$boolCheckOnly) {

				if (!pgrep($strInstallScriptPgrep)) {

					// Run all commands
					file_put_contents($strInstallScript, $strAllCmd);
					chmod($strInstallScript, 0777);
					exec($strInstallScript . ' >/dev/null 2>&1 &');

				}

				$arrResponse['status'] = 'Downloading ... ';

			}

			$arrResponse['pid'] = pgrep($strInstallScriptPgrep);

		}

		echo json_encode($arrResponse);
		exit;
	}

	$arrLibreELECVersion = reset($arrLibreELECVersions);
	$strLibreELECVersionID = key($arrLibreELECVersions);

	$arrConfigDefaults = [
		'template' => [
			'name' => $strSelectedTemplate,
			'icon' => $arrAllTemplates[$strSelectedTemplate]['icon'],
			'libreelec' => $strLibreELECVersionID
		],
		'domain' => [
			'name' => $strSelectedTemplate,
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
			'ovmf' => 1,
			'usbmode' => 'usb3'
		],
		'media' => [
			'cdrom' => '',
			'cdrombus' => '',
			'drivers' => '',
			'driversbus' => ''
		],
		'disk' => [
			[
				'image' => $arrLibreELECVersion['localpath'],
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda',
				'readonly' => 1
			]
		],
		'gpu' => [
			[
				'id' => '',
				'mode' => 'qxl',
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
				'source' => (is_dir('/mnt/user/appdata') ? '/mnt/user/appdata/LibreELEC/' : ''),
				'target' => 'appconfig'
			]
		]
	];

	// Merge in any default values from the VM template
	if (!empty($arrAllTemplates[$strSelectedTemplate]) && !empty($arrAllTemplates[$strSelectedTemplate]['overrides'])) {
		$arrConfigDefaults = array_replace_recursive($arrConfigDefaults, $arrAllTemplates[$strSelectedTemplate]['overrides']);
	}

	// If we are editing a existing VM load it's existing configuration details
	$arrExistingConfig = (!empty($_GET['uuid']) ? domain_to_config($_GET['uuid']) : []);

	// Active config for this page
	$arrConfig = array_replace_recursive($arrConfigDefaults, $arrExistingConfig);

	if (array_key_exists($arrConfig['template']['libreelec'], $arrLibreELECVersions)) {
		$arrConfigDefaults['disk'][0]['image'] = $arrLibreELECVersions[$arrConfig['template']['libreelec']]['localpath'];
	}

	$boolNew = empty($arrExistingConfig);
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
		$dom = $lv->domain_get_domain_by_uuid($_POST['domain']['uuid']);
		if ($dom) {
			$strOldXML = $lv->domain_get_xml($dom);
			$boolOldAutoStart = $lv->domain_get_autostart($dom);
			$strOldName = $lv->domain_get_name($dom);
			$strNewName = $_POST['domain']['name'];

			if (!empty($strOldName) &&
				 !empty($strNewName) &&
				 is_dir($domain_cfg['DOMAINDIR'].$strOldName.'/') &&
				 !is_dir($domain_cfg['DOMAINDIR'].$strNewName.'/')) {

				// mv domain/vmname folder
				if (rename($domain_cfg['DOMAINDIR'].$strOldName, $domain_cfg['DOMAINDIR'].$strNewName)) {
					// replace all disk paths in xml
					foreach ($_POST['disk'] as &$arrDisk) {
						if (!empty($arrDisk['new'])) {
							$arrDisk['new'] = str_replace($domain_cfg['DOMAINDIR'].$strOldName.'/', $domain_cfg['DOMAINDIR'].$strNewName.'/', $arrDisk['new']);
						}
						if (!empty($arrDisk['image'])) {
							$arrDisk['image'] = str_replace($domain_cfg['DOMAINDIR'].$strOldName.'/', $domain_cfg['DOMAINDIR'].$strNewName.'/', $arrDisk['image']);
						}
					}
				}
			}

			//DEBUG
			file_put_contents('/tmp/debug_libvirt_oldxml.xml', $strOldXML);
		}

		// Remove existing domain
		$lv->nvram_backup($_POST['domain']['uuid']);
		$lv->domain_undefine($dom);
		$lv->nvram_restore($_POST['domain']['uuid']);

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
	#libreelec_image {
		color: #BBB;
		display: none;
		transform: translate(0px, 3px);
	}
	.delete_libreelec_image {
		cursor: pointer;
		margin-left: -5px;
		margin-right: 5px;
		color: #CC0011;
		font-size: 1.3em;
		transform: translate(0px, 3px);
	}
</style>

<input type="hidden" name="domain[persistent]" value="<?=$arrConfig['domain']['persistent']?>">
<input type="hidden" name="domain[uuid]" value="<?=$arrConfig['domain']['uuid']?>">
<input type="hidden" name="domain[clock]" id="domain_clock" value="<?=$arrConfig['domain']['clock']?>">
<input type="hidden" name="domain[arch]" value="<?=$arrConfig['domain']['arch']?>">
<input type="hidden" name="domain[oldname]" value="<?=htmlentities($arrConfig['domain']['name'])?>">

<input type="hidden" name="disk[0][image]" id="disk_0" value="<?=$arrConfig['disk'][0]['image']?>">
<input type="hidden" name="disk[0][dev]" value="<?=$arrConfig['disk'][0]['dev']?>">
<input type="hidden" name="disk[0][readonly]" value="1">

<div class="installed">
	<table>
		<tr>
			<td>Name:</td>
			<td><input type="text" name="domain[name]" id="domain_name" class="textTemplate" title="Name of virtual machine" placeholder="e.g. LibreELEC" value="<?=htmlentities($arrConfig['domain']['name'])?>" required /></td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Give the VM a name (e.g. LibreELEC Family Room, LibreELEC Theatre, LibreELEC)</p>
	</blockquote>

	<table>
		<tr class="advanced">
			<td>Description:</td>
			<td><input type="text" name="domain[desc]" title="description of virtual machine" placeholder="description of virtual machine (optional)" value="<?=htmlentities($arrConfig['domain']['desc'])?>" /></td>
		</tr>
	</table>
	<div class="advanced">
		<blockquote class="inline_help">
			<p>Give the VM a brief description (optional field).</p>
		</blockquote>
	</div>
</div>

<table>
	<tr>
		<td>LibreELEC Version:</td>
		<td>
			<select name="template[libreelec]" id="template_libreelec" class="narrow" title="Select the LibreELEC version to use">
			<?php
				foreach ($arrLibreELECVersions as $strOEVersion => $arrOEVersion) {
					$strDefaultFolder = '';
					if (!empty($domain_cfg['DOMAINDIR']) && file_exists($domain_cfg['DOMAINDIR'])) {
						$strDefaultFolder = str_replace('//', '/', $domain_cfg['DOMAINDIR'].'/LibreELEC/');
					}
					$strLocalFolder = ($arrOEVersion['localpath'] == '' ? $strDefaultFolder : dirname($arrOEVersion['localpath']));
					echo mk_option($arrConfig['template']['libreelec'], $strOEVersion, $arrOEVersion['name'], 'localpath="' . $arrOEVersion['localpath'] . '" localfolder="' . $strLocalFolder . '" valid="' . $arrOEVersion['valid'] . '"');
				}
			?>
			</select> <i class="fa fa-trash delete_libreelec_image installed" title="Remove LibreELEC image"></i> <span id="libreelec_image" class="installed"></span>
		</td>
	</tr>
</table>
<blockquote class="inline_help">
	<p>Select which LibreELEC version to download or use for this VM</p>
</blockquote>

<div class="available">
	<table>
		<tr>
			<td>Download Folder:</td>
			<td>
				<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="" id="download_path" placeholder="e.g. /mnt/user/domains/" title="Folder to save the LibreELEC image to" />
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Choose a folder where the LibreELEC image will downloaded to</p>
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
				<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="<?=$arrConfig['shares'][0]['source']?>" name="shares[0][source]" placeholder="e.g. /mnt/user/appdata/libreelec" title="path on unRAID share to save LibreELEC settings" required/>
				<input type="hidden" value="<?=$arrConfig['shares'][0]['target']?>" name="shares[0][target]" />
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>Choose a folder or type in a new name off of an existing folder to specify where LibreELEC will save configuration files.  If you create multiple LibreELEC VMs, these Config Folders must be unique for each instance.</p>
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
			<td>Logical CPUs:</td>
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
					<label for="vcpu<?=$i?>"><input type="checkbox" name="domain[vcpu][]" class="domain_vcpu" id="vcpu<?=$i?>" value="<?=$i?>" <?=$extra;?>/> CPU <?=$i?></label>
				<?php } ?>
				</div>
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>The number of logical CPUs in your system is determined by multiplying the number of CPU cores on your processor(s) by the number of threads.</p>
		<p>Select which logical CPUs you wish to allow your VM to use. (minimum 1).</p>
	</blockquote>

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
			<p>For VMs where no PCI devices are being passed through (GPUs, sound, etc.), you can set different values to initial and max memory to allow for memory ballooning.  If you are passing through a PCI device, only the initial memory value is used and the max memory value is ignored.  For more information on KVM memory ballooning, see <a href="http://www.linux-kvm.org/page/FAQ#Is_dynamic_memory_management_for_guests_supported.3F" target="_new">here</a>.</p>
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

					if (file_exists('/usr/share/qemu/ovmf-x64/OVMF_CODE-pure-efi.fd')) {
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
						if ($i == 0) {
							// Only the first video card can be VNC
							echo mk_option($arrGPU['id'], 'vnc', 'VNC');
						} else {
							echo mk_option($arrGPU['id'], '', 'None');
						}

						foreach($arrValidGPUDevices as $arrDev) {
							echo mk_option($arrGPU['id'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
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
							echo mk_option('', $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
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
							echo mk_option($arrAudio['id'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
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
							echo mk_option('', $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
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
				<div class="textarea" style="width: 540px">
				<?php
					if (!empty($arrValidUSBDevices)) {
						foreach($arrValidUSBDevices as $i => $arrDev) {
						?>
						<label for="usb<?=$i?>"><input type="checkbox" name="usb[]" id="usb<?=$i?>" value="<?=$arrDev['id']?>" <?php if (count(array_filter($arrConfig['usb'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) echo 'checked="checked"'; ?>/> <?=$arrDev['name']?> (<?=$arrDev['id']?>)</label><br/>
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
		<tr class="advanced">
			<td>USB Mode:</td>
			<td>
				<select name="domain[usbmode]" id="usbmode" class="narrow" title="Select the USB Mode to emulate.">
				<?php
					echo mk_option($arrConfig['domain']['usbmode'], 'usb2', '2.0 (EHCI)');
					echo mk_option($arrConfig['domain']['usbmode'], 'usb3', '3.0 (XHCI)');
				?>
				</select>
			</td>
		</tr>
	</table>
	<div class="advanced">
		<blockquote class="inline_help">
			<p>
				<b>USB Mode</b><br>
				Select the USB Mode to emulate.
			</p>
		</blockquote>
	</div>

	<table>
		<tr>
			<td>Other PCI Devices:</td>
			<td>
				<div class="textarea" style="width: 540px">
				<?
					$intAvailableOtherPCIDevices = 0;

					if (!empty($arrValidOtherDevices)) {
						foreach($arrValidOtherDevices as $i => $arrDev) {
							$extra = '';
							if (count(array_filter($arrConfig['pci'], function($arr) use ($arrDev) { return ($arr['id'] == $arrDev['id']); }))) {
								$extra .= ' checked="checked"';
							} else if (!in_array($arrDev['driver'], ['pci-stub', 'vfio-pci'])) {
								//$extra .= ' disabled="disabled"';
								continue;
							}
							$intAvailableOtherPCIDevices++;
					?>
						<label for="pci<?=$i?>"><input type="checkbox" name="pci[]" id="pci<?=$i?>" value="<?=$arrDev['id']?>" <?=$extra?>/> <?=$arrDev['name']?> | <?=$arrDev['type']?> (<?=$arrDev['id']?>)</label><br/>
					<?
						}
					}

					if (empty($intAvailableOtherPCIDevices)) {
						echo "<i>None available</i>";
					}
				?>
				</div>
			</td>
		</tr>
	</table>
	<blockquote class="inline_help">
		<p>If you wish to assign any other PCI devices to your guest, you can select them from this list.</p>
	</blockquote>

	<table>
		<tr>
			<td></td>
			<td>
			<? if (!$boolRunning) { ?>
				<? if (!$boolNew) { ?>
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
	<? if ($boolNew) { ?>
	<blockquote class="inline_help">
		<p>Click Create to return to the Virtual Machines page where your new VM will be created.</p>
	</blockquote>
	<? } ?>
</div>

<script type="text/javascript">
$(function() {
	$("#vmform .domain_vcpu").change(function changeVCPUEvent() {
		var $cores = $("#vmform .domain_vcpu:checked");

		if ($cores.length == 1) {
			$cores.prop("disabled", true);
		} else {
			$("#vmform .domain_vcpu").prop("disabled", false);
		}
	});

	$("#vmform #domain_mem").change(function changeMemEvent() {
		$("#vmform #domain_maxmem").val($(this).val());
	});

	$("#vmform #domain_maxmem").change(function changeMaxMemEvent() {
		if (parseFloat($(this).val()) < parseFloat($("#vmform #domain_mem").val())) {
			$("#vmform #domain_mem").val($(this).val());
		}
	});

	$("#vmform").on("change", ".gpu", function changeGPUEvent() {
		var myvalue = $(this).val();

		$("#vmform .gpu").not(this).each(function () {
			if (myvalue == $(this).val()) {
				$(this).prop("selectedIndex", 0).change();
			}
		});
	});

	$("#vmform").on("click", ".mac_generate", function generateMac() {
		var $input = $(this).prev('input');

		$.getJSON("/plugins/dynamix.vm.manager/VMajax.php?action=generate-mac", function (data) {
			if (data.mac) {
				$input.val(data.mac);
			}
		});
	});

	$("#vmform #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $form = $button.closest('form');

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
				$("#vmform .domain_vcpu").change(); // restore the cpu checkbox disabled states
				$button.val($button.attr('readyvalue'));
			}
		}, "json");
	});

	var checkDownloadTimer = null;
	var checkOrInitDownload = function(checkonly) {
		clearTimeout(checkDownloadTimer);

		var $button = $("#vmform #btnDownload");
		var $form = $button.closest('form');

		var postdata = {
			download_version: $('#vmform #template_libreelec').val(),
			download_path: $('#vmform #download_path').val(),
			checkonly: ((typeof checkonly === 'undefined') ? false : !!checkonly) ? 1 : 0
		};

		$form.find('input').prop('disabled', true);
		$button.val($button.attr('busyvalue'));

		$.post("/plugins/dynamix.vm.manager/templates/<?=basename(__FILE__)?>", postdata, function( data ) {
			if (data.error) {
				$("#vmform #download_status").html($("#vmform #download_status").html() + '<br><span style="color: red">' + data.error + '</span>');
			} else if (data.status) {
				var old_list = $("#vmform #download_status").html().split('<br>');

				if (old_list.pop().split(' ... ').shift() == data.status.split(' ... ').shift()) {
					old_list.push(data.status);
					$("#vmform #download_status").html(old_list.join('<br>'));
				} else {
					$("#vmform #download_status").html($("#vmform #download_status").html() + '<br>' + data.status);
				}

				if (data.pid) {
					checkDownloadTimer = setTimeout(checkOrInitDownload, 1000);
					return;
				}

				if (data.status == 'Done') {
					$("#vmform #template_libreelec").find('option:selected').attr({
						localpath: data.localpath,
						localfolder:  data.localfolder,
						valid: '1'
					});
					$("#vmform #template_libreelec").change();
				}
			}

			$button.val($button.attr('readyvalue'));
			$form.find('input').prop('disabled', false);
		}, "json");
	};

	$("#vmform #btnDownload").click(function changeVirtIOVersion() {
		checkOrInitDownload(false);
	});

	// Fire events below once upon showing page
	$("#vmform #template_libreelec").change(function changeLibreELECVersion() {
		clearTimeout(checkDownloadTimer);

		$selected = $(this).find('option:selected');

		if ($selected.attr('valid') === '0') {
			$("#vmform .available").slideDown('fast');
			$("#vmform .installed").slideUp('fast');
			$("#vmform #download_status").html('');
			$("#vmform #download_path").val($selected.attr('localfolder'));
			if ($selected.attr('localpath') !== '') {
				// Check status of current running job (but dont initiate a new download)
				checkOrInitDownload(true);
			}
		} else {
			$("#vmform .available").slideUp('fast');
			$("#vmform .installed").slideDown('fast', function () {
				$("#vmform .domain_vcpu").change(); // restore the cpu checkbox disabled states

				// attach delete libreelec image onclick event
				$("#vmform .delete_libreelec_image").off().click(function deleteOEVersion() {
					swal({title:"Are you sure?",text:"Remove this LibreELEC file:\n"+$selected.attr('localpath'),type:"warning",showCancelButton:true},function() {
						$.post("/plugins/dynamix.vm.manager/templates/<?=basename(__FILE__)?>", {delete_version: $selected.val()}, function(data) {
							if (data.error) {
								swal({title:"VM image deletion error",text:data.error,type:"error"});
							} else if (data.status == 'ok') {
								$selected.attr({
									localpath: '',
									valid: '0'
								});
							}
							$("#vmform #template_libreelec").change();
						}, "json");
					});
				}).hover(function () {
					$("#vmform #libreelec_image").css('color', '#666');
				}, function () {
					$("#vmform #libreelec_image").css('color', '#BBB');
				});
			});
			$("#vmform #disk_0").val($selected.attr('localpath'));
			$("#vmform #libreelec_image").html($selected.attr('localpath'));
		}
	}).change(); // Fire now too!
});
</script>
