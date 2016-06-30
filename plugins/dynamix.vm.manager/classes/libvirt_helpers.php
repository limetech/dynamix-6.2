<?PHP
/* Copyright 2005-2016, Lime Technology
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

	// Load emhttp variables if needed.
	if (! isset($var)){
		if (! is_file("/usr/local/emhttp/state/var.ini")) shell_exec("wget -qO /dev/null localhost:$(lsof -nPc emhttp | grep -Po 'TCP[^\d]*\K\d+')");
		$var = @parse_ini_file("/usr/local/emhttp/state/var.ini");
		$disks = @parse_ini_file("/usr/local/emhttp/state/disks.ini", true);
		extract(parse_plugin_cfg("dynamix",true));
	}
	if (!isset($eth0) && is_file("/usr/local/emhttp/state/network.ini")) {
		extract(parse_ini_file('/usr/local/emhttp/state/network.ini',true));
	}

	// Check if program is running and
	$libvirt_running = trim(shell_exec( "[ -f /proc/`cat /var/run/libvirt/libvirtd.pid 2> /dev/null`/exe ] && echo 'yes' || echo 'no' 2> /dev/null" ));


	$arrAllTemplates = [
		' Windows ' => '', /* Windows Header */

		'Windows 10' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows10',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows 8.x' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows',
			'overrides' => [
				'domain' => [
					'name' => 'Windows 8.1',
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows 7' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows7.png',
			'os' => 'windows7',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024,
					'ovmf' => 0,
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows XP' => [
			'form' => 'Custom.form.php',
			'icon' => 'windowsxp.png',
			'os' => 'windowsxp',
			'overrides' => [
				'domain' => [
					'ovmf' => 0,
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '15G'
					]
				]
			]
		],

		'Windows Server 2016' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows2016',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows Server 2012' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows.png',
			'os' => 'windows2012',
			'overrides' => [
				'domain' => [
					'mem' => 2048 * 1024,
					'maxmem' => 2048 * 1024
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows Server 2008' => [
			'form' => 'Custom.form.php',
			'icon' => 'windows7.png',
			'os' => 'windows2008',
			'overrides' => [
				'domain' => [
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '30G'
					]
				]
			]
		],

		'Windows Server 2003' => [
			'form' => 'Custom.form.php',
			'icon' => 'windowsxp.png',
			'os' => 'windows2003',
			'overrides' => [
				'domain' => [
					'usbmode' => 'usb2'
				],
				'disk' => [
					[
						'size' => '15G'
					]
				]
			]
		],


		' Pre-packaged ' => '', /* Pre-built Header */

		'LibreELEC' => [
			'form' => 'LibreELEC.form.php',
			'icon' => 'libreelec.png'
		],

		'OpenELEC' => [
			'form' => 'OpenELEC.form.php',
			'icon' => 'openelec.png'
		],


		' Linux ' => '', /* Linux Header */

		'Linux' => [
			'form' => 'Custom.form.php',
			'icon' => 'linux.png',
			'os' => 'linux'
		],
		'Arch' => [
			'form' => 'Custom.form.php',
			'icon' => 'arch.png',
			'os' => 'arch'
		],
		'CentOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'centos.png',
			'os' => 'centos'
		],
		'ChromeOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'chromeos.png',
			'os' => 'chromeos'
		],
		'CoreOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'coreos.png',
			'os' => 'coreos'
		],
		'Debian' => [
			'form' => 'Custom.form.php',
			'icon' => 'debian.png',
			'os' => 'debian'
		],
		'Fedora' => [
			'form' => 'Custom.form.php',
			'icon' => 'fedora.png',
			'os' => 'fedora'
		],
		'FreeBSD' => [
			'form' => 'Custom.form.php',
			'icon' => 'freebsd.png',
			'os' => 'freebsd'
		],
		'OpenSUSE' => [
			'form' => 'Custom.form.php',
			'icon' => 'opensuse.png',
			'os' => 'opensuse'
		],
		'RedHat' => [
			'form' => 'Custom.form.php',
			'icon' => 'redhat.png',
			'os' => 'redhat'
		],
		'Scientific' => [
			'form' => 'Custom.form.php',
			'icon' => 'scientific.png',
			'os' => 'scientific'
		],
		'Slackware' => [
			'form' => 'Custom.form.php',
			'icon' => 'slackware.png',
			'os' => 'slackware'
		],
		'SteamOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'steamos.png',
			'os' => 'steamos'
		],
		'Ubuntu' => [
			'form' => 'Custom.form.php',
			'icon' => 'ubuntu.png',
			'os' => 'ubuntu'
		],
		'VyOS' => [
			'form' => 'Custom.form.php',
			'icon' => 'vyos.png',
			'os' => 'vyos'
		],


		' ' => '', /* Custom / XML Expert Header */

		'Custom' => [
			'form' => 'XML_Expert.form.php',
			'icon' => 'default.png'
		]
	];


	$arrOpenELECVersions = [
		'6.0.3_1' => [
			'name' => '6.0.3',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-6.0.3_1.tar.xz',
			'size' => 178909136,
			'md5' => 'c584312831d7cd93a40e61ac9f186d32',
			'localpath' => '',
			'valid' => '0'
		],
		'6.0.0_1' => [
			'name' => '6.0.0',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-6.0.0_1.tar.xz',
			'size' => 165658636,
			'md5' => '66fb6c3f1b6db49c291753fb3ec7c15c',
			'localpath' => '',
			'valid' => '0'
		],
		'5.95.3_1' => [
			'name' => '5.95.3 (6.0.0 Beta3)',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-5.95.3_1.tar.xz',
			'size' => 153990180,
			'md5' => '8936cda74c28ddcaa165cc49ff2a477a',
			'localpath' => '',
			'valid' => '0'
		],
		'5.95.2_1' => [
			'name' => '5.95.2 (6.0.0 Beta2)',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/OpenELEC/OpenELEC-unRAID.x86_64-5.95.2_1.tar.xz',
			'size' => 156250392,
			'md5' => 'ac70048eecbda4772e386c6f271cb5e9',
			'localpath' => '',
			'valid' => '0'
		]
	];


	$arrLibreELECVersions = [
		'7.0.1_1' => [
			'name' => '7.0.1',
			'url' => 'https://s3.amazonaws.com/dnld.lime-technology.com/images/LibreELEC/LibreELEC-unRAID.x86_64-7.0.1_1.tar.xz',
			'size' => 209748564,
			'md5' => 'c1e8def2ffb26a355e7cc598311697f6',
			'localpath' => '',
			'valid' => '0'
		]
	];


	$virtio_isos = [
		'virtio-win-0.1.118-2' => [
			'name' => 'virtio-win-0.1.118-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.118-2/virtio-win-0.1.118.iso',
			'size' => 56967168,
			'md5' => '9cb51bde60decfafdf8119ce01b7c1cf'
		],
		'virtio-win-0.1.118-1' => [
			'name' => 'virtio-win-0.1.118-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.118-1/virtio-win-0.1.118.iso',
			'size' => 56967168,
			'md5' => 'cc5771f2f0ea5097946d3d447f21cce8'
		],
		'virtio-win-0.1.117-1' => [
			'name' => 'virtio-win-0.1.117-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.117-1/virtio-win-0.1.117.iso',
			'size' => 56999936,
			'md5' => '2a79d6036ea4292f81c3370dd0a8b6d6'
		],
		'virtio-win-0.1.113-1' => [
			'name' => 'virtio-win-0.1.113-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.113-1/virtio-win-0.1.113.iso',
			'size' => 56936448,
			'md5' => '11ed773055e19eca75ed186ff12d354c'
		],
		'virtio-win-0.1.112-1' => [
			'name' => 'virtio-win-0.1.112-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.112-1/virtio-win-0.1.112.iso',
			'size' => 56926208,
			'md5' => '7db0211d7aec3e08fadd21c8eaaf35db'
		],
		'virtio-win-0.1.110-2' => [
			'name' => 'virtio-win-0.1.110-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.110-2/virtio-win-0.1.110.iso',
			'size' => 56586240,
			'md5' => '93357a5105f1255591f1c389748288a9'
		],
		'virtio-win-0.1.110-1' => [
			'name' => 'virtio-win-0.1.110-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.110-1/virtio-win-0.1.110.iso',
			'size' => 56586240,
			'md5' => '239e0eb442bb63c177deb4af39397731'
		],
		'virtio-win-0.1.109-2' => [
			'name' => 'virtio-win-0.1.109-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.109-2/virtio-win-0.1.109.iso',
			'size' => 56606720,
			'md5' => '2a9f78f648f03fe72decdadb38837db3'
		],
		'virtio-win-0.1.109-1' => [
			'name' => 'virtio-win-0.1.109-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.109-1/virtio-win-0.1.109.iso',
			'size' => 56606720,
			'md5' => '1b0da008d0ec79a6223d21be2fcce2ee'
		],
		'virtio-win-0.1.108-1' => [
			'name' => 'virtio-win-0.1.108-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.108-1/virtio-win-0.1.108.iso',
			'size' => 56598528,
			'md5' => '46deb991f8c382f2d9af0fb786792990'
		],
		'virtio-win-0.1.106-1' => [
			'name' => 'virtio-win-0.1.106-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.106-1/virtio-win-0.1.106.iso',
			'size' => 56586240,
			'md5' => '66228ea20fae1a28d7a1583b9a5a1b8b'
		],
		'virtio-win-0.1.105-1' => [
			'name' => 'virtio-win-0.1.105-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.105-1/virtio-win-0.1.105.iso',
			'size' => 56584192,
			'md5' => 'c3194fa62a4a1ccbecfe784a52feda66'
		],
		'virtio-win-0.1.104-1' => [
			'name' => 'virtio-win-0.1.104-1.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.104-1/virtio-win-0.1.104.iso',
			'size' => 56584192,
			'md5' => '9aa28b6f5b18770d796194aaaeeea31a'
		],
		'virtio-win-0.1.103-2' => [
			'name' => 'virtio-win-0.1.103-2.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.103-2/virtio-win-0.1.103.iso',
			'size' => 56340480,
			'md5' => '07c4356880f0b385d6908392e48d6e75'
		],
		'virtio-win-0.1.103' => [
			'name' => 'virtio-win-0.1.103.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.103/virtio-win-0.1.103.iso',
			'size' => 49903616,
			'md5' => 'd31069b620820b75730d2def7690c271'
		],
		'virtio-win-0.1.102' => [
			'name' => 'virtio-win-0.1.102.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.102/virtio-win-0.1.102.iso',
			'size' => 160755712,
			'md5' => '712561dd78ef532c54f8fee927c1ce2e'
		],
		'virtio-win-0.1.101' => [
			'name' => 'virtio-win-0.1.101.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.101/virtio-win-0.1.101.iso',
			'size' => 160755712,
			'md5' => 'cf73576efc03685907c1fa49180ea388'
		],
		'virtio-win-0.1.100' => [
			'name' => 'virtio-win-0.1.100.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.100/virtio-win-0.1.100.iso',
			'size' => 160704512,
			'md5' => '8b21136f988bef7981ee580e9101b6b4'
		],
		'virtio-win-0.1.96' => [
			'name' => 'virtio-win-0.1.96.iso',
			'url' => 'https://fedorapeople.org/groups/virt/virtio-win/direct-downloads/archive-virtio/virtio-win-0.1.96/virtio-win-0.1.96.iso',
			'size' => 160659456,
			'md5' => 'd406bf6748b9ba4c872c5b5301ba7272'
		]
	];

	// Read configuration file (guaranteed to exist)
	$domain_cfgfile = "/boot/config/domain.cfg";
        // This will clean any ^M characters (\r) caused by windows from the config file
        shell_exec("sed -i 's!\r!!g' '$domain_cfgfile'");
	$domain_cfg = parse_ini_file($domain_cfgfile);

	if ($domain_cfg['DEBUG'] != "yes") {
		error_reporting(0);
	}

	if (empty($domain_cfg['VMSTORAGEMODE'])) {
		$domain_cfg['VMSTORAGEMODE'] = "auto";
	}
	if (!empty($domain_cfg['DOMAINDIR'])) {
		$domain_cfg['DOMAINDIR'] = rtrim($domain_cfg['DOMAINDIR'], '/') . '/';
	}
	if (!empty($domain_cfg['MEDIADIR'])) {
		$domain_cfg['MEDIADIR'] = rtrim($domain_cfg['MEDIADIR'], '/') . '/';
	}

	$domain_bridge = (!($domain_cfg['BRNAME'])) ? 'virbr0' : $domain_cfg['BRNAME'];
	$msg = (empty($domain_bridge)) ? "Error: Setup Bridge in Settings/Network Settings" : false;
	$libvirt_service = isset($domain_cfg['SERVICE']) ? $domain_cfg['SERVICE'] : "disable";

	if ($libvirt_running == "yes"){
		$lv = new Libvirt('qemu:///system', null, null, false);
		$arrHostInfo = $lv->host_get_node_info();
		$maxcpu = (int)$arrHostInfo['cpus'];
		$maxmem = number_format(($arrHostInfo['memory'] / 1048576), 1, '.', ' ');
	}

	//set color on even rows for white or black theme
	function bcolor($row) {
		global $display;

		if (empty($display) || $display['theme'] == "white")
			return ($row % 2 == 0) ? "transparent" : "#F8F8F8";

		return ($row % 2 == 0) ? "transparent" : "#0C0C0C";
	}

	function mk_dropdown_options($arrOptions, $strSelected) {
		foreach ($arrOptions as $key => $label) {
			echo mk_option($strSelected, $key, $label);
		}
	}

	function appendOrdinalSuffix($number) {
		$ends = array('th','st','nd','rd','th','th','th','th','th','th');

		if (($number % 100) >= 11 && ($number % 100) <= 13) {
			$abbreviation = $number . 'th';
		} else {
			$abbreviation = $number . $ends[$number % 10];
		}

		return $abbreviation;
	}

	function sanitizeVendor($strVendor) {
		// Specialized vendor name cleanup
		// e.g.: Advanced Micro Devices, Inc. [AMD/ATI] --> Advanced Micro Devices, Inc.
		if (preg_match('/(?P<gpuvendor>.+) \[.+\]/', $strVendor, $arrGPUMatch)) {
			$strVendor = $arrGPUMatch['gpuvendor'];
		}

		$strVendor = str_replace('Advanced Micro Devices', 'AMD', $strVendor);
		$strVendor = str_replace('Samsung Electronics Co.', 'Samsung', $strVendor);
		$strVendor = str_replace([' Corporation', ' Semiconductor ', ' Technology Group Ltd.', ' System, Inc.', ' Systems, Inc.'], '', $strVendor);
		$strVendor = str_replace([' Co., Ltd.', ', Ltd.', ', Ltd', ', Inc.'], '', $strVendor);
		return $strVendor;
	}

	function sanitizeProduct($strProduct) {
		$strProduct = str_replace(' PCI Express', ' PCIe', $strProduct);
		$strProduct = str_replace(' High Definition ', ' HD ', $strProduct);
		return $strProduct;
	}

	function getDiskImageInfo($strImgPath) {
		$arrJSON = json_decode(shell_exec("qemu-img info --output json " . escapeshellarg($strImgPath) . " 2>/dev/null"), true);
		return $arrJSON;
	}


	$cacheValidPCIDevices = null;
	function getValidPCIDevices() {
		global $cacheValidPCIDevices;
		global $disks;

		if (!is_null($cacheValidPCIDevices)) {
			return $cacheValidPCIDevices;
		}

		$strOSNetworkDevice = trim(exec("udevadm info -q path -p /sys/class/net/eth0 2>/dev/null | grep -Po '0000:\K\w{2}:\w{2}\.\w{1}'"));

		$arrOSDiskControllers = [];
		foreach ($disks as $strDisk => $arrDisk) {
			if (!empty($arrDisk['device']) && file_exists('/dev/'.$arrDisk['device'])) {
				$strOSDiskController = trim(exec("udevadm info -q path -n /dev/".$arrDisk['device']." | grep -Po '0000:\K\w{2}:\w{2}\.\w{1}'"));
				if (!empty($strOSDiskController)) {
					$arrOSDiskControllers[] = $strOSDiskController;
				}
			}
		}
		$arrOSDiskControllers = array_values(array_unique($arrOSDiskControllers));

		$arrBlacklistIDs = $arrOSDiskControllers;
		if (!empty($strOSNetworkDevice)) {
			$arrBlacklistIDs[] = $strOSNetworkDevice;
		}
		$arrBlacklistClassIDregex = '/^(05|06|08|0a|0b|0c05)/';
		// Got Class IDs at the bottom of /usr/share/hwdata/pci.ids
		$arrWhitelistGPUClassIDregex = '/^(0001|03)/';
		$arrWhitelistAudioClassIDregex = '/^(0403)/';

		$arrValidPCIDevices = [];

		exec("lspci -m -nn 2>/dev/null", $arrAllPCIDevices);

		foreach ($arrAllPCIDevices as $strPCIDevice) {
			// Example: 00:1f.0 "ISA bridge [0601]" "Intel Corporation [8086]" "Z77 Express Chipset LPC Controller [1e44]" -r04 "Micro-Star International Co., Ltd. [MSI] [1462]" "Device [7759]"
			if (preg_match('/^(?P<id>\S+) \"(?P<type>[^"]+) \[(?P<typeid>[a-f0-9]{4})\]\" \"(?P<vendorname>[^"]+) \[(?P<vendorid>[a-f0-9]{4})\]\" \"(?P<productname>[^"]+) \[(?P<productid>[a-f0-9]{4})\]\"/', $strPCIDevice, $arrMatch)) {

				$boolBlacklisted = false;
				if (in_array($arrMatch['id'], $arrBlacklistIDs) || preg_match($arrBlacklistClassIDregex, $arrMatch['typeid'])) {
					// Device blacklisted, skip device
					$boolBlacklisted = true;
				}

				$strClass = 'other';
				if (preg_match($arrWhitelistGPUClassIDregex, $arrMatch['typeid'])) {
					$strClass = 'vga';
					// Specialized product name cleanup for GPU
					// GF116 [GeForce GTX 550 Ti] --> GeForce GTX 550 Ti
					if (preg_match('/.+\[(?P<gpuname>.+)\]/', $arrMatch['productname'], $arrGPUMatch)) {
						$arrMatch['productname'] = $arrGPUMatch['gpuname'];
					}
				} else if (preg_match($arrWhitelistAudioClassIDregex, $arrMatch['typeid'])) {
					$strClass = 'audio';
				}

				if ($strClass == 'vga' &&
					strpos($arrMatch['id'], '00:') === 0 &&
					(stripos($arrMatch['productname'], 'integrated') !== false || strpos($arrMatch['vendorname'], 'Intel ') !== false)) {
					// Our sorry attempt to detect a integrated gpu
					// Integrated gpus dont work for passthrough, skip device
					continue;
				}

				if (!file_exists('/sys/bus/pci/devices/0000:' . $arrMatch['id'] . '/iommu_group/')) {
					// No IOMMU support for device, skip device
					continue;
				}

				// Attempt to get the current kernel-bound driver for this pci device
				$strDriver = '';
				if (is_link('/sys/bus/pci/devices/0000:' . $arrMatch['id'] . '/driver')) {
					$strLink = @readlink('/sys/bus/pci/devices/0000:' . $arrMatch['id'] . '/driver');
					if (!empty($strLink)) {
						$strDriver = basename($strLink);
					}
				}

				// Clean up the vendor and product name
				$arrMatch['vendorname'] = sanitizeVendor($arrMatch['vendorname']);
				$arrMatch['productname'] = sanitizeProduct($arrMatch['productname']);

				$arrValidPCIDevices[] = array(
					'id' => $arrMatch['id'],
					'type' => $arrMatch['type'],
					'typeid' => $arrMatch['typeid'],
					'vendorid' => $arrMatch['vendorid'],
					'vendorname' => $arrMatch['vendorname'],
					'productid' => $arrMatch['productid'],
					'productname' => $arrMatch['productname'],
					'class' => $strClass,
					'driver' => $strDriver,
					'name' => $arrMatch['vendorname'] . ' ' . $arrMatch['productname'],
					'blacklisted' => $boolBlacklisted
				);
			}
		}

		$cacheValidPCIDevices = $arrValidPCIDevices;

		return $arrValidPCIDevices;
	}


	function getValidGPUDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidGPUDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'vga' && !$arrDev['blacklisted']);
		});

		return $arrValidGPUDevices;
	}


	function getValidAudioDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidAudioDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'audio' && !$arrDev['blacklisted']);
		});

		return $arrValidAudioDevices;
	}


	function getValidOtherDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidOtherDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'other' && !$arrDev['blacklisted']);
		});

		return $arrValidOtherDevices;
	}


	function getValidOtherStubbedDevices() {
		$arrValidPCIDevices = getValidPCIDevices();

		$arrValidOtherStubbedDevices = array_filter($arrValidPCIDevices, function($arrDev) {
			return ($arrDev['class'] == 'other' && !$arrDev['blacklisted'] && in_array($arrDev['driver'], ['pci-stub', 'vfio-pci']));
		});

		return $arrValidOtherStubbedDevices;
	}


	$cacheValidUSBDevices = null;
	function getValidUSBDevices() {
		global $cacheValidUSBDevices;

		if (!is_null($cacheValidUSBDevices)) {
			return $cacheValidUSBDevices;
		}

		$arrValidUSBDevices = array();

		// Get a list of all usb hubs so we can blacklist them
		exec("cat /sys/bus/usb/drivers/hub/*/modalias | grep -Po 'usb:v\K\w{9}' | tr 'p' ':'", $arrAllUSBHubs);

		exec("lsusb 2>/dev/null", $arrAllUSBDevices);

		foreach ($arrAllUSBDevices as $strUSBDevice) {
			if (preg_match('/^.+ID (?P<id>\S+)(?P<name>.*)$/', $strUSBDevice, $arrMatch)) {
				if (stripos($GLOBALS['var']['flashGUID'], str_replace(':', '-', $arrMatch['id'])) === 0) {
					// Device id matches the unraid boot device, skip device
					continue;
				}

				if (in_array(strtoupper($arrMatch['id']), $arrAllUSBHubs)) {
					// Device class is a Hub, skip device
					continue;
				}

				$arrMatch['name'] = trim($arrMatch['name']);

				if (empty($arrMatch['name'])) {
					// Device name is blank, attempt to lookup usb details
					exec("lsusb -d ".$arrMatch['id']." -v 2>/dev/null | grep -Po '^\s+(iManufacturer|iProduct)\s+[1-9]+ \K[^\\n]+'", $arrAltName);
					$arrMatch['name'] = trim(implode(' ', (array)$arrAltName));

					if (empty($arrMatch['name'])) {
						// Still blank, replace using fallback default
						$arrMatch['name'] = '[unnamed device]';
					}
				}

				// Clean up the name
				$arrMatch['name'] = sanitizeVendor($arrMatch['name']);

				$arrValidUSBDevices[] = array(
					'id' => $arrMatch['id'],
					'name' => $arrMatch['name'],
				);
			}
		}

		uasort($arrValidUSBDevices, function ($a, $b) {
			return strcasecmp($a['id'], $b['id']);
		});

		$cacheValidUSBDevices = $arrValidUSBDevices;

		return $arrValidUSBDevices;
	}


	function getValidMachineTypes() {
		global $lv;

		$arrValidMachineTypes = [];

		$arrQEMUInfo = $lv->get_connect_information();
		$arrMachineTypes = $lv->get_machine_types('x86_64');

		$strQEMUVersion = $arrQEMUInfo['hypervisor_major'] . '.' . $arrQEMUInfo['hypervisor_minor'];

		foreach ($arrMachineTypes as $arrMachine) {
			if ($arrMachine['name'] == 'q35') {
				// Latest Q35
				$arrValidMachineTypes['pc-q35-' . $strQEMUVersion] = 'Q35-' . $strQEMUVersion;
			}
			if (strpos($arrMachine['name'], 'q35-') !== false) {
				// Prior releases of Q35
				$arrValidMachineTypes[$arrMachine['name']] = str_replace(['q35', 'pc-'], ['Q35', ''], $arrMachine['name']);
			}
			if ($arrMachine['name'] == 'pc') {
				// Latest i440fx
				$arrValidMachineTypes['pc-i440fx-' . $strQEMUVersion] = 'i440fx-' . $strQEMUVersion;
			}
			if (strpos($arrMachine['name'], 'i440fx-') !== false) {
				// Prior releases of i440fx
				$arrValidMachineTypes[$arrMachine['name']] = str_replace('pc-', '', $arrMachine['name']);
			}
		}

		arsort($arrValidMachineTypes);

		return $arrValidMachineTypes;
	}


	function getLatestMachineType($strType = 'i440fx') {
		$arrMachineTypes = getValidMachineTypes();

		foreach ($arrMachineTypes as $key => $value) {
			if (stripos($key, $strType) !== false) {
				return $key;
			}
		}

		return array_shift(array_keys($arrMachineTypes));
	}


	function getValidDiskDrivers() {
		$arrValidDiskDrivers = [
			'raw' => 'raw',
			'qcow2' => 'qcow2'
		];

		return $arrValidDiskDrivers;
	}


	function getValidDiskBuses() {
		$arrValidDiskBuses = [
			'virtio' => 'VirtIO',
			'sata' => 'SATA',
			'ide' => 'IDE',
			'usb' => 'USB'
		];

		return $arrValidDiskBuses;
	}


	function getValidCdromBuses() {
		$arrValidCdromBuses = [
			'sata' => 'SATA',
			'ide' => 'IDE',
			'usb' => 'USB'
		];

		return $arrValidCdromBuses;
	}


	function getValidVNCModels() {
		$arrValidVNCModels = [
			'cirrus' => 'Cirrus',
			'qxl' => 'QXL (best)',
			'vmvga' => 'vmvga'
		];

		return $arrValidVNCModels;
	}


	function getValidKeyMaps() {
		$arrValidKeyMaps = [
			'ar' => 'Arabic (ar)',
			'hr' => 'Croatian (hr)',
			'cz' => 'Czech (cz)',
			'da' => 'Danish (da)',
			'nl' => 'Dutch (nl)',
			'nl-be' => 'Dutch-Belgium (nl-be)',
			'en-gb' => 'English-United Kingdom (en-gb)',
			'en-us' => 'English-United States (en-us)',
			'es' => 'Español (es)',
			'et' => 'Estonian (et)',
			'fo' => 'Faroese (fo)',
			'fi' => 'Finnish (fi)',
			'fr' => 'French (fr)',
			'bepo' => 'French-Bépo (bepo)',
			'fr-be' => 'French-Belgium (fr-be)',
			'fr-ca' => 'French-Canadian (fr-ca)',
			'fr-ch' => 'French-Switzerland (fr-ch)',
			'de-ch' => 'German-Switzerland (de-ch)',
			'de' => 'German (de)',
			'hu' => 'Hungarian (hu)',
			'is' => 'Icelandic (is)',
			'it' => 'Italian (it)',
			'ja' => 'Japanese (ja)',
			'lv' => 'Latvian (lv)',
			'lt' => 'Lithuanian (lt)',
			'mk' => 'Macedonian (mk)',
			'no' => 'Norwegian (no)',
			'pl' => 'Polish (pl)',
			'pt-br' => 'Portuguese-Brazil (pt-br)',
			'ru' => 'Russian (ru)',
			'sl' => 'Slovene (sl)',
			'sv' => 'Swedish (sv)',
			'th' => 'Thailand (th)',
			'tr' => 'Turkish (tr)'
		];

		return $arrValidKeyMaps;
	}


	function getHostCPUModel() {
		$cpu = explode('#', exec("dmidecode -q -t 4|awk -F: '{if(/Version:/) v=$2; else if(/Current Speed:/) s=$2} END{print v\"#\"s}'"));
		list($strCPUModel) = explode('@', str_replace(array("Processor","CPU","(C)","(R)","(TM)"), array("","","&#169;","&#174;","&#8482;"), $cpu[0]) . '@');
		return trim($strCPUModel);
	}


	function getNetworkBridges() {
		exec("brctl show|grep -Po '^(vir)?br\d\S*'", $arrValidBridges);

		if (!is_array($arrValidBridges)) {
			$arrValidBridges = [];
		}

		// Make sure the default libvirt bridge is first in the list
		if (($key = array_search('virbr0', $arrValidBridges)) !== false) {
			unset($arrValidBridges[$key]);
		}
		// We always list virbr0 because libvirt might not be started yet (thus the bridge doesn't exists)
		array_unshift($arrValidBridges, 'virbr0');

		return array_values($arrValidBridges);
	}


	function domain_to_config($uuid) {
		global $lv;
		global $domain_cfg;

		$arrValidGPUDevices = getValidGPUDevices();
		$arrValidAudioDevices = getValidAudioDevices();
		$arrValidOtherDevices = getValidOtherDevices();
		$arrValidUSBDevices = getValidUSBDevices();
		$arrValidDiskDrivers = getValidDiskDrivers();

		$res = $lv->domain_get_domain_by_uuid($uuid);
		$dom = $lv->domain_get_info($res);
		$medias = $lv->get_cdrom_stats($res);
		$disks = $lv->get_disk_stats($res, false);
		$arrNICs = $lv->get_nic_info($res);
		$arrHostDevs = $lv->domain_get_host_devices_pci($res);
		$arrUSBDevs = $lv->domain_get_host_devices_usb($res);


		// Metadata Parsing
		// libvirt xpath parser sucks, use php's xpath parser instead
		$strDOMXML = $lv->domain_get_xml($res);
		$xmldoc = new DOMDocument();
		$xmldoc->loadXML($strDOMXML);
		$xpath = new DOMXPath($xmldoc);
		$objNodes = $xpath->query('//domain/metadata/*[local-name()=\'vmtemplate\']/@*');

		$arrTemplateValues = [];
		if ($objNodes->length > 0) {
			foreach ($objNodes as $objNode) {
				$arrTemplateValues[$objNode->nodeName] = $objNode->nodeValue;
			}
		}

		if (empty($arrTemplateValues['name'])) {
			$arrTemplateValues['name'] = 'Custom';
		}


		$arrGPUDevices = [];
		$arrAudioDevices = [];
		$arrOtherDevices = [];

		// check for vnc; add to arrGPUDevices
		$intVNCPort = $lv->domain_get_vnc_port($res);
		if (!empty($intVNCPort)) {
			$arrGPUDevices[] = [
				'id' => 'vnc',
				'model' => $lv->domain_get_vnc_model($res),
				'keymap' => $lv->domain_get_vnc_keymap($res)
			];
		}

		foreach ($arrHostDevs as $arrHostDev) {
			$arrFoundGPUDevices = array_filter($arrValidGPUDevices, function($arrDev) use ($arrHostDev) { return ($arrDev['id'] == $arrHostDev['id']); });
			if (!empty($arrFoundGPUDevices)) {
				$arrGPUDevices[] = ['id' => $arrHostDev['id']];
				continue;
			}

			$arrFoundAudioDevices = array_filter($arrValidAudioDevices, function($arrDev) use ($arrHostDev) { return ($arrDev['id'] == $arrHostDev['id']); });
			if (!empty($arrFoundAudioDevices)) {
				$arrAudioDevices[] = ['id' => $arrHostDev['id']];
				continue;
			}

			$arrFoundOtherDevices = array_filter($arrValidOtherDevices, function($arrDev) use ($arrHostDev) { return ($arrDev['id'] == $arrHostDev['id']); });
			if (!empty($arrFoundOtherDevices)) {
				$arrOtherDevices[] = ['id' => $arrHostDev['id']];
				continue;
			}
		}

		// Add claimed USB devices by this VM to the available USB devices
		/*
		foreach($arrUSBDevs as $arrUSB) {
			$arrValidUSBDevices[] = array(
				'id' => $arrUSB['id'],
				'name' => $arrUSB['product'],
			);
		}
		*/

		$arrDisks = [];
		foreach ($disks as $i => $disk) {
			$strPath = (empty($disk['file']) ? $disk['partition'] : $disk['file']);

			$default_option = 'auto';
			if (empty($domain_cfg['DOMAINDIR']) ||
				!file_exists($domain_cfg['DOMAINDIR']) ||
				!is_file($strPath) ||
				strpos($domain_cfg['DOMAINDIR'], dirname(dirname($strPath))) === false ||
				basename($strPath) != 'vdisk'.($i+1).'.img') {

				$default_option = 'manual';
			}

			$arrDisks[] = [
				'new' => $strPath,
				'size' => '',
				'driver' => 'raw',
				'dev' => $disk['device'],
				'bus' => $disk['bus'],
				'select' => $default_option
			];
		}
		if (empty($arrDisks)) {
			$arrDisks[] = [
				'new' => '',
				'size' => '',
				'driver' => 'raw',
				'dev' => 'hda',
				'select' => '',
				'bus' => 'virtio'
			];
		}

		// HACK: If there's only 1 cdrom and the dev=hdb then it's most likely a VirtIO Driver ISO instead of the OS Install ISO
		if (!empty($medias) && count($medias) == 1 && array_key_exists('device', $medias[0]) && $medias[0]['device'] == 'hdb') {
			$medias[] = null;
			$medias = array_reverse($medias);
		}

		return [
			'template' => $arrTemplateValues,
			'domain' => [
				'name' => $lv->domain_get_name($res),
				'desc' => $lv->domain_get_description($res),
				'persistent' => 1,
				'uuid' => $lv->domain_get_uuid($res),
				'clock' => $lv->domain_get_clock_offset($res),
				'arch' => $lv->domain_get_arch($res),
				'machine' => $lv->domain_get_machine($res),
				'mem' => $lv->domain_get_current_memory($res),
				'maxmem' => $lv->domain_get_memory($res),
				'password' => '', //TODO?
				'cpumode' => $lv->domain_get_cpu_type($res),
				'vcpus' => $dom['nrVirtCpu'],
				'vcpu' => $lv->domain_get_vcpu_pins($res),
				'hyperv' => ($lv->domain_get_feature($res, 'hyperv') ? 1 : 0),
				'autostart' => ($lv->domain_get_autostart($res) ? 1 : 0),
				'state' => $lv->domain_state_translate($dom['state']),
				'ovmf' => ($lv->domain_get_ovmf($res) ? 1 : 0),
				'usbmode' => ($lv->_get_single_xpath_result($res, '//domain/devices/controller[@model=\'nec-xhci\']') ? 'usb3' : 'usb2')
			],
			'media' => [
				'cdrom' => (!empty($medias) && !empty($medias[0]) && array_key_exists('file', $medias[0])) ? $medias[0]['file'] : '',
				'cdrombus' => (!empty($medias) && !empty($medias[0]) && array_key_exists('bus', $medias[0])) ? $medias[0]['bus'] : (stripos($lv->domain_get_machine($res), 'q35')!==false ? 'sata': 'ide'),
				'drivers' => (!empty($medias) && !empty($medias[1]) && array_key_exists('file', $medias[1])) ? $medias[1]['file'] : '',
				'driversbus' => (!empty($medias) && !empty($medias[1]) && array_key_exists('bus', $medias[1])) ? $medias[1]['bus'] : (stripos($lv->domain_get_machine($res), 'q35')!==false ? 'sata': 'ide')
			],
			'disk' => $arrDisks,
			'gpu' => $arrGPUDevices,
			'audio' => $arrAudioDevices,
			'pci' => $arrOtherDevices,
			'nic' => $arrNICs,
			'usb' => $arrUSBDevs,
			'shares' => $lv->domain_get_mount_filesystems($res)
		];
	}

?>
