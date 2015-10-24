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
	$arrValidKeyMaps = getValidKeyMaps();
	$arrValidBridges = getNetworkBridges();
	$strCPUModel = getHostCPUModel();

	$arrOperatingSystems = [
		'windows' => 'Windows 8.1 / 2012',
		'windows7' => 'Windows 7 / 2008',
		'windowsxp' => 'Windows XP / 2003',
		'linux' => 'Linux',
		'arch' => 'Arch',
		'centos' => 'CentOS',
		'chromeos' => 'ChromeOS',
		'coreos' => 'CoreOS',
		'debian' => 'Debian',
		'fedora' => 'Fedora',
		'freebsd' => 'FreeBSD',
		'openelec' => 'OpenELEC',
		'opensuse' => 'OpenSUSE',
		'redhat' => 'RedHat',
		'scientific' => 'Scientific',
		'slackware' => 'Slackware',
		'steamos' => 'SteamOS',
		'ubuntu' => 'Ubuntu'
	];

	$arrConfigDefaults = [
		'template' => [
			'name' => 'Custom',
			'icon' => 'windows.png'
		],
		'domain' => [
			'persistent' => 1,
			'uuid' => $lv->domain_generate_uuid(),
			'clock' => 'localtime',
			'arch' => 'x86_64',
			'machine' => 'pc',
			'mem' => 512 * 1024,
			'maxmem' => 512 * 1024,
			'password' => '',
			'cpumode' => 'host-passthrough',
			'vcpus' => 1,
			'vcpu' => [0],
			'hyperv' => 1,
			'ovmf' => 0
		],
		'media' => [
			'cdrom' => '',
			'drivers' => $domain_cfg['VIRTIOISO']
		],
		'disk' => [
			[
				'new' => '',
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda'
			]
		],
		'gpu' => [
			[
				'id' => 'vnc',
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
				'source' => '',
				'target' => ''
			]
		]
	];

	// If we are editing a existing VM load it's existing configuration details
	$arrExistingConfig = (!empty($_GET['uuid']) ? domain_to_config($_GET['uuid']) : []);

	// Active config for this page
	$arrConfig = array_replace_recursive($arrConfigDefaults, $arrExistingConfig);

	// Add any custom metadata field defaults (e.g. os)
	if (empty($arrConfig['template']['os'])) {
		$arrConfig['template']['os'] = ($arrConfig['domain']['clock'] == 'localtime' ? 'windows' : 'linux');
	}

	$boolRunning = (!empty($arrConfig['domain']['state']) && $arrConfig['domain']['state'] == 'running');


	if (array_key_exists('createvm', $_POST)) {
		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));
		file_put_contents('/tmp/debug_libvirt_newxml.xml', $lv->config_to_xml($_POST));

		$tmp = $lv->domain_new($_POST);
		if (!$tmp){
			$arrResponse = ['error' => $lv->get_last_error()];
		} else {
			$arrResponse = ['success' => true];

			// Fire off the vnc popup if available
			$res = $lv->get_domain_by_name($_POST['domain']['name']);
			$vncport = $lv->domain_get_vnc_port($res);
			$wsport = $lv->domain_get_ws_port($res);

			if ($vncport > 0) {
				$vnc = '/plugins/dynamix.vm.manager/vnc.html?autoconnect=true&host='.$var['IPADDR'].'&port='.$wsport;
				$arrResponse['vncurl'] = $vnc;
			}
		}

		echo json_encode($arrResponse);
		exit;
	}

	if (array_key_exists('updatevm', $_POST)) {
		//DEBUG
		file_put_contents('/tmp/debug_libvirt_postparams.txt', print_r($_POST, true));
		file_put_contents('/tmp/debug_libvirt_updatexml.xml', $lv->config_to_xml($_POST));

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
</style>

<input type="hidden" name="domain[persistent]" value="<?=$arrConfig['domain']['persistent']?>">
<input type="hidden" name="domain[uuid]" value="<?=$arrConfig['domain']['uuid']?>">
<input type="hidden" name="domain[clock]" id="domain_clock" value="<?=$arrConfig['domain']['clock']?>">
<input type="hidden" name="domain[arch]" value="<?=$arrConfig['domain']['arch']?>">

<table>
	<tr>
		<td>Operating System:</td>
		<td>
			<select name="template[os]" id="domain_os" class="narrow" title="define the base OS">
			<?php mk_dropdown_options($arrOperatingSystems, $arrConfig['template']['os']); ?>
			</select>
		</td>
	</tr>
</table>
<blockquote class="inline_help">
	<p>Select Windows for any Microsoft operating systems</p>
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
		<td>Initial Memory:</td>
		<td>
			<select name="domain[mem]" id="domain_mem" class="narrow" title="define the amount memory">
			<?php
				echo mk_option($arrConfig['domain']['mem'], 128 * 1024, '128 MB');
				echo mk_option($arrConfig['domain']['mem'], 256 * 1024, '256 MB');
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
				echo mk_option($arrConfig['domain']['maxmem'], 128 * 1024, '128 MB');
				echo mk_option($arrConfig['domain']['maxmem'], 256 * 1024, '256 MB');
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
			<select name="domain[ovmf]" id="domain_ovmf" class="narrow" title="Select the BIOS.  SeaBIOS will work for most.  OVMF requires a UEFI-compatable OS (e.g. Windows 8/2012, newer Linux distros) and if using graphics device passthrough it too needs UEFI" <? if (!empty($arrConfig['domain']['state'])) echo 'disabled="disabled"'; ?>>
			<?php
				echo mk_option($arrConfig['domain']['ovmf'], '0', 'SeaBIOS');

				if (file_exists('/usr/share/qemu/ovmf-x64/OVMF-pure-efi.fd')) {
					echo mk_option($arrConfig['domain']['ovmf'], '1', 'OVMF');
				} else {
					echo mk_option('', '0', 'OVMF (Not Available)', 'disabled="disabled"');
				}
			?>
			</select>
			<?php if (!empty($arrConfig['domain']['state'])) { ?>
				<input type="hidden" name="domain[ovmf]" value="<?=$arrConfig['domain']['ovmf']?>">
			<?php } ?>
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

<table class="domain_os windows">
	<tr class="advanced">
		<td>Hyper-V:</td>
		<td>
			<select name="domain[hyperv]" id="hyperv" class="narrow" title="Hyperv tweaks for Windows.  Don't select if trying to passthrough Nvidia card">
			<?php mk_dropdown_options(['No', 'Yes'], $arrConfig['domain']['hyperv']); ?>
			</select>
		</td>
	</tr>
</table>
<div class="domain_os windows">
	<div class="advanced">
		<blockquote class="inline_help">
			<p>Exposes the guest to hyper-v extensions for Microsoft operating systems.  Set to "Yes" by default, but set to "No" automatically if an NVIDIA-based GPU is assigned to the guest (but can be user-toggled back to "Yes").</p>
		</blockquote>
	</div>
</div>

<table>
	<tr>
		<td>OS Install ISO:</td>
		<td>
			<input type="text" data-pickcloseonfile="true" data-pickfilter="iso" data-pickroot="<?=$domain_cfg['MEDIADIR']?>" name="media[cdrom]" value="<?=$arrConfig['media']['cdrom']?>" placeholder="Click and Select cdrom image to install operating system">
		</td>
	</tr>
</table>
<blockquote class="inline_help">
	<p>Select the virtual CD-ROM (ISO) that contains the installation media for your operating system.  Clicking this field displays a list of ISOs found in the directory specified on the Settings page.</p>
</blockquote>

<table class="domain_os windows">
	<tr class="advanced">
		<td><a href="https://fedoraproject.org/wiki/Windows_Virtio_Drivers#Direct_download" target="_blank">VirtIO Drivers ISO:</a></td>
		<td>
			<input type="text" data-pickcloseonfile="true" data-pickfilter="iso" data-pickroot="<?=$domain_cfg['MEDIADIR']?>" name="media[drivers]" value="<?=$arrConfig['media']['drivers']?>" placeholder="Download, Click and Select virtio drivers image">
		</td>
	</tr>
</table>
<div class="domain_os windows">
	<div class="advanced">
		<blockquote class="inline_help">
			<p>Specify the virtual CD-ROM (ISO) that contains the VirtIO Windows drivers as provided by the Fedora Project.  Download the latest ISO from here: <a href="https://fedoraproject.org/wiki/Windows_Virtio_Drivers#Direct_download" target="_blank">https://fedoraproject.org/wiki/Windows_Virtio_Drivers#Direct_download</a></p>
			<p>When installing Windows, you will reach a step where no disk devices will be found.  There is an option to browse for drivers on that screen.  Click browse and locate the additional CD-ROM in the menu.  Inside there will be various folders for the different versions of Windows.  Open the folder for the version of Windows you are installing and then select the AMD64 subfolder inside (even if you are on an Intel system, select AMD64).  Three drivers will be found.  Select them all, click next, and the vDisks you have assigned will appear.</p>
		</blockquote>
	</div>
</div>


<? foreach ($arrConfig['disk'] as $i => $arrDisk) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : 'Primary';

	?>
	<table data-category="vDisk" data-multiple="true" data-minimum="1" data-maximum="24" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
		<tr>
			<td>vDisk Location:</td>
			<td>
				<input type="text" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickroot="/mnt/" name="disk[<?=$i?>][new]" class="disk" id="disk_<?=$i?>" value="<?=$arrDisk['new']?>" placeholder="Separate sub-folder and image will be created based on Name">
			</td>
		</tr>

		<tr class="disk_file_options">
			<td>vDisk Size:</td>
			<td>
				<input type="text" name="disk[<?=$i?>][size]" value="<?=$arrDisk['size']?>" placeholder="e.g. 10M, 1G, 10G...">
			</td>
		</tr>

		<tr class="advanced disk_file_options">
			<td>vDisk Type:</td>
			<td>
				<select name="disk[<?=$i?>][driver]" class="narrow" title="type of storage image">
				<?php mk_dropdown_options($arrValidDiskDrivers, $arrDisk['driver']); ?>
				</select>
			</td>
		</tr>

		<input type="hidden" name="disk[<?=$i?>][dev]" value="<?=$arrDisk['dev']?>">
	</table>
	<?php if ($i == 0) { ?>
	<blockquote class="inline_help">
		<p>
			<b>vDisk Location</b><br>
			Specify a path to a user share in which you wish to store the VM or specify an existing vDisk.  The primary vDisk will store the operating system for your VM.
		</p>

		<p>
			<b>vDisk Size</b><br>
			Specify a number followed by a letter.  M for megabytes, G for gigabytes.
		</p>

		<p class="advanced">
			<b>vDisk Type</b><br>
			Select RAW for best performance.  QCOW2 implementation is still in development.
		</p>

		<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
	</blockquote>
	<? } ?>
<? } ?>
<script type="text/html" id="tmplvDisk">
	<table>
		<tr>
			<td>vDisk Location:</td>
			<td>
				<input type="text" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickroot="/mnt/" name="disk[{{INDEX}}][new]" class="disk" id="disk_{{INDEX}}" value="" placeholder="Separate sub-folder and image will be created based on Name">
			</td>
		</tr>

		<tr class="disk_file_options">
			<td>vDisk Size:</td>
			<td>
				<input type="text" name="disk[{{INDEX}}][size]" value="" placeholder="e.g. 10M, 1G, 10G...">
			</td>
		</tr>

		<tr class="advanced disk_file_options">
			<td>vDisk Type:</td>
			<td>
				<select name="disk[{{INDEX}}][driver]" class="narrow" title="type of storage image">
				<?php mk_dropdown_options($arrValidDiskDrivers, ''); ?>
				</select>
			</td>
		</tr>

		<input type="hidden" name="disk[{{INDEX}}][dev]" value="">
	</table>
</script>


<? foreach ($arrConfig['shares'] as $i => $arrShare) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

	?>
	<table class="domain_os other" data-category="Share" data-multiple="true" data-minimum="1" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
		<tr class="advanced">
			<td>unRAID Share:</td>
			<td>
				<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="<?=$arrShare['source']?>" name="shares[<?=$i?>][source]" placeholder="e.g. /mnt/user/..." title="path of unRAID share" />
			</td>
		</tr>

		<tr class="advanced">
			<td>unRAID Mount tag:</td>
			<td>
				<input type="text" value="<?=$arrShare['target']?>" name="shares[<?=$i?>][target]" placeholder="e.g. shares (name of mount tag inside vm)" title="mount tag inside vm" />
			</td>
		</tr>
	</table>
	<?php if ($i == 0) { ?>
	<div class="domain_os other">
		<div class="advanced">
			<blockquote class="inline_help">
				<p>
					<b>unRAID Share</b><br>
					Used to create a VirtFS mapping to a Linux-based guest.  Specify the path on the host here.
				</p>

				<p>
					<b>unRAID Mount tag</b><br>
					Specify the mount tag that you will use for mounting the VirtFS share inside the VM.  See this page for how to do this on a Linux-based guest: <a href="http://wiki.qemu.org/Documentation/9psetup" target="_blank">http://wiki.qemu.org/Documentation/9psetup</a>
				</p>

				<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
			</blockquote>
		</div>
	</div>
	<? } ?>
<? } ?>
<script type="text/html" id="tmplShare">
	<table class="domain_os other">
		<tr class="advanced">
			<td>unRAID Share:</td>
			<td>
				<input type="text" data-pickfolders="true" data-pickfilter="NO_FILES_FILTER" data-pickroot="/mnt/" value="" name="shares[{{INDEX}}][source]" placeholder="e.g. /mnt/user/..." title="path of unRAID share" />
			</td>
		</tr>

		<tr class="advanced">
			<td>unRAID Mount tag:</td>
			<td>
				<input type="text" value="" name="shares[{{INDEX}}][target]" placeholder="e.g. shares (name of mount tag inside vm)" title="mount tag inside vm" />
			</td>
		</tr>
	</table>
</script>


<? foreach ($arrConfig['gpu'] as $i => $arrGPU) {
	$strLabel = ($i > 0) ? appendOrdinalSuffix($i + 1) : '';

	?>
	<table data-category="Graphics_Card" data-multiple="true" data-minimum="1" data-maximum="<?=count($arrValidGPUDevices)+1?>" data-index="<?=$i?>" data-prefix="<?=$strLabel?>">
		<tr>
			<td>Graphics Card:</td>
			<td>
				<select name="gpu[<?=$i?>][id]" class="gpu narrow">
				<?php
					if ($i == 0) {
						// Only the first video card can be VNC
						echo mk_option($arrGPU['id'], 'vnc', 'VNC');
					} else {
						echo mk_option($arrGPU['id'], '', 'None');
					}

					foreach($arrValidGPUDevices as $arrDev) {
						echo mk_option($arrGPU['id'], $arrDev['id'], $arrDev['name']);
					}
				?>
				</select>
			</td>
		</tr>

		<? if ($i == 0) { ?>
		<tr class="vncpassword">
			<td>VNC Password:</td>
			<td><input type="password" name="domain[password]" title="password for VNC" placeholder="Password for VNC (optional)" /></td>
		</tr>
		<tr class="advanced vnckeymap">
			<td>VNC Keyboard:</td>
			<td>
				<select name="gpu[<?=$i?>][keymap]" title="keyboard for VNC">
				<?php mk_dropdown_options($arrValidKeyMaps, $arrGPU['keymap']); ?>
				</select>
			</td>
		</tr>
		<? } ?>
	</table>
	<?php if ($i == 0) { ?>
	<blockquote class="inline_help">
		<p>
			<b>Graphics Card</b><br>
			If you wish to assign a graphics card to the VM, select it from this list, otherwise leave it set to VNC.
		</p>

		<p class="vncpassword">
			<b>VNC Password</b><br>
			If you wish to require a password to connect to the VM over a VNC connection, specify one here.
		</p>

		<p class="advanced vnckeymap">
			<b>VNC Keyboard</b><br>
			If you wish to assign a different keyboard layout to use for a VNC connection, specify one here.
		</p>

		<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
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
		<p>Additional devices can be added/removed by clicking the symbols to the left.</p>
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
	<p>Click Create to generate the vDisks and return to the Virtual Machines page where your new VM will be created.</p>
</blockquote>


<script type="text/javascript">
var OS2ImageMap = {<?php
	$arrItems = array();
	foreach ($arrOperatingSystems as $key => $value) {
		$arrItems[] = "'$key':'{$key}.png'";
	}
	echo implode(',', $arrItems);
?>};

$(function() {
	var initComplete = false;

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
		var mylabel = $(this).children('option:selected').text();

		$vnc_sections = $('.vncpassword,.vnckeymap');
		if ($(".gpu option[value='vnc']:selected").length) {
			$vnc_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
			slideDownRows($vnc_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
		} else {
			slideUpRows($vnc_sections);
			$vnc_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
		}

		if (mylabel.indexOf('NVIDIA ') > -1) {
			$("#hyperv").val(0).change();
		}

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
				if (data.vncurl) {
					window.open(data.vncurl, '_blank', 'scrollbars=yes,resizable=yes');
				}
				done();
			}
			if (data.error) {
        swal({title:"VM creation error",text:data.error,type:"error"});
				$form.find('input').prop('disabled', false);
				$("#form_content .domain_vcpu").change(); // restore the cpu checkbox disabled states
				<? if (!empty($arrConfig['domain']['state'])) echo '$(\'#domain_ovmf\').prop(\'disabled\', true); // restore bios disabled state' . "\n"; ?>
				$button.val($button.attr('readyvalue'));
			}
		}, "json");
	});

	$("#form_content #btnCancel").click(done);


	// Fire events below once upon showing page
	$("#form_content table[data-category]").each(function () {
		var category = $(this).data('category');

		updatePrefixLabels(category);
		bindSectionEvents(category);
	});

	$("#form_content #domain_os").change(function changeOSEvent() {
		var os_casted = ($(this).val().indexOf('windows') == -1 ? 'other' : 'windows');

		if (initComplete && !$('#template_img').attr('touched')) {
			var vmicon = OS2ImageMap[$(this).val()] || OS2ImageMap[os_casted];
			$('#template_icon').val(vmicon);
			$('#template_img').prop('src', '/plugins/dynamix.vm.manager/templates/images/' + vmicon);
		}

		slideUpRows($('.domain_os').not($('.' + os_casted)));
		slideDownRows($('.domain_os.' + os_casted).not(isVMAdvancedMode() ? '.basic' : '.advanced'));

		if (initComplete) {
			if (os_casted == 'windows') {
				$('#domain_clock').val('localtime');
				$("#domain_machine option").each(function(){
					if ($(this).val().indexOf('i440fx') != -1) {
						$('#domain_machine').val($(this).val());
						return false;
					}
				});
			} else {
				$('#domain_clock').val('utc');
				$("#domain_machine option").each(function(){
					if ($(this).val().indexOf('q35') != -1) {
						$('#domain_machine').val($(this).val());
						return false;
					}
				});
			}
		}
	}).change(); // Fire now too!

	if ($(".gpu option[value='vnc']:selected").length) {
		$('.vncpassword,.vnckeymap').not(isVMAdvancedMode() ? '.basic' : '.advanced').show();
	} else {
		$('.vncpassword,.vnckeymap').hide();
	}

	$("#form_content .disk").not("[value='']")
		.attr('name', function(){ return $(this).attr('name').replace('new', 'image'); })
		.closest('table').find('.disk_file_options').hide()
		.filter('.advanced').removeClass('advanced').addClass('wasadvanced');

	initComplete = true;
});
</script>
