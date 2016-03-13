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
	$arrValidBusTypes = getValidBusTypes();
	$arrValidVNCModels = getValidVNCModels();
	$arrValidKeyMaps = getValidKeyMaps();
	$arrValidBridges = getNetworkBridges();
	$strCPUModel = getHostCPUModel();

	$arrConfigDefaults = [
		'template' => [
			'name' => $strSelectedTemplate,
			'icon' => $arrAllTemplates[$strSelectedTemplate]['icon'],
			'os' => $arrAllTemplates[$strSelectedTemplate]['os']
		],
		'domain' => [
			'name' => $strSelectedTemplate,
			'persistent' => 1,
			'uuid' => $lv->domain_generate_uuid(),
			'clock' => 'localtime',
			'arch' => 'x86_64',
			'machine' => 'pc',
			'mem' => 1024 * 1024,
			'maxmem' => 1024 * 1024,
			'password' => '',
			'cpumode' => 'host-passthrough',
			'vcpus' => 1,
			'vcpu' => [0],
			'hyperv' => 1,
			'ovmf' => 1,
			'usbmode' => 'usb2'
		],
		'media' => [
			'cdrom' => '',
			'cdrombus' => 'ide',
			'drivers' => is_file($domain_cfg['VIRTIOISO']) ? $domain_cfg['VIRTIOISO'] : '',
			'driversbus' => 'ide'
		],
		'disk' => [
			[
				'new' => '',
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda',
				'select' => $domain_cfg['VMSTORAGEMODE'],
				'bus' => 'virtio'
			]
		],
		'gpu' => [
			[
				'id' => 'vnc',
				'model' => 'qxl',
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

	// Merge in any default values from the VM template
	if (!empty($arrAllTemplates[$strSelectedTemplate]) && !empty($arrAllTemplates[$strSelectedTemplate]['overrides'])) {
		$arrConfigDefaults = array_replace_recursive($arrConfigDefaults, $arrAllTemplates[$strSelectedTemplate]['overrides']);
	}

	// If we are editing a existing VM load it's existing configuration details
	$arrExistingConfig = (!empty($_GET['uuid']) ? domain_to_config($_GET['uuid']) : []);

	// Active config for this page
	$arrConfig = array_replace_recursive($arrConfigDefaults, $arrExistingConfig);

	// Add any custom metadata field defaults (e.g. os)
	if (empty($arrConfig['template']['os'])) {
		$arrConfig['template']['os'] = ($arrConfig['domain']['clock'] == 'localtime' ? 'windows' : 'linux');
	}

	$boolNew = empty($arrExistingConfig);
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
			$dom = $lv->get_domain_by_name($_POST['domain']['name']);
			$vncport = $lv->domain_get_vnc_port($dom);
			$wsport = $lv->domain_get_ws_port($dom);

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

<input type="hidden" name="template[os]" id="template_os" value="<?=$arrConfig['template']['os']?>">
<input type="hidden" name="domain[persistent]" value="<?=$arrConfig['domain']['persistent']?>">
<input type="hidden" name="domain[uuid]" value="<?=$arrConfig['domain']['uuid']?>">
<input type="hidden" name="domain[clock]" id="domain_clock" value="<?=$arrConfig['domain']['clock']?>">
<input type="hidden" name="domain[arch]" value="<?=$arrConfig['domain']['arch']?>">
<input type="hidden" name="domain[oldname]" id="domain_oldname" value="<?=htmlentities($arrConfig['domain']['name'])?>">

<table>
	<tr>
		<td>Name:</td>
		<td><input type="text" name="domain[name]" id="domain_name" class="textTemplate" title="Name of virtual machine" placeholder="e.g. My Workstation" value="<?=htmlentities($arrConfig['domain']['name'])?>" required /></td>
	</tr>
</table>
<blockquote class="inline_help">
	<p>Give the VM a name (e.g. Work, Gaming, Media Player, Firewall, Bitcoin Miner)</p>
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
			<select name="domain[ovmf]" id="domain_ovmf" class="narrow" title="Select the BIOS.  SeaBIOS will work for most.  OVMF requires a UEFI-compatable OS (e.g. Windows 8/2012, newer Linux distros) and if using graphics device passthrough it too needs UEFI" <? if (!empty($arrConfig['domain']['state'])) echo 'disabled="disabled"'; ?>>
			<?php
				echo mk_option($arrConfig['domain']['ovmf'], '0', 'SeaBIOS');

				if (file_exists('/usr/share/qemu/ovmf-x64/OVMF_CODE-pure-efi.fd')) {
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
			<p>Exposes the guest to hyper-v extensions for Microsoft operating systems.</p>
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
	<tr class="advanced">
		<td>OS Install CDRom Bus:</td>
		<td>
			<select name="media[cdrombus]" class="cdrom_bus narrow">
			<?php mk_dropdown_options($arrValidBusTypes, $arrConfig['media']['cdrombus']); ?>
			</select>
		</td>
	</tr>
</table>
<blockquote class="inline_help">
	<p>Select the virtual CD-ROM (ISO) that contains the installation media for your operating system.  Clicking this field displays a list of ISOs found in the directory specified on the Settings page.</p>
	<p class="advanced">
		<b>CDRom Bus</b><br>
		Specify what interface this virtual cdrom uses to connect inside the VM.
	</p>
</blockquote>

<table class="domain_os windows">
	<tr class="advanced">
		<td>VirtIO Drivers ISO:</td>
		<td>
			<input type="text" data-pickcloseonfile="true" data-pickfilter="iso" data-pickroot="<?=$domain_cfg['MEDIADIR']?>" name="media[drivers]" value="<?=$arrConfig['media']['drivers']?>" placeholder="Download, Click and Select virtio drivers image">
		</td>
	</tr>
	<tr class="advanced">
		<td>VirtIO Drivers CDRom Bus:</td>
		<td>
			<select name="media[driversbus]" class="cdrom_bus narrow">
			<?php mk_dropdown_options($arrValidBusTypes, $arrConfig['media']['driversbus']); ?>
			</select>
		</td>
	</tr>
</table>
<div class="domain_os windows">
	<div class="advanced">
		<blockquote class="inline_help">
			<p>Specify the virtual CD-ROM (ISO) that contains the VirtIO Windows drivers as provided by the Fedora Project.  Download the latest ISO from here: <a href="https://fedoraproject.org/wiki/Windows_Virtio_Drivers#Direct_download" target="_blank">https://fedoraproject.org/wiki/Windows_Virtio_Drivers#Direct_download</a></p>
			<p>When installing Windows, you will reach a step where no disk devices will be found.  There is an option to browse for drivers on that screen.  Click browse and locate the additional CD-ROM in the menu.  Inside there will be various folders for the different versions of Windows.  Open the folder for the version of Windows you are installing and then select the AMD64 subfolder inside (even if you are on an Intel system, select AMD64).  Three drivers will be found.  Select them all, click next, and the vDisks you have assigned will appear.</p>
			<p>
				<b>CDRom Bus</b><br>
				Specify what interface this virtual cdrom uses to connect inside the VM.
			</p>
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
				<select name="disk[<?=$i?>][select]" class="disk_select narrow">
				<?
					if ($i == 0) {
						echo '<option value="">None</option>';
					}

					$default_option = $arrDisk['select'];

					if (!empty($domain_cfg['DOMAINDIR']) && file_exists($domain_cfg['DOMAINDIR'])) {

						$boolShowAllDisks = (strpos($domain_cfg['DOMAINDIR'], '/mnt/user/') === 0);

						if (!empty($arrDisk['new'])) {
							if (strpos($domain_cfg['DOMAINDIR'], dirname(dirname($arrDisk['new']))) === false || basename($arrDisk['new']) != 'vdisk'.($i+1).'.img') {
								$default_option = 'manual';
							}
							if (file_exists(dirname(dirname($arrDisk['new'])).'/'.$arrConfig['domain']['name'].'/vdisk'.($i+1).'.img')) {
								// hide all the disks because the auto disk already has been created
								$boolShowAllDisks = false;
							}
						}

						echo mk_option($default_option, 'auto', 'Auto');

						if ($boolShowAllDisks) {
							$strShareUserLocalInclude = '';
							$strShareUserLocalExclude = '';
							$strShareUserLocalUseCache = 'no';

							// Get the share name and its configuration
							$arrDomainDirParts = explode('/', $domain_cfg['DOMAINDIR']);
							$strShareName = $arrDomainDirParts[3];
							if (!empty($strShareName) && is_file('/boot/config/shares/'.$strShareName.'.cfg')) {
								$arrShareCfg = parse_ini_file('/boot/config/shares/'.$strShareName.'.cfg');
								if (!empty($arrShareCfg['shareInclude'])) {
									$strShareUserLocalInclude = $arrShareCfg['shareInclude'];
								}
								if (!empty($arrShareCfg['shareExclude'])) {
									$strShareUserLocalExclude = $arrShareCfg['shareExclude'];
								}
								if (!empty($arrShareCfg['shareUseCache'])) {
									$strShareUserLocalUseCache = $arrShareCfg['shareUseCache'];
								}
							}

							// Determine if cache drive is available:
							if (!empty($disks['cache']) && (!empty($disks['cache']['device']))) {
								if ($strShareUserLocalUseCache != 'no' && $var['shareCacheEnabled'] == 'yes') {
									$strLabel = my_disk('cache').' - '.my_scale($disks['cache']['fsFree']*1024, $strUnit).' '.$strUnit.' free';
									echo mk_option($default_option, 'cache', $strLabel);
								}
							}

							// Determine which disks from the array are available for this share:
							foreach ($disks as $name => $disk) {
								if ((strpos($name, 'disk') === 0) && (!empty($disk['device']))) {
									if ((!empty($strShareUserLocalInclude) && (strpos($strShareUserLocalInclude.',', $name.',') === false)) ||
										(!empty($strShareUserLocalExclude) && (strpos($strShareUserLocalExclude.',', $name.',') !== false)) ||
										(!empty($var['shareUserInclude']) && (strpos($var['shareUserInclude'].',', $name.',') === false)) ||
										(!empty($var['shareUserExclude']) && (strpos($var['shareUserExclude'].',', $name.',') !== false))) {
										// skip this disk based on local and global share settings
										continue;
									}
									$strLabel = my_disk($name).' - '.my_scale($disk['fsFree']*1024, $strUnit).' '.$strUnit.' free';
									echo mk_option($default_option, $name, $strLabel);
								}
							}
						}

					}

					echo mk_option($default_option, 'manual', 'Manual');
				?>
				</select><input type="text" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickroot="/mnt/" name="disk[<?=$i?>][new]" class="disk" id="disk_<?=$i?>" value="<?=$arrDisk['new']?>" placeholder="Separate sub-folder and image will be created based on Name"><div class="disk_preview"></div>
			</td>
		</tr>

		<tr class="disk_file_options">
			<td>vDisk Size:</td>
			<td>
				<input type="text" name="disk[<?=$i?>][size]" value="<?=$arrDisk['size']?>" class="narrow" placeholder="e.g. 10M, 1G, 10G...">
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

		<tr class="advanced disk_bus_options">
			<td>vDisk Bus:</td>
			<td>
				<select name="disk[<?=$i?>][bus]" class="disk_bus narrow">
				<?php mk_dropdown_options($arrValidBusTypes, $arrDisk['bus']); ?>
				</select>
			</td>
		</tr>
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

		<p class="advanced">
			<b>vDisk Bus</b><br>
			Select virtio for best performance.
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
				<select name="disk[{{INDEX}}][select]" class="disk_select narrow">
				<?
					if (!empty($domain_cfg['DOMAINDIR']) && file_exists($domain_cfg['DOMAINDIR'])) {

						$default_option = $domain_cfg['VMSTORAGEMODE'];

						echo mk_option($default_option, 'auto', 'Auto');

						if (strpos($domain_cfg['DOMAINDIR'], '/mnt/user/') === 0) {
							$strShareUserLocalInclude = '';
							$strShareUserLocalExclude = '';
							$strShareUserLocalUseCache = 'no';

							// Get the share name and its configuration
							$arrDomainDirParts = explode('/', $domain_cfg['DOMAINDIR']);
							$strShareName = $arrDomainDirParts[3];
							if (!empty($strShareName) && is_file('/boot/config/shares/'.$strShareName.'.cfg')) {
								$arrShareCfg = parse_ini_file('/boot/config/shares/'.$strShareName.'.cfg');
								if (!empty($arrShareCfg['shareInclude'])) {
									$strShareUserLocalInclude = $arrShareCfg['shareInclude'];
								}
								if (!empty($arrShareCfg['shareExclude'])) {
									$strShareUserLocalExclude = $arrShareCfg['shareExclude'];
								}
								if (!empty($arrShareCfg['shareUseCache'])) {
									$strShareUserLocalUseCache = $arrShareCfg['shareUseCache'];
								}
							}

							// Determine if cache drive is available:
							if (!empty($disks['cache']) && (!empty($disks['cache']['device']))) {
								if ($strShareUserLocalUseCache != 'no' && $var['shareCacheEnabled'] == 'yes') {
									$strLabel = my_disk('cache').' - '.my_scale($disks['cache']['fsFree']*1024, $strUnit).' '.$strUnit.' free';
									echo mk_option($default_option, 'cache', $strLabel);
								}
							}

							// Determine which disks from the array are available for this share:
							foreach ($disks as $name => $disk) {
								if ((strpos($name, 'disk') === 0) && (!empty($disk['device']))) {
									if ((!empty($strShareUserLocalInclude) && (strpos($strShareUserLocalInclude.',', $name.',') === false)) ||
										(!empty($strShareUserLocalExclude) && (strpos($strShareUserLocalExclude.',', $name.',') !== false)) ||
										(!empty($var['shareUserInclude']) && (strpos($var['shareUserInclude'].',', $name.',') === false)) ||
										(!empty($var['shareUserExclude']) && (strpos($var['shareUserExclude'].',', $name.',') !== false))) {
										// skip this disk based on local and global share settings
										continue;
									}
									$strLabel = my_disk($name).' - '.my_scale($disk['fsFree']*1024, $strUnit).' '.$strUnit.' free';
									echo mk_option($default_option, $name, $strLabel);
								}
							}
						}

					}

					echo mk_option('', 'manual', 'Manual');
				?>
				</select><input type="text" data-pickcloseonfile="true" data-pickfolders="true" data-pickfilter="img,qcow,qcow2" data-pickroot="/mnt/" name="disk[{{INDEX}}][new]" class="disk" id="disk_{{INDEX}}" value="" placeholder="Separate sub-folder and image will be created based on Name"><div class="disk_preview"></div>
			</td>
		</tr>

		<tr class="disk_file_options">
			<td>vDisk Size:</td>
			<td>
				<input type="text" name="disk[{{INDEX}}][size]" value="" class="narrow" placeholder="e.g. 10M, 1G, 10G...">
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

		<tr class="advanced disk_bus_options">
			<td>vDisk Bus:</td>
			<td>
				<select name="disk[{{INDEX}}][bus]" class="disk_bus narrow">
				<?php mk_dropdown_options($arrValidBusTypes, ''); ?>
				</select>
			</td>
		</tr>
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
						echo mk_option($arrGPU['id'], $arrDev['id'], $arrDev['name'].' ('.$arrDev['id'].')');
					}
				?>
				</select>
			</td>
		</tr>

		<? if ($i == 0) { ?>
		<tr class="advanced vncmodel">
			<td>VNC Video Driver:</td>
			<td>
				<select id="vncmodel" name="gpu[<?=$i?>][model]" class="narrow" title="video for VNC">
				<?php mk_dropdown_options($arrValidVNCModels, $arrGPU['model']); ?>
				</select>
			</td>
		</tr>
		<tr class="vncpassword">
			<td>VNC Password:</td>
			<td><input type="password" name="domain[password]" title="password for VNC" class="narrow" placeholder="Password for VNC (optional)" /></td>
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

		<p class="advanced vncmodel">
			<b>VNC Video Driver</b><br>
			If you wish to assign a different video driver to use for a VNC connection, specify one here.
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
			<select name="domain[usbmode]" id="usbmode" class="narrow" title="Select the USB Mode to emulate.  Some OSes won't support USB3 (e.g. Windows 7/XP)">
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
			Select the USB Mode to emulate.  Some OSes won't support USB3 (e.g. Windows 7).
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
<blockquote class="inline_help">
	<p>Click Create to generate the vDisks and return to the Virtual Machines page where your new VM will be created.</p>
</blockquote>

<script type="text/javascript">
$(function() {
	var regenerateDiskPreview = function (disk_index) {
		var domaindir = '<?=$domain_cfg['DOMAINDIR']?>' + $('#domain_oldname').val();
		var tl_args = arguments.length;

		$("#vmform .disk").closest('table').each(function (index) {
			var $table = $(this);

			if (tl_args && disk_index != $table.data('index')) {
				return;
			}

			var disk_select = $table.find(".disk_select option:selected").val();
			var $disk_file_sections = $table.find('.disk_file_options');
			var $disk_bus_sections = $table.find('.disk_bus_options');
			var $disk_input = $table.find('.disk');
			var $disk_preview = $table.find('.disk_preview');

			if (disk_select == 'manual') {

				// Manual disk
				$disk_preview.fadeOut('fast', function() {
					$disk_input.fadeIn('fast');
				});

				$disk_bus_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
				slideDownRows($disk_bus_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));

				$.getJSON("/plugins/dynamix.vm.manager/VMajax.php?action=file-info&file=" + encodeURIComponent($disk_input.val()), function( info ) {
					if (info.isfile || info.isblock) {
						slideUpRows($disk_file_sections);
						$disk_file_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');

						$disk_input.attr('name', $disk_input.attr('name').replace('new', 'image'));
					} else {
						$disk_file_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
						slideDownRows($disk_file_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));

						$disk_input.attr('name', $disk_input.attr('name').replace('image', 'new'));
					}
				});

			} else if (disk_select !== '') {

				// Auto disk
				var auto_disk_path = domaindir + '/vdisk' + (index+1) + '.img';
				$disk_preview.html(auto_disk_path);
				$disk_input.fadeOut('fast', function() {
					$disk_preview.fadeIn('fast');
				});

				$disk_bus_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
				slideDownRows($disk_bus_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));

				$.getJSON("/plugins/dynamix.vm.manager/VMajax.php?action=file-info&file=" + encodeURIComponent(auto_disk_path), function( info ) {
					if (info.isfile || info.isblock) {
						slideUpRows($disk_file_sections);
						$disk_file_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');

						$disk_input.attr('name', $disk_input.attr('name').replace('new', 'image'));
					} else {
						$disk_file_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
						slideDownRows($disk_file_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));

						$disk_input.attr('name', $disk_input.attr('name').replace('image', 'new'));
					}
				});

			} else {

				// No disk
				var $hide_el = $table.find('.disk_bus_options,.disk_file_options,.disk_preview,.disk');
				$disk_preview.html('');
				slideUpRows($hide_el);
				$hide_el.filter('.advanced').removeClass('advanced').addClass('wasadvanced');

			}
		});
	};

	<?if ($boolNew):?>
	$("#vmform #domain_name").on("input change", function changeNameEvent() {
		$('#vmform #domain_oldname').val($(this).val());
		regenerateDiskPreview();
	});
	<?endif?>

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

	$("#vmform #domain_machine").change(function changeMachineEvent() {
		// Cdrom Bus: select IDE for i440 and SATA for q35
		if ($(this).val().indexOf('i440fx') != -1) {
			$('#vmform .cdrom_bus').val('ide');
		} else {
			$('#vmform .cdrom_bus').val('sata');
		}
	});

	$("#vmform #domain_ovmf").change(function changeBIOSEvent() {
		// using OVMF - disable vmvga vnc option
		if ($(this).val() == '1' && $("#vmform #vncmodel").val() == 'vmvga') {
			$("#vmform #vncmodel").val('qxl');
		}
		$("#vmform #vncmodel option[value='vmvga']").prop('disabled', ($(this).val() == '1'));
	}).change(); // fire event now

	$("#vmform").on("spawn_section", function spawnSectionEvent(evt, section, sectiondata) {
		if (sectiondata.category == 'vDisk') {
			regenerateDiskPreview(sectiondata.index);
		}
	});

	$("#vmform").on("destroy_section", function destroySectionEvent(evt, section, sectiondata) {
		if (sectiondata.category == 'vDisk') {
			regenerateDiskPreview();
		}
	});

	$("#vmform").on("change", ".disk_select", function changeDiskSelectEvent() {
		regenerateDiskPreview($(this).closest('table').data('index'));
	});

	$("#vmform").on("input change", ".disk", function changeDiskEvent() {
		var $input = $(this);
		var config = $input.data();

		if (config.hasOwnProperty('pickfilter')) {
			regenerateDiskPreview($input.closest('table').data('index'));
		}
	});

	$("#vmform").on("change", ".gpu", function changeGPUEvent() {
		var myvalue = $(this).val();
		var mylabel = $(this).children('option:selected').text();

		$vnc_sections = $('.vncmodel,.vncpassword,.vnckeymap');
		if ($("#vmform .gpu option[value='vnc']:selected").length) {
			$vnc_sections.filter('.wasadvanced').removeClass('wasadvanced').addClass('advanced');
			slideDownRows($vnc_sections.not(isVMAdvancedMode() ? '.basic' : '.advanced'));
		} else {
			slideUpRows($vnc_sections);
			$vnc_sections.filter('.advanced').removeClass('advanced').addClass('wasadvanced');
		}

		$("#vmform .gpu").not(this).each(function () {
			if (myvalue == $(this).val()) {
				$(this).prop("selectedIndex", 0).change();
			}
		});
	});

	$("#vmform").on("click", ".mac_generate", function generateMac() {
		var $input = $(this).prev('input');

		$.getJSON("/plugins/dynamix.vm.manager/VMajax.php?action=generate-mac", function( data ) {
			if (data.mac) {
				$input.val(data.mac);
			}
		});
	});

	$("#vmform #btnSubmit").click(function frmSubmit() {
		var $button = $(this);
		var $form = $button.closest('form');

		//TODO: form validation

		$("#vmform .disk_select option:selected").not("[value='manual']").closest('table').each(function () {
			var v = $(this).find('.disk_preview').html();
			$(this).find('.disk').val(v);
		});

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
				$("#vmform .domain_vcpu").change(); // restore the cpu checkbox disabled states
				<? if (!empty($arrConfig['domain']['state'])) echo '$(\'#vmform #domain_ovmf\').prop(\'disabled\', true); // restore bios disabled state' . "\n"; ?>
				$button.val($button.attr('readyvalue'));
			}
		}, "json");
	});

	// Fire events below once upon showing page
	var os = $("#vmform #template_os").val() || 'linux';
	var os_casted = (os.indexOf('windows') == -1 ? 'other' : 'windows');

	$('#vmform .domain_os').not($('.' + os_casted)).hide();
	$('#vmform .domain_os.' + os_casted).not(isVMAdvancedMode() ? '.basic' : '.advanced').show();

	<?if ($boolNew):?>
	if (os_casted == 'windows') {
		$('#vmform #domain_clock').val('localtime');
		$("#vmform #domain_machine option").each(function(){
			if ($(this).val().indexOf('i440fx') != -1) {
				$('#vmform #domain_machine').val($(this).val()).change();
				return false;
			}
		});
	} else {
		$('#vmform #domain_clock').val('utc');
		$("#vmform #domain_machine option").each(function(){
			if ($(this).val().indexOf('q35') != -1) {
				$('#vmform #domain_machine').val($(this).val()).change();
				return false;
			}
		});
	}
	<?endif?>

	// disable usb3 option for windows7 / xp / server 2003 / server 2008
	var noUSB3 = (os == 'windows7' || os == 'windows2008' || os == 'windowsxp' || os == 'windows2003');
	if (noUSB3 && $("#vmform #usbmode").val() == 'usb3') {
		$("#vmform #usbmode").val('usb2');
	}
	$("#vmform #usbmode option[value='usb3']").prop('disabled', noUSB3);

	if ($("#vmform .gpu option[value='vnc']:selected").length) {
		$('.vncmodel,.vncpassword,.vnckeymap').not(isVMAdvancedMode() ? '.basic' : '.advanced').show();
	} else {
		$('.vncmodel,.vncpassword,.vnckeymap').hide();
	}

	regenerateDiskPreview();
});
</script>
