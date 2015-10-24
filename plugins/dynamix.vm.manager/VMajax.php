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

// Make sure libvirt is connected to qemu
if (!isset($lv) || !$lv->enabled()) {
	header('Content-Type: application/json');
	die(json_encode(['error' => 'failed to connect to the hypervisor']));
}

if ($uuid) {
	$domName = $lv->domain_get_name_by_uuid($uuid);
	if (!$domName) {
		header('Content-Type: application/json');
		die(json_encode(['error' => $lv->get_last_error()]));
	}
}

$arrResponse = [];


switch ($action) {

	case 'domain-autostart':
		$arrResponse = $lv->domain_set_autostart($domName, ($_REQUEST['autostart'] != "false")) ?
						['success' => true, 'autostart' => (bool)$lv->domain_get_autostart($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-start':
		$arrResponse = $lv->domain_start($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-pause':
		$arrResponse = $lv->domain_suspend($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-resume':
		$arrResponse = $lv->domain_resume($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-pmwakeup':
		// No support in libvirt-php to do a dompmwakeup, use virsh tool instead
		exec("virsh dompmwakeup " . escapeshellarg($uuid) . " 2>&1", $arrOutput, $intReturnCode);
		$arrResponse = ($intReturnCode == 0) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => str_replace('error: ', '', implode('. ', $arrOutput))];
		break;

	case 'domain-restart':
		$arrResponse = $lv->domain_reboot($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-save':
		$arrResponse = $lv->domain_save($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-stop':
		$arrResponse = $lv->domain_shutdown($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-destroy':
		$arrResponse = $lv->domain_destroy($domName) ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-delete':
		$arrResponse = $lv->domain_delete($domName) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-undefine':
		$arrResponse = $lv->domain_undefine($domName) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-define':
		$domName = $lv->domain_define($_REQUEST['xml']);
		$arrResponse =  $domName ?
						['success' => true, 'state' => $lv->domain_get_state($domName)] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-state':
		$state = $lv->domain_get_state($domName);
		$arrResponse = ($state) ?
						['success' => true, 'state' => $state] :
						['error' => $lv->get_last_error()];
		break;

	case 'domain-diskdev':
		$arrResponse = ($lv->domain_set_disk_dev($domName, $_REQUEST['olddev'], $_REQUEST['diskdev'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'cdrom-change':
		$arrResponse = ($lv->domain_change_cdrom($domName, $_REQUEST['cdrom'], $_REQUEST['dev'], $_REQUEST['bus'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'memory-change':
		$arrResponse = ($lv->domain_set_memory($domName, $_REQUEST['memory']*1024)) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'vcpu-change':
		$arrResponse = ($lv->domain_set_vcpu($domName, $_REQUEST['vcpu'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'bootdev-change':
		$arrResponse = ($lv->domain_set_boot_device($domName, $_REQUEST['bootdev'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'disk-remove':
		$arrResponse = ($lv->domain_disk_remove($domName, $_REQUEST['dev'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-create':
		$arrResponse = ($lv->domain_snapshot_create($domName)) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-delete':
		$arrResponse = ($lv->domain_snapshot_delete($domName, $_REQUEST['snap'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-revert':
		$arrResponse = ($lv->domain_snapshot_revert($domName, $_REQUEST['snap'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'snap-desc':
		$arrResponse = ($lv->snapshot_set_metadata($domName, $_REQUEST['snap'], $_REQUEST['snapdesc'])) ?
						['success' => true] :
						['error' => $lv->get_last_error()];
		break;

	case 'disk-create':
		$disk = $_REQUEST['disk'];
		$driver = $_REQUEST['driver'];
		$size = str_replace(array("KB","MB","GB","TB","PB", " ", ","), array("K","M","G","T","P", "", ""), strtoupper($_REQUEST['size']));

		$dir = dirname($disk);

		if (!is_dir($dir))
			mkdir($dir);

		// determine the actual disk if user share is being used
		if (strpos($dir, '/mnt/user/') === 0) {
			$tmp = parse_ini_string(shell_exec("getfattr -n user.LOCATION " . escapeshellarg($dir) . " | grep user.LOCATION"));
			$dir = str_replace('/mnt/user', '/mnt/' . $tmp['user.LOCATION'], $dir);  // replace 'user' with say 'cache' or 'disk1' etc
		}

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
		$capacity = str_replace(array("KB","MB","GB","TB","PB", " ", ","), array("K","M","G","T","P", "", ""), strtoupper($_REQUEST['cap']));
		$old_capacity = str_replace(array("KB","MB","GB","TB","PB", " ", ","), array("K","M","G","T","P", "", ""), strtoupper($_REQUEST['oldcap']));

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
			$json_info = json_decode(shell_exec("qemu-img info --output json " . escapeshellarg($file)), true);
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
		$arrResponse = [
			'mac' => $lv->generate_random_mac_addr()
		];
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


	default:
		$arrResponse = ['error' => 'Unknown action \'' . $action . '\''];
		break;

}

header('Content-Type: application/json');
die(json_encode($arrResponse));

