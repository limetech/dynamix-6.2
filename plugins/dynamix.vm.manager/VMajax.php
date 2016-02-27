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

function requireLibvirt() {
	global $lv;

	// Make sure libvirt is connected to qemu
	if (!isset($lv) || !$lv->enabled()) {
		header('Content-Type: application/json');
		die(json_encode(['error' => 'failed to connect to the hypervisor']));
	}
}

$arrSizePrefix = [
	0 => '',
	1 => 'K',
	2 => 'M',
	3 => 'G',
	4 => 'T',
	5 => 'P'
];

$_REQUEST = array_merge($_GET, $_POST);

$action = array_key_exists('action', $_REQUEST) ? $_REQUEST['action'] : '';
$uuid = array_key_exists('uuid', $_REQUEST) ? $_REQUEST['uuid'] : '';

if ($uuid) {
	requireLibvirt();
	$domName = $lv->domain_get_name_by_uuid($uuid);
	if (!$domName) {
		header('Content-Type: application/json');
		die(json_encode(['error' => $lv->get_last_error()]));
	}
}

$arrResponse = [];


switch ($action) {

	case 'domain-autostart':
		requireLibvirt();
		$arrResponse = $lv->domain_set_autostart($domName, ($_REQUEST['autostart'] != "false")) ?
						['success' => true, 'autostart' => (bool)$lv->domain_get_autostart($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-start':
		requireLibvirt();
		$arrResponse = $lv->domain_start($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-pause':
		requireLibvirt();
		$arrResponse = $lv->domain_suspend($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-resume':
		requireLibvirt();
		$arrResponse = $lv->domain_resume($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-pmwakeup':
		requireLibvirt();
		// No support in libvirt-php to do a dompmwakeup, use virsh tool instead
		exec("virsh dompmwakeup " . escapeshellarg($uuid) . " 2>&1", $arrOutput, $intReturnCode);
		$arrResponse = ($intReturnCode == 0) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => str_replace('error: ', '', implode('. ', $arrOutput))];
		break;

	case 'domain-restart':
		requireLibvirt();
		$arrResponse = $lv->domain_reboot($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-save':
		requireLibvirt();
		$arrResponse = $lv->domain_save($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-stop':
		requireLibvirt();
		$arrResponse = $lv->domain_shutdown($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-destroy':
		requireLibvirt();
		$arrResponse = $lv->domain_destroy($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-delete':
		requireLibvirt();
		$arrResponse = $lv->domain_delete($domName) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-undefine':
		requireLibvirt();
		$arrResponse = $lv->domain_undefine($domName) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-define':
		requireLibvirt();
		$domName = $lv->domain_define($_REQUEST['xml']);
		$arrResponse =  $domName ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-state':
		requireLibvirt();
		$state = $lv->domain_get_state($domName);
		$arrResponse = ($state) ?
						['success' => true, 'state' => $state] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-diskdev':
		requireLibvirt();
		$arrResponse = ($lv->domain_set_disk_dev($domName, $_REQUEST['olddev'], $_REQUEST['diskdev'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'cdrom-change':
		requireLibvirt();
		$arrResponse = ($lv->domain_change_cdrom($domName, $_REQUEST['cdrom'], $_REQUEST['dev'], $_REQUEST['bus'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'memory-change':
		requireLibvirt();
		$arrResponse = ($lv->domain_set_memory($domName, $_REQUEST['memory']*1024)) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'vcpu-change':
		requireLibvirt();
		$arrResponse = ($lv->domain_set_vcpu($domName, $_REQUEST['vcpu'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'bootdev-change':
		requireLibvirt();
		$arrResponse = ($lv->domain_set_boot_device($domName, $_REQUEST['bootdev'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'disk-remove':
		requireLibvirt();
		$arrResponse = ($lv->domain_disk_remove($domName, $_REQUEST['dev'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-create':
		requireLibvirt();
		$arrResponse = ($lv->domain_snapshot_create($domName)) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-delete':
		requireLibvirt();
		$arrResponse = ($lv->domain_snapshot_delete($domName, $_REQUEST['snap'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-revert':
		requireLibvirt();
		$arrResponse = ($lv->domain_snapshot_revert($domName, $_REQUEST['snap'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-desc':
		requireLibvirt();
		$arrResponse = ($lv->snapshot_set_metadata($domName, $_REQUEST['snap'], $_REQUEST['snapdesc'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'disk-create':
		$disk = $_REQUEST['disk'];
		$driver = $_REQUEST['driver'];
		$size = str_replace(["KB","MB","GB","TB","PB", " ", ","], ["K","M","G","T","P", "", ""], strtoupper($_REQUEST['size']));

		$dir = dirname($disk);

		if (!is_dir($dir))
			mkdir($dir);

		// determine the actual disk if user share is being used
		$dir = transpose_user_path($dir);

		@exec("chattr +C -R " . escapeshellarg($dir) . " >/dev/null");

		$strLastLine = exec("qemu-img create -q -f " . escapeshellarg($driver) . " " . escapeshellarg($disk) . " " . escapeshellarg($size) . " 2>&1", $out, $status);

		if (empty($status)) {
			$arrResponse = ['success' => true];
		} else {
			$arrResponse = ['error' => $strLastLine];
		}

		break;

	case 'disk-resize':
		$disk = $_REQUEST['disk'];
		$capacity = str_replace(["KB","MB","GB","TB","PB", " ", ","], ["K","M","G","T","P", "", ""], strtoupper($_REQUEST['cap']));
		$old_capacity = str_replace(["KB","MB","GB","TB","PB", " ", ","], ["K","M","G","T","P", "", ""], strtoupper($_REQUEST['oldcap']));

		if (substr($old_capacity,0,-1) < substr($capacity,0,-1)){
			$strLastLine = exec("qemu-img resize -q " . escapeshellarg($disk) . " " . escapeshellarg($capacity) . " 2>&1", $out, $status);
			if (empty($status)) {
				$arrResponse = ['success' => true];
			} else {
				$arrResponse = ['error' => $strLastLine];
			}
		} else {
			$arrResponse = ['error' => "Disk capacity has to be greater than " . $old_capacity];
		}
		break;

	case 'file-info':
		$file = $_REQUEST['file'];

		$arrResponse = [
			'isfile' => (!empty($file) ? is_file($file) : false),
			'isdir' => (!empty($file) ? is_dir($file) : false),
			'isblock' => (!empty($file) ? is_block($file) : false),
			'resizable' => false
		];

		// if file, get size and format info
		if (is_file($file)) {
			$json_info = getDiskImageInfo($file);
			if (!empty($json_info)) {
				$intDisplaySize = (int)$json_info['virtual-size'];
				$intShifts = 0;
				while (!empty($intDisplaySize) &&
						(floor($intDisplaySize) == $intDisplaySize) &&
						isset($arrSizePrefix[$intShifts])) {

					$arrResponse['display-size'] = $intDisplaySize . $arrSizePrefix[$intShifts];

					$intDisplaySize /= 1024;
					$intShifts++;
				}

				$arrResponse['virtual-size'] = $json_info['virtual-size'];
				$arrResponse['actual-size'] = $json_info['actual-size'];
				$arrResponse['format'] = $json_info['format'];
				$arrResponse['dirty-flag'] = $json_info['dirty-flag'];
				$arrResponse['resizable'] = true;
			}
		} else if (is_block($file)) {
			$strDevSize = trim(shell_exec("blockdev --getsize64 " . escapeshellarg($file)));
			if (!empty($strDevSize) && is_numeric($strDevSize)) {
				$arrResponse['actual-size'] = (int)$strDevSize;
				$arrResponse['format'] = 'raw';

				$intDisplaySize = (int)$strDevSize;
				$intShifts = 0;
				while (!empty($intDisplaySize) &&
						($intDisplaySize >= 2) &&
						isset($arrSizePrefix[$intShifts])) {

					$arrResponse['display-size'] = round($intDisplaySize, 0) . $arrSizePrefix[$intShifts];

					$intDisplaySize /= 1000; // 1000 looks better than 1024 for block devs
					$intShifts++;
				}
			}
		}
		break;

	case 'generate-mac':
		requireLibvirt();
		$arrResponse = [
			'mac' => $lv->generate_random_mac_addr()
		];
		break;

	case 'get-vm-icons':
		$arrImages = [];
		foreach (glob("/usr/local/emhttp/plugins/dynamix.vm.manager/templates/images/*.png") as $png_file) {
			$arrImages[] = [
				'custom' => false,
				'basename' => basename($png_file),
				'url' => '/plugins/dynamix.vm.manager/templates/images/' . basename($png_file)
			];
		}
		$arrResponse = $arrImages;
		break;

	case 'get-usb-devices':
		$arrValidUSBDevices = getValidUSBDevices();
		$arrResponse = $arrValidUSBDevices;
		break;


	case 'hot-attach-usb':
		//TODO - If usb is a block device, then attach as a <disk type="usb"> otherwise <hostdev type="usb">
		/*
			<hostdev mode='subsystem' type='usb'>
				<source startupPolicy='optional'>
					<vendor id='0x1234'/>
					<product id='0xbeef'/>
				</source>
			</hostdev>

			<disk type='block' device='disk'>
				<driver name='qemu' type='raw'/>
				<source dev='/dev/sda'/>
				<target dev='hdX' bus='virtio'/>
			</disk>
		*/

		break;

	case 'hot-detach-usb':
		//TODO
		break;

	case 'acs-override-enable':
		// Check the /boot/syslinux/syslinux.cfg for the existance of pcie_acs_override=downstream, add it in if not found
		$arrSyslinuxCfg = file('/boot/syslinux/syslinux.cfg');
		$strCurrentLabel = '';
		$boolModded = false;
		foreach ($arrSyslinuxCfg as &$strSyslinuxCfg) {
			if (stripos(trim($strSyslinuxCfg), 'label ') === 0) {
				$strCurrentLabel = trim(str_ireplace('label ', '', $strSyslinuxCfg));
			}
			if (stripos($strSyslinuxCfg, 'append ') !== false) {
				if (stripos($strSyslinuxCfg, 'pcie_acs_override=') === false) {
					// pcie_acs_override=downstream was not found so append it in
					$strSyslinuxCfg = str_ireplace('append ', 'append pcie_acs_override=downstream ', $strSyslinuxCfg);
					$boolModded = true;
				}

				// We just modify the first append line, other boot menu items are untouched
				break;
			}
		}

		if ($boolModded) {
			// Backup syslinux.cfg
			copy('/boot/syslinux/syslinux.cfg', '/boot/syslinux/syslinux.cfg-');

			// Write Changes to syslinux.cfg
			file_put_contents('/boot/syslinux/syslinux.cfg', implode('', $arrSyslinuxCfg));
		}

		$arrResponse = ['success' => true, 'label' => $strCurrentLabel];
		break;

	case 'acs-override-disable':
		// Check the /boot/syslinux/syslinux.cfg for the existance of pcie_acs_override=, remove it if found
		$arrSyslinuxCfg = file('/boot/syslinux/syslinux.cfg');
		$strCurrentLabel = '';
		$boolModded = false;
		foreach ($arrSyslinuxCfg as &$strSyslinuxCfg) {
			if (stripos(trim($strSyslinuxCfg), 'label ') === 0) {
				$strCurrentLabel = trim(str_ireplace('label ', '', $strSyslinuxCfg));
			}
			if (stripos($strSyslinuxCfg, 'append ') !== false) {
				if (stripos($strSyslinuxCfg, 'pcie_acs_override=') !== false) {
					// pcie_acs_override= was found so remove the two variations
					$strSyslinuxCfg = str_ireplace('pcie_acs_override=downstream ', '', $strSyslinuxCfg);
					$strSyslinuxCfg = str_ireplace('pcie_acs_override=multifunction ', '', $strSyslinuxCfg);
					$boolModded = true;
				}

				// We just modify the first append line, other boot menu items are untouched
				break;
			}
		}

		if ($boolModded) {
			// Backup syslinux.cfg
			copy('/boot/syslinux/syslinux.cfg', '/boot/syslinux/syslinux.cfg-');

			// Write Changes to syslinux.cfg
			file_put_contents('/boot/syslinux/syslinux.cfg', implode('', $arrSyslinuxCfg));
		}

		$arrResponse = ['success' => true, 'label' => $strCurrentLabel];
		break;

	case 'virtio-win-iso-info':
		$path = $_REQUEST['path'];
		$file = $_REQUEST['file'];

		if (empty($file)) {
			$arrResponse = ['exists' => false];
			break;
		}

		if (is_file($file)) {
			$arrResponse = ['exists' => true, 'path' => $file];
			break;
		}

		if (empty($path) || !is_dir($path)) {
			$path = '/mnt/user/isos/';
		} else {
			$path = str_replace('//', '/', $path.'/');
		}
		$file = $path.$file;

		if (is_file($file)) {
			$arrResponse = ['exists' => true, 'path' => $file];
			break;
		}

		$arrResponse = ['exists' => false];
		break;

	case 'virtio-win-iso-download':
		$arrDownloadVirtIO = [];
		$strKeyName = basename($_POST['download_version'], '.iso');
		if (array_key_exists($strKeyName, $virtio_isos)) {
			$arrDownloadVirtIO = $virtio_isos[$strKeyName];
		}

		if (empty($arrDownloadVirtIO)) {
			$arrResponse = ['error' => 'Unknown version: ' . $_POST['download_version']];
		} else if (empty($_POST['download_path'])) {
			$arrResponse = ['error' => 'Specify a ISO storage path first'];
		//} else if (!is_dir($_POST['download_path'])) {
		//	$arrResponse = ['error' => 'ISO storage path doesn\'t exist, please create the user share (or empty folder) first'];
		} else {
			@mkdir($_POST['download_path'], 0777, true);
			$_POST['download_path'] = realpath($_POST['download_path']) . '/';

			$boolCheckOnly = !empty($_POST['checkonly']);

			$strInstallScript = '/tmp/VirtIOWin_' . $strKeyName . '_install.sh';
			$strInstallScriptPgrep = '-f "VirtIOWin_' . $strKeyName . '_install.sh"';
			$strTargetFile = $_POST['download_path'] . $arrDownloadVirtIO['name'];
			$strLogFile = $strTargetFile . '.log';
			$strMD5File = $strTargetFile . '.md5';
			$strMD5StatusFile = $strTargetFile . '.md5status';

			// Save to /boot/config/domain.conf
			$domain_cfg['MEDIADIR'] = $_POST['download_path'];
			$domain_cfg['VIRTIOISO'] = $strTargetFile;
			$tmp = '';
			foreach ($domain_cfg as $key => $value) $tmp .= "$key=\"$value\"\n";
			file_put_contents($domain_cfgfile, $tmp);

			$strDownloadCmd = 'wget -nv -c -O ' . escapeshellarg($strTargetFile) . ' ' . escapeshellarg($arrDownloadVirtIO['url']);
			$strDownloadPgrep = '-f "wget.*' . $strTargetFile . '.*' . $arrDownloadVirtIO['url'] . '"';

			$strVerifyCmd = 'md5sum -c ' . escapeshellarg($strMD5File);
			$strVerifyPgrep = '-f "md5sum.*' . $strMD5File . '"';

			$strCleanCmd = '(chmod 777 ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strTargetFile) . '; chown nobody:users ' . escapeshellarg($_POST['download_path']) . ' ' . escapeshellarg($strTargetFile) . '; rm ' . escapeshellarg($strMD5File) . ' ' . escapeshellarg($strMD5StatusFile) . ')';
			$strCleanPgrep = '-f "chmod.*chown.*rm.*' . $strMD5StatusFile . '"';

			$strAllCmd = "#!/bin/bash\n\n";
			$strAllCmd .= $strDownloadCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'echo "' . $arrDownloadVirtIO['md5'] . '  ' . $strTargetFile . '" > ' . escapeshellarg($strMD5File) . ' && ';
			$strAllCmd .= $strVerifyCmd . ' >' . escapeshellarg($strMD5StatusFile) . ' 2>/dev/null && ';
			$strAllCmd .= $strCleanCmd . ' >>' . escapeshellarg($strLogFile) . ' 2>&1 && ';
			$strAllCmd .= 'rm ' . escapeshellarg($strLogFile) . ' && ';
			$strAllCmd .= 'rm ' . escapeshellarg($strInstallScript);

			$arrResponse = [];

			if (file_exists($strTargetFile)) {

				if (!file_exists($strLogFile)) {

					if (!pgrep($strDownloadPgrep)) {

						// Status = done
						$arrResponse['status'] = 'Done';
						$arrResponse['localpath'] = $strTargetFile;
						$arrResponse['localfolder'] = dirname($strTargetFile);

					} else {

						// Status = cleanup
						$arrResponse['status'] = 'Cleanup ... ';

					}

				} else {

					if (pgrep($strDownloadPgrep)) {

						// Get Download percent completed
						$intSize = filesize($strTargetFile);
						$strPercent = 0;
						if ($intSize > 0) {
							$strPercent = round(($intSize / $arrDownloadVirtIO['size']) * 100);
						}

						$arrResponse['status'] = 'Downloading ... ' . $strPercent . '%';

					} else if (pgrep($strVerifyPgrep)) {

						// Status = running md5 check
						$arrResponse['status'] = 'Verifying ... ';

					} else if (file_exists($strMD5StatusFile)) {

						// Status = running extract
						$arrResponse['status'] = 'Cleanup ... ';

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
		break;


	case 'virtio-win-iso-cancel':
		$arrDownloadVirtIO = [];
		$strKeyName = basename($_POST['download_version'], '.iso');
		if (array_key_exists($strKeyName, $virtio_isos)) {
			$arrDownloadVirtIO = $virtio_isos[$strKeyName];
		}

		if (empty($arrDownloadVirtIO)) {
			$arrResponse = ['error' => 'Unknown version: ' . $_POST['download_version']];
		} else if (empty($_POST['download_path'])) {
			$arrResponse = ['error' => 'ISO storage path was empty'];
		} else if (!is_dir($_POST['download_path'])) {
			$arrResponse = ['error' => 'ISO storage path doesn\'t exist'];
		} else {
			$strInstallScriptPgrep = '-f "VirtIOWin_' . $strKeyName . '_install.sh"';
			$pid = pgrep($strInstallScriptPgrep);
			if (!$pid) {
				$arrResponse = ['error' => 'Not running'];
			} else {
				if (!posix_kill($pid, SIGTERM)) {
					$arrResponse = ['error' => 'Wasn\'t able to stop the process'];
				} else {
					$strTargetFile = $_POST['download_path'] . $arrDownloadVirtIO['name'];
					$strLogFile = $strTargetFile . '.log';
					$strMD5File = $strTargetFile . '.md5';
					$strMD5StatusFile = $strTargetFile . '.md5status';
					@unlink($strTargetFile);
					@unlink($strMD5File);
					@unlink($strMD5StatusFile);
					@unlink($strLogFile);
					$arrResponse['status'] = 'Done';
				}
			}
		}
		break;


	default:
		$arrResponse = ['error' => 'Unknown action \'' . $action . '\''];
		break;

}

header('Content-Type: application/json');
die(json_encode($arrResponse));

