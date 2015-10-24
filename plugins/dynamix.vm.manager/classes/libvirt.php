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
	class Libvirt {
		private $conn;
		private $last_error;
		private $allow_cached = true;
		private $dominfos = array();
		private $enabled = false;

		function Libvirt($uri = false, $login = false, $pwd = false, $debug=false) {
			if ($debug)
				$this->set_logfile($debug);
			if ($uri != false) {
				$this->enabled = $this->connect($uri, $login, $pwd);
			}
		}

		function _set_last_error() {
			$this->last_error = libvirt_get_last_error();
			return false;
		}

		function enabled() {
			return $this->enabled;
		}

		function set_logfile($filename) {
			if (!libvirt_logfile_set($filename,'10M'))
				return $this->_set_last_error();

			return true;
		}

		function get_capabilities() {
			$tmp = libvirt_connect_get_capabilities($this->conn);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_machine_types($arch = 'x86_64' /* or 'i686' */) {
			$tmp = libvirt_connect_get_machine_types($this->conn);

			if (!$tmp)
				return $this->_set_last_error();

			if (empty($tmp[$arch]))
				return [];

			return $tmp[$arch];
		}

		function get_default_emulator() {
			$tmp = libvirt_connect_get_capabilities($this->conn, '//capabilities/guest/arch/domain/emulator');
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function set_folder_nodatacow($folder) {
			if (!is_dir($folder)) {
				return false;
			}

			// determine the actual disk if user share is being used
			if (strpos($folder, '/mnt/user/') === 0) {
				$tmp = parse_ini_string(shell_exec("getfattr -n user.LOCATION " . escapeshellarg($folder) . " | grep user.LOCATION"));
				$folder = str_replace('/mnt/user', '/mnt/' . $tmp['user.LOCATION'], $folder);  // replace 'user' with say 'cache' or 'disk1' etc
			}

			@shell_exec("chattr +C -R " . escapeshellarg($folder) . " &>/dev/null");

			return true;
		}

		function create_disk_image($disk, $vmname = '', $diskid = 1) {
			$arrReturn = [];

			if (!empty($disk['size'])) {
				$disk['size'] = str_replace(array("KB","MB","GB","TB","PB"), array("K","M","G","T","P"), strtoupper($disk['size']));
			}
			if (empty($disk['driver'])) {
				$disk['driver'] = 'raw';
			}

			// if new is a folder then
			//   if existing then
			//     create folder 'new/vmname'
			//     create image file as new/vmname/vdisk[1-x].xxx
			//   if doesn't exist then
			//     create folder 'new'
			//     create image file as new/vdisk[1-x].xxx

			// if new is a file then
			//   if existing then
			//     nothing to do
			//   if doesn't exist then
			//     create folder dirname('new') if needed
			//     create image file as new --> if size is specified

			if (!empty($disk['new'])) {
				if (is_file($disk['new']) || is_block($disk['new'])) {
					$disk['image'] = $disk['new'];
				}
			}

			if (!empty($disk['image'])) {
				// Use existing disk image

				if (is_block($disk['image'])) {
					// Valid block device, return as-is
					return $disk;
				}

				if (is_file($disk['image'])) {
					$json_info = json_decode(shell_exec("qemu-img info --output json " . escapeshellarg($disk['image'])), true);
					$disk['driver'] = $json_info['format'];

					if (!empty($disk['size'])) {
						//TODO: expand disk image if size param is larger
					}

					return $disk;
				}

				$disk['new'] = $disk['image'];
			}

			if (!empty($disk['new'])) {
				// Create new disk image
				$strImgFolder = $disk['new'];
				$strImgPath = '';

				if (strpos($strImgFolder, '/dev/') === 0) {
					// ERROR invalid block device
					$arrReturn = [
						'error' => "Not a valid block device location '" . $strImgFolder . "'"
					];

					return $arrReturn;
				}

				if (empty($disk['size'])) {
					// ERROR invalid disk size
					$arrReturn = [
						'error' => "Please specify a disk size for '" . $strImgFolder . "'"
					];

					return $arrReturn;
				}

				$path_parts = pathinfo($strImgFolder);
				if (empty($path_parts['extension'])) {
					// 'new' is a folder

					if (substr($strImgFolder, -1) != '/') {
						$strImgFolder .= '/';
					}

					if (is_dir($strImgFolder)) {
						// 'new' is a folder and already exists, append vmname folder
						$strImgFolder .= preg_replace('((^\.)|\/|(\.$))', '_', $vmname) . '/';
					}

					// create folder if needed
					if (!is_dir($strImgFolder)) {
						mkdir($strImgFolder, 0777, true);
						chown($strImgFolder, 'nobody');
						chgrp($strImgFolder, 'users');
					}

					$this->set_folder_nodatacow($strImgFolder);

					$strExt = ($disk['driver'] == 'raw') ? 'img' : $disk['driver'];

					$strImgPath = $strImgFolder . 'vdisk' . $diskid . '.' . $strExt;

				} else {
					// 'new' is a file

					// create parent folder if needed
					if (!is_dir($path_parts['dirname'])) {
						mkdir($path_parts['dirname'], 0777, true);
						chown($path_parts['dirname'], 'nobody');
						chgrp($path_parts['dirname'], 'users');
					}

					$this->set_folder_nodatacow($path_parts['dirname']);

					$strImgPath = $strImgFolder;
				}


				if (is_file($strImgPath)) {
					$json_info = json_decode(shell_exec("qemu-img info --output json " . escapeshellarg($strImgPath)), true);
					$disk['driver'] = $json_info['format'];
					$return_value = 0;
				} else {
					$strLastLine = exec("qemu-img create -q -f " . escapeshellarg($disk['driver']) . " " . escapeshellarg($strImgPath) . " " . escapeshellarg($disk['size']), $output, $return_value);
					chmod($strImgPath, 0777);
					chown($strImgPath, 'nobody');
					chgrp($strImgPath, 'users');
				}

				if ($return_value != 0) {

					// ERROR during image creation, return message to user
					$arrReturn = [
						'error' => "Error creating disk image '" . $strImgPath . "': " . $strLastLine,
						'error_output' => $output
					];

				} else {

					// Success!
					$arrReturn = [
						'image' => $strImgPath,
						'driver' => $disk['driver']
					];
					if (!empty($disk['dev'])) {
						$arrReturn['dev'] = $disk['dev'];
					}
					if (!empty($disk['bus'])) {
						$arrReturn['bus'] = $disk['bus'];
					}

				}
			}

			return $arrReturn;
		}


		function config_to_xml($config) {
			$domain = $config['domain'];
			$media = $config['media'];
			$nics = $config['nic'];
			$disks = $config['disk'];
			$usb = $config['usb'];
			$shares = $config['shares'];
			$gpus = $config['gpu'];
			$audios = $config['audio'];
			$template = $config['template'];


			$type = $domain['type'];
			$name = $domain['name'];
			$mem = $domain['mem'];
			$maxmem = $mem;
			if (!empty($domain['maxmem'])) {
				$maxmem = $domain['maxmem'];
			}
			$uuid = (!empty($domain['uuid']) ? $domain['uuid'] : $this->domain_generate_uuid());
			$machine = $domain['machine'];
			$machine_type = (stripos($machine, 'q35') !== false ? 'q35' : 'pc');
			$os_type = ((empty($template['os']) || stripos($template['os'], 'windows') === false) ? 'other' : 'windows');
			$emulator = $this->get_default_emulator();
			$arch = $domain['arch'];
			$pae = '';
			if ($arch == 'i686'){
				$pae = '<pae/>';
			}

			$loader = '';
			if (!empty($domain['ovmf'])) {
				$loader = "<loader type='pflash'>/usr/share/qemu/ovmf-x64/OVMF-pure-efi.fd</loader>";
			}

			$metadata = '';
			if (!empty($template)) {
				$template_options = '';
				foreach ($template as $key => $value) {
					$template_options .= $key . "='" . htmlspecialchars($value, ENT_QUOTES | ENT_XML1) . "' ";
				}
				$metadata = "<metadata><vmtemplate " . $template_options . "/></metadata>";
			}

			$vcpus = 1;
			$vcpupinstr = '';

			if (!empty($domain['vcpu']) && is_array($domain['vcpu'])) {
				$vcpus = count($domain['vcpu']);
				foreach($domain['vcpu'] as $i => $vcpu) {
					$vcpupinstr .= "<vcpupin vcpu='$i' cpuset='$vcpu'/>";
				}
			} else if (!empty($domain['vcpus'])) {
				$vcpus = $domain['vcpus'];
				for ($i=0; $i < $vcpus; $i++) {
					$vcpupinstr .= "<vcpupin vcpu='$i' cpuset='$i'/>";
				}
			}

			$cpumode = '';
			if (!empty($domain['cpumode']) && $domain['cpumode'] == 'host-passthrough') {
				$cpumode .= "mode='host-passthrough'";
			}

			$cpustr = "<cpu $cpumode>
							<topology sockets='1' cores='{$vcpus}' threads='1'/>
						</cpu>
						<vcpu placement='static'>{$vcpus}</vcpu>
						<cputune>
							$vcpupinstr
						</cputune>";

			$bus = "ide";
			$ctrl = '';
			if ($machine_type == 'q35'){
				$bus = "sata";
				$ctrl = "<controller type='usb' index='0' model='ich9-ehci1'/>
						<controller type='usb' index='0' model='ich9-uhci1'/>";
				if (!empty($domain['ovmf'])) {
					// OVMF + Q35 needs the bus set to usb for cdroms
					$bus = "usb";
				}
			}

			$clock = "<clock offset='" . $domain['clock'] . "'>
						<timer name='rtc' tickpolicy='catchup'/>
						<timer name='pit' tickpolicy='delay'/>
						<timer name='hpet' present='no'/>
					</clock>";

			$hyperv = '';
			if (!empty($domain['hyperv']) && $os_type == "windows") {
				$hyperv = "<hyperv>
							<relaxed state='on'/>
							<vapic state='on'/>
							<spinlocks state='on' retries='8191'/>
						</hyperv>";

				$clock = "<clock offset='" . $domain['clock'] . "'>
							<timer name='hypervclock' present='yes'/>
							<timer name='hpet' present='no'/>
						</clock>";
			}

			$usbstr = '';
			if (!empty($usb)) {
				foreach($usb as $i => $v){
					$usbx = explode(':', $v);
					$usbstr .= "<hostdev mode='subsystem' type='usb' managed='yes'>
									<source>
										<vendor id='0x".$usbx[0]."'/>
										<product id='0x".$usbx[1]."'/>
									</source>
								</hostdev>";
				}
			}

			$arrAvailableDevs = [];
			foreach (range('a', 'z') as $letter) {
				$arrAvailableDevs['hd' . $letter] = 'hd' . $letter;
			}
			$arrUsedBootOrders = [];

			//media settings
			$mediastr = '';
			if (!empty($media['cdrom'])) {
				unset($arrAvailableDevs['hda']);
				$arrUsedBootOrders[] = 2;
				$mediastr = "<disk type='file' device='cdrom'>
								<driver name='qemu'/>
								<source file='" . htmlspecialchars($media['cdrom'], ENT_QUOTES | ENT_XML1) . "'/>
								<target dev='hda' bus='$bus'/>
								<readonly/>
								<boot order='2'/>
							</disk>";
			}

			$driverstr = '';
			if (!empty($media['drivers']) && $os_type == "windows") {
				unset($arrAvailableDevs['hdb']);
				$driverstr = "<disk type='file' device='cdrom'>
								<driver name='qemu'/>
								<source file='" . htmlspecialchars($media['drivers'], ENT_QUOTES | ENT_XML1) . "'/>
								<target dev='hdb' bus='$bus'/>
								<readonly/>
							</disk>";
			}

			//disk settings
			$diskstr = '';
			$diskcount = 0;
			if (!empty($disks)) {
				foreach ($disks as $i => $disk) {
					if (!empty($disk['image']) | !empty($disk['new']) ) {
						//TODO: check if image/new is a block device
						$diskcount++;

						if (!empty($disk['new'])) {
							if (is_file($disk['new']) || is_block($disk['new'])) {
								$disk['image'] = $disk['new'];
							}
						}

						if (!empty($disk['image'])) {
							if (empty($disk['driver'])) {
								$disk['driver'] = 'raw';

								if (is_file($disk['image'])) {
									$json_info = json_decode(shell_exec("qemu-img info --output json " . escapeshellarg($disk['image'])), true);
									$disk['driver'] = $json_info['format'];
								}
							}
						} else {
							if (empty($disk['driver'])) {
								$disk['driver'] = 'raw';
							}

							$strImgFolder = $disk['new'];
							$strImgPath = '';

							$path_parts = pathinfo($strImgFolder);
							if (empty($path_parts['extension'])) {
								// 'new' is a folder

								if (substr($strImgFolder, -1) != '/') {
									$strImgFolder .= '/';
								}

								if (is_dir($strImgFolder)) {
									// 'new' is a folder and already exists, append domain name as child folder
									$strImgFolder .= preg_replace('((^\.)|\/|(\.$))', '_', $domain['name']) . '/';
								}

								$strExt = ($disk['driver'] == 'raw') ? 'img' : $disk['driver'];

								$strImgPath = $strImgFolder . 'vdisk' . $diskcount . '.' . $strExt;

							} else {
								// 'new' is a file
								$strImgPath = $strImgFolder;
							}

							if (is_file($strImgPath)) {
								$json_info = json_decode(shell_exec("qemu-img info --output json " . escapeshellarg($strImgPath)), true);
								$disk['driver'] = $json_info['format'];
							}

							$arrReturn = [
								'image' => $strImgPath,
								'driver' => $disk['driver']
							];
							if (!empty($disk['dev'])) {
								$arrReturn['dev'] = $disk['dev'];
							}
							if (!empty($disk['bus'])) {
								$arrReturn['bus'] = $disk['bus'];
							}

							$disk = $arrReturn;
						}

						if (empty($disk['bus'])) {
							$disk['bus'] = 'virtio';
						}

						if (empty($disk['dev']) || !in_array($disk['dev'], $arrAvailableDevs)) {
							$disk['dev'] = array_shift($arrAvailableDevs);
						}
						unset($arrAvailableDevs[$disk['dev']]);

						$bootorder = '';
						if (!in_array(1, $arrUsedBootOrders)) {
							$bootorder = "<boot order='1'/>";
							$arrUsedBootOrders[] = 1;
						}

						$readonly = '';
						if (!empty($disk['readonly'])) {
							$readonly = '<readonly/>';
						}

						$strDevType = @filetype(realpath($disk['image']));

						if ($strDevType == 'file' || $strDevType == 'block') {
							$strSourceType = ($strDevType == 'file' ? 'file' : 'dev');

							$diskstr .= "<disk type='" . $strDevType . "' device='disk'>
											<driver name='qemu' type='" . $disk['driver'] . "' cache='writeback'/>
											<source " . $strSourceType . "='" . htmlspecialchars($disk['image'], ENT_QUOTES | ENT_XML1) . "'/>
											<target bus='" . $disk['bus'] . "' dev='" . $disk['dev'] . "'/>
											$bootorder
											$readonly
										</disk>";
						}
					}
				}
			}

			$netstr = '';
			if (!empty($nics)) {
				foreach ($nics as $i => $nic) {
					if (empty($nic['mac']) || empty($nic['network'])) {
						continue;
					}

					$netstr .= "<interface type='bridge'>
									<mac address='{$nic['mac']}'/>
									<source bridge='" . htmlspecialchars($nic['network'], ENT_QUOTES | ENT_XML1) . "'/>
									<model type='virtio'/>
								</interface>";
				}
			}

			$sharestr = '';
			if (!empty($shares) && $os_type != "windows") {
				foreach ($shares as $i => $share) {
					if (empty($share['source']) || empty($share['target'])) {
						continue;
					}

					$sharestr .= "<filesystem type='mount' accessmode='passthrough'>
										<source dir='" . htmlspecialchars($share['source'], ENT_QUOTES | ENT_XML1) . "'/>
										<target dir='" . htmlspecialchars($share['target'], ENT_QUOTES | ENT_XML1) . "'/>
									</filesystem>";
				}
			}

			$passwdstr = '';
			if (!empty($domain['password'])){
				$passwdstr = "passwd='" . htmlspecialchars($domain['password'], ENT_QUOTES | ENT_XML1) . "'";
			}

			$pcidevs='';
			$gpuargs='';
			$gpudevs=[];
			$gpuargsdevs=[];
			$gpuincr=0;
			$vnc='';
			if (!empty($gpus)) {
				foreach ($gpus as $i => $gpu) {
					if (empty($gpu['id'])) {
						continue;
					}

					// Skip duplicate video devices
					if (in_array($gpu['id'], $gpudevs)) {
						continue;
					}

					if ($gpu['id'] == 'vnc') {
						$strKeyMap = '';
						if (!empty($gpu['keymap'])) {
							$strKeyMap = "keymap='" . $gpu['keymap'] . "'";
						}

						$vnc = "<input type='tablet' bus='usb'/>
								<input type='mouse' bus='ps2'/>
								<input type='keyboard' bus='ps2'/>
								<graphics type='vnc' port='-1' autoport='yes' websocket='-1' listen='0.0.0.0' $passwdstr $strKeyMap>
									<listen type='address' address='0.0.0.0'/>
								</graphics>";

						if (!empty($domain['ovmf'])) {
							// OVMF doesn't work with vmvga
							$vnc .= "<video>
										<model type='cirrus'/>
									</video>";
						} else {
							// SeaBIOS is cool with vmvga
							$vnc .= "<video>
										<model type='vmvga'/>
									</video>";
						}
						continue;
					}

					list($gpu_bus, $gpu_slot, $gpu_function) = explode(":", str_replace('.', ':', $gpu['id']));

					if (!empty($domain['ovmf'])) {

						// OVMF passthrough uses the normal hostdev and libvirt can fully manage the device
						$pcidevs .= "<hostdev mode='subsystem' type='pci' managed='yes'>
										<driver name='vfio'/>
										<source>
											<address domain='0x0000' bus='0x" . $gpu_bus . "' slot='0x" . $gpu_slot . "' function='0x" . $gpu_function . "'/>
										</source>
									</hostdev>";

					} else {

						// VGA BIOS passthrough uses qemu args and we have to manage the device (libvirt wont attach/detach the device driver to vfio-pci)
						switch ($machine_type) {

							case 'q35':
								$gpuargs .= "<qemu:arg value='-device'/>
											<qemu:arg value='vfio-pci,host={$gpu_bus}:{$gpu_slot}.{$gpu_function},bus=pcie.0,multifunction=on" . ((empty($gpuargs) && empty($vnc)) ? ',x-vga=on' : '') . "'/>";
								$gpuargsdevs[$gpu['id']] = ""; // no address needed
								break;

							case 'pc':
								$gpuargs .= "<qemu:arg value='-device'/>
											<qemu:arg value='vfio-pci,host={$gpu_bus}:{$gpu_slot}.{$gpu_function},bus=root.1,addr=0{$gpuincr}.0,multifunction=on" . ((empty($gpuargs) && empty($vnc)) ? ',x-vga=on' : '') . "'/>";
								$gpuargsdevs[$gpu['id']] = "0{$gpuincr}.0";
								break;
						}

					}

					$gpudevs[] = $gpu['id'];
					$gpuincr++;
				}
			}

			$audioargs='';
			$audiodevs=[];
			if (!empty($audios)) {
				foreach ($audios as $i => $audio) {
					if (empty($audio['id'])) {
						continue;
					}

					// Skip duplicate audio devices
					if (in_array($audio['id'], $audiodevs)) {
						continue;
					}

					list($audio_bus, $audio_slot, $audio_function) = explode(":", str_replace('.', ':', $audio['id']));

					if (!empty($domain['ovmf']) || empty($gpuargsdevs)) {

						$pcidevs .= "<hostdev mode='subsystem' type='pci' managed='yes'>
										<driver name='vfio'/>
										<source>
											<address domain='0x0000' bus='0x" . $audio_bus . "' slot='0x" . $audio_slot . "' function='0x" . $audio_function . "'/>
										</source>
									</hostdev>";

					} else {

						// VGA BIOS passthrough uses qemu args and we have to manage the device (libvirt wont attach/detach the device driver to vfio-pci)
						switch ($machine_type) {

							case 'q35':
								$audioargs .= "<qemu:arg value='-device'/>
												<qemu:arg value='vfio-pci,host={$audio_bus}:{$audio_slot}.{$audio_function},bus=pcie.0'/>";
								break;

							case 'pc':
								// Look for video device and see if this sound device is a function of that video card
								$addr = '';
								if (isset($gpuargsdevs[$audio_bus . ':' . $audio_slot . '.0'])) {
									$addr = str_replace('.0', '.' . $audio_function, $gpuargsdevs[$audio_bus . ':' . $audio_slot . '.0']);
								} else {
									$addr = "0" . $gpuincr++ . ".0";
								}

								$audioargs .= "<qemu:arg value='-device'/>
												<qemu:arg value='vfio-pci,host={$audio_bus}:{$audio_slot}.{$audio_function},bus=root.1,addr=" . $addr . "'/>";
								break;
						}

					}

					$audiodevs[] = $audio['id'];
				}
			}

			$cmdargs='';
			if (!empty($gpuargs) || !empty($audioargs)) {
				switch ($machine_type) {

					case 'q35':
						$cmdargs .= "<qemu:commandline>
										<qemu:arg value='-device'/>
										<qemu:arg value='ioh3420,bus=pcie.0,addr=1c.0,multifunction=on,port=2,chassis=1,id=root.1'/>
										$gpuargs
										$audioargs
									</qemu:commandline>";
						break;

					case 'pc':
						$cmdargs .= "<qemu:commandline>
										<qemu:arg value='-device'/>
										<qemu:arg value='ioh3420,bus=pci.0,addr=1c.0,multifunction=on,port=2,chassis=1,id=root.1'/>
										$gpuargs
										$audioargs
									</qemu:commandline>";
						break;

				}
			}


			return "<domain type='$type' xmlns:qemu='http://libvirt.org/schemas/domain/qemu/1.0'>
						<uuid>$uuid</uuid>
						<name>$name</name>
						<description>" . htmlspecialchars($domain['desc'], ENT_QUOTES | ENT_XML1) . "</description>
						$metadata
						<currentMemory>$mem</currentMemory>
						<memory>$maxmem</memory>
						<memoryBacking>
							<nosharepages/>
							<locked/>
						</memoryBacking>
						$cpustr
						<os>
							$loader
							<type arch='$arch' machine='$machine'>hvm</type>
						</os>
						<features>
							<acpi/>
							<apic/>
							$hyperv
							$pae
						</features>
						$clock
						<on_poweroff>destroy</on_poweroff>
						<on_reboot>restart</on_reboot>
						<on_crash>restart</on_crash>
						<devices>
							<emulator>$emulator</emulator>
							$diskstr
							$mediastr
							$driverstr
							$ctrl
							$sharestr
							$netstr
							$vnc
							<console type='pty'/>
							$pcidevs
							$usbstr
							<channel type='unix'>
								<target type='virtio' name='org.qemu.guest_agent.0'/>
							</channel>
							<memballoon model='virtio'>
								<alias name='balloon0'/>
							</memballoon>
						</devices>
						$cmdargs
					</domain>";

		}

		function domain_new($config) {

			// attempt to create all disk images if needed
			$diskcount = 0;
			if (!empty($config['disk'])) {
				foreach ($config['disk'] as $i => $disk) {
					if (!empty($disk['image']) | !empty($disk['new']) ) {
						$diskcount++;

						$disk = $this->create_disk_image($disk, $config['domain']['name'], $diskcount);

						if (!empty($disk['error'])) {
							$this->last_error = $disk['error'];
							return false;
						}

						$config['disk'][$i] = $disk;
					}
				}
			}

			// generate xml for this domain
			$strXML = $this->config_to_xml($config);


			// Start the VM now if requested
			if (!empty($config['domain']['startnow'])) {
				$tmp = libvirt_domain_create_xml($this->conn, $strXML);
				if (!$tmp)
					return $this->_set_last_error();
			}

			// Define the VM to persist
			if ($config['domain']['persistent']) {
				$tmp = libvirt_domain_define_xml($this->conn, $strXML);
				if (!$tmp)
					return $this->_set_last_error();

				$this->domain_set_autostart($tmp, $config['domain']['autostart'] == 1);
				return $tmp;
			}
			else
				return $tmp;

		}

		function vfio_bind($strPassthruDevice) {
			// Ensure we have leading 0000:
			$strPassthruDeviceShort = str_replace('0000:', '', $strPassthruDevice);
			$strPassthruDeviceLong = '0000:' . $strPassthruDeviceShort;

			// Determine the driver currently assigned to the device
			$strDriverSymlink = @readlink('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/driver');

			if ($strDriverSymlink !== false) {
				// Device is bound to a Driver already

				if (strpos($strDriverSymlink, 'vfio-pci') !== false) {
					// Driver bound to vfio-pci already - nothing left to do for this device now regarding vfio
					return true;
				}

				// Driver bound to some other driver - attempt to unbind driver
				if (file_put_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/driver/unbind', $strPassthruDeviceLong) === false) {
					$this->last_error = 'Failed to unbind device ' . $strPassthruDeviceShort . ' from current driver';
					return false;
				}
			}

			// Get Vendor and Device IDs for the passthru device
			$strVendor = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/vendor');
			$strDevice = file_get_contents('/sys/bus/pci/devices/' . $strPassthruDeviceLong . '/device');

			// Attempt to bind driver to vfio-pci
			if (file_put_contents('/sys/bus/pci/drivers/vfio-pci/new_id', $strVendor . ' ' . $strDevice) === false) {
				$this->last_error = 'Failed to bind device ' . $strPassthruDeviceShort . ' to vfio-pci driver';
				return false;
			}

			return true;
		}

		function connect($uri = 'null', $login = false, $password = false) {
			if ($login !== false && $password !== false) {
				$this->conn=libvirt_connect($uri, false, array(VIR_CRED_AUTHNAME => $login, VIR_CRED_PASSPHRASE => $password));
			} else {
				$this->conn=libvirt_connect($uri, false);
			}
			if ($this->conn==false)
				return $this->_set_last_error();

			return true;
		}

		function domain_change_boot_devices($domain, $first, $second) {
			$domain = $this->get_domain_object($domain);

			$tmp = libvirt_domain_change_boot_devices($domain, $first, $second);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_screen_dimensions($domain) {
			$dom = $this->get_domain_object($domain);

			$tmp = libvirt_domain_get_screen_dimensions($dom, $this->get_hostname() );
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_send_keys($domain, $keys) {
			$dom = $this->get_domain_object($domain);

			$tmp = libvirt_domain_send_keys($dom, $this->get_hostname(), $keys);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_send_pointer_event($domain, $x, $y, $clicked = 1, $release = false) {
			$dom = $this->get_domain_object($domain);

			$tmp = libvirt_domain_send_pointer_event($dom, $this->get_hostname(), $x, $y, $clicked, $release);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_disk_remove($domain, $dev) {
			$dom = $this->get_domain_object($domain);

			$tmp = libvirt_domain_disk_remove($dom, $dev);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function supports($name) {
			return libvirt_has_feature($name);
		}

		function macbyte($val) {
			if ($val < 16)
				return '0'.dechex($val);

			return dechex($val);
		}

		function generate_random_mac_addr($seed=false) {
			if (!$seed)
				$seed = 1;

			if ($this->get_hypervisor_name() == 'qemu')
				$prefix = '52:54:00';
			else
				$prefix = $this->macbyte(($seed * rand()) % 256).':'.
						  $this->macbyte(($seed * rand()) % 256).':'.
						  $this->macbyte(($seed * rand()) % 256);

			return $prefix.':'.
					$this->macbyte(($seed * rand()) % 256).':'.
					$this->macbyte(($seed * rand()) % 256).':'.
					$this->macbyte(($seed * rand()) % 256);
		}

		function get_connection() {
			return $this->conn;
		}

		function get_hostname() {
			return libvirt_connect_get_hostname($this->conn);
		}

		function get_domain_object($nameRes) {
			if (is_resource($nameRes))
				return $nameRes;

			$dom=libvirt_domain_lookup_by_name($this->conn, $nameRes);
			if (!$dom) {
				$dom=libvirt_domain_lookup_by_uuid_string($this->conn, $nameRes);
				if (!$dom)
					return $this->_set_last_error();
			}

			return $dom;
		}

		function get_xpath($domain, $xpath, $inactive = false) {
			$dom = $this->get_domain_object($domain);
			$flags = 0;
			if ($inactive)
				$flags = VIR_DOMAIN_XML_INACTIVE;

			$tmp = libvirt_domain_xml_xpath($dom, $xpath, $flags);
			if (!$tmp)
				return $this->_set_last_error();

			return $tmp;
		}

		function get_cdrom_stats($domain, $sort=true) {
			$dom = $this->get_domain_object($domain);

			$buses =  $this->get_xpath($dom, '//domain/devices/disk[@device="cdrom"]/target/@bus', false);
			$disks =  $this->get_xpath($dom, '//domain/devices/disk[@device="cdrom"]/target/@dev', false);
			$files =  $this->get_xpath($dom, '//domain/devices/disk[@device="cdrom"]/source/@file', false);

			$ret = array();
			for ($i = 0; $i < $disks['num']; $i++) {
				$tmp = libvirt_domain_get_block_info($dom, $disks[$i]);
				if ($tmp) {
					$tmp['bus'] = $buses[$i];
					$ret[] = $tmp;
				}
				else {
					$this->_set_last_error();

					$ret[] = array(
						'device' => $disks[$i],
						'file'   => $files[$i],
						'type'   => '-',
						'capacity' => '-',
						'allocation' => '-',
						'physical' => '-',
						'bus' => $buses[$i]
					);
				}
			}

			if ($sort) {
				for ($i = 0; $i < sizeof($ret); $i++) {
					for ($ii = 0; $ii < sizeof($ret); $ii++) {
						if (strcmp($ret[$i]['device'], $ret[$ii]['device']) < 0) {
							$tmp = $ret[$i];
							$ret[$i] = $ret[$ii];
							$ret[$ii] = $tmp;
						}
					}
				}
			}

			unset($buses);
			unset($disks);
			unset($files);

			return $ret;
		}

		function get_disk_stats($domain, $sort=true) {
			$dom = $this->get_domain_object($domain);

			$buses =  $this->get_xpath($dom, '//domain/devices/disk[@device="disk"]/target/@bus', false);
			$disks =  $this->get_xpath($dom, '//domain/devices/disk[@device="disk"]/target/@dev', false);
			$files =  $this->get_xpath($dom, '//domain/devices/disk[@device="disk"]/source/@file', false);

			$ret = array();
			for ($i = 0; $i < $disks['num']; $i++) {
				$tmp = libvirt_domain_get_block_info($dom, $disks[$i]);
				if ($tmp) {
					$tmp['bus'] = $buses[$i];

					// Libvirt reports 0 bytes for raw disk images that haven't been
					// written to yet so we just report the raw disk size for now
					if ( !empty($tmp['file']) &&
						 $tmp['type'] == 'raw' &&
						 empty($tmp['physical']) &&
						 is_file($tmp['file']) ) {

						$intSize = filesize($tmp['file']);
						$tmp['physical'] = $intSize;
						$tmp['capacity'] = $intSize;
					}

					$ret[] = $tmp;
				}
				else {
					$this->_set_last_error();

					$ret[] = array(
						'device' => $disks[$i],
						'file'   => $files[$i],
						'type'   => '-',
						'capacity' => '-',
						'allocation' => '-',
						'physical' => '-',
						'bus' => $buses[$i]
					);
				}
			}

			if ($sort) {
				for ($i = 0; $i < sizeof($ret); $i++) {
					for ($ii = 0; $ii < sizeof($ret); $ii++) {
						if (strcmp($ret[$i]['device'], $ret[$ii]['device']) < 0) {
							$tmp = $ret[$i];
							$ret[$i] = $ret[$ii];
							$ret[$ii] = $tmp;
						}
					}
				}
			}

			unset($buses);
			unset($disks);
			unset($files);

			return $ret;
		}

		function get_domain_type($domain) {
			$dom = $this->get_domain_object($domain);

			$tmp = $this->get_xpath($dom, '//domain/@type', false);
			if ($tmp['num'] == 0)
				return $this->_set_last_error();

			$ret = $tmp[0];
			unset($tmp);

			return $ret;
		}

		function get_domain_emulator($domain) {
			$dom = $this->get_domain_object($domain);

			$tmp =  $this->get_xpath($dom, '//domain/devices/emulator', false);
				if ($tmp['num'] == 0)
					return $this->_set_last_error();

			 $ret = $tmp[0];
			 unset($tmp);

			 return $ret;
		}

		function get_disk_capacity($domain, $physical=false, $disk='*', $unit='?') {
			$dom = $this->get_domain_object($domain);
			$tmp = $this->get_disk_stats($dom);

			$ret = 0;
			for ($i = 0; $i < sizeof($tmp); $i++) {
				if (($disk == '*') || ($tmp[$i]['device'] == $disk))
					if ($physical)
						$ret += $tmp[$i]['physical'];
					else
						$ret += $tmp[$i]['capacity'];
			}
			unset($tmp);

			return $this->format_size($ret, 2, $unit);
		}

		function get_disk_count($domain) {
			$dom = $this->get_domain_object($domain);
			$tmp = $this->get_disk_stats($dom);
			$ret = sizeof($tmp);
			unset($tmp);

			return $ret;
		}

		function format_size($value, $decimals, $unit='?') {
			if ($value == '-')
				return 'unknown';

			/* Autodetect unit that's appropriate */
			if ($unit == '?') {
				/* (1 << 40) is not working correctly on i386 systems */
				if ($value >= 1099511627776)
					$unit = 'T';
				else
				if ($value >= (1 << 30))
					$unit = 'G';
				else
				if ($value >= (1 << 20))
					$unit = 'M';
				else
				if ($value >= (1 << 10))
					$unit = 'K';
				else
					$unit = 'B';
			}

			$unit = strtoupper($unit);

			switch ($unit) {
				case 'T': return number_format($value / (float)1099511627776, $decimals).' TB';
				case 'G': return number_format($value / (float)(1 << 30), $decimals).' GB';
				case 'M': return number_format($value / (float)(1 << 20), $decimals).' MB';
				case 'K': return number_format($value / (float)(1 << 10), $decimals).' KB';
				case 'B': return $value.' B';
			}

			return false;
		}

		function get_uri() {
			$tmp = libvirt_connect_get_uri($this->conn);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_domain_count() {
			$tmp = libvirt_domain_get_counts($this->conn);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function translate_volume_type($type) {
			if ($type == 1)
				return 'Block device';

			return 'File image';
		}

		function translate_perms($mode) {
			$mode = (string)((int)$mode);

			$tmp = '---------';

			for ($i = 0; $i < 3; $i++) {
				$bits = (int)$mode[$i];
				if ($bits & 4)
					$tmp[ ($i * 3) ] = 'r';
				if ($bits & 2)
					$tmp[ ($i * 3) + 1 ] = 'w';
				if ($bits & 1)
					$tmp[ ($i * 3) + 2 ] = 'x';
			}


			return $tmp;
		}

		function parse_size($size) {
			$unit = $size[ strlen($size) - 1 ];

			$size = (int)$size;
			switch (strtoupper($unit)) {
				case 'T': $size *= 1099511627776;
					  break;
				case 'G': $size *= 1073741824;
					  break;
				case 'M': $size *= 1048576;
					  break;
				case 'K': $size *= 1024;
					  break;
			}

			return $size;
		}

		//create a storage volume and add file extension
		function volume_create($name, $capacity, $allocation, $format) {
			$capacity = $this->parse_size($capacity);
			$allocation = $this->parse_size($allocation);
			($format != 'raw' ) ? $ext = $format : $ext = 'img';
			($ext == pathinfo($name, PATHINFO_EXTENSION)) ? $ext = '': $name .= '.';

			$xml = "<volume>\n".
					"   <name>$name$ext</name>\n".
					"   <capacity>$capacity</capacity>\n".
					"   <allocation>$allocation</allocation>\n".
					"   <target>\n".
					"      <format type='$format'/>\n".
					"   </target>\n".
					"</volume>";

			$tmp = libvirt_storagevolume_create_xml($pool, $xml);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_hypervisor_name() {
			$tmp = libvirt_connect_get_information($this->conn);
			$hv = $tmp['hypervisor'];
			unset($tmp);

			switch (strtoupper($hv)) {
				case 'QEMU': $type = 'qemu';
					break;

				default:
					$type = $hv;
			}

			return $type;
		}

		function get_connect_information() {
			$tmp = libvirt_connect_get_information($this->conn);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_change_xml($domain, $xml) {
			$dom = $this->get_domain_object($domain);

			if (!($old_xml = domain_get_xml($dom)))
				return $this->_set_last_error();
			if (!libvirt_domain_undefine($dom))
				return $this->_set_last_error();
			if (!libvirt_domain_define_xml($this->conn, $xml)) {
				$this->last_error = libvirt_get_last_error();
				libvirt_domain_define_xml($this->conn, $old_xml);
				return false;
			}

			return true;
		}

		function get_domains() {
			$tmp = libvirt_list_domains($this->conn);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_active_domain_ids() {
			$tmp = libvirt_list_active_domain_ids($this->conn);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_domain_by_name($name) {
			$tmp = libvirt_domain_lookup_by_name($this->conn, $name);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_node_devices($dev = false) {
			$tmp = ($dev == false) ? libvirt_list_nodedevs($this->conn) : libvirt_list_nodedevs($this->conn, $dev);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_node_device_res($res) {
			if ($res == false)
				return false;
			if (is_resource($res))
				return $res;

			$tmp = libvirt_nodedev_get($this->conn, $res);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_node_device_caps($dev) {
			$dev = $this->get_node_device_res($dev);

			$tmp = libvirt_nodedev_capabilities($dev);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_node_device_cap_options() {
			$all = $this->get_node_devices();

			$ret = array();
			for ($i = 0; $i < sizeof($all); $i++) {
				$tmp = $this->get_node_device_caps($all[$i]);

				for ($ii = 0; $ii < sizeof($tmp); $ii++)
					if (!in_array($tmp[$ii], $ret))
						$ret[] = $tmp[$ii];
			}

			return $ret;
		}

		function get_node_device_xml($dev) {
			$dev = $this->get_node_device_res($dev);

			$tmp = libvirt_nodedev_get_xml_desc($dev, NULL);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function get_node_device_information($dev) {
			$dev = $this->get_node_device_res($dev);

			$tmp = libvirt_nodedev_get_information($dev);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_name($res) {
			return libvirt_domain_get_name($res);
		}

		function domain_get_info_call($name = false, $name_override = false) {
			$ret = array();

			if ($name != false) {
				$dom = $this->get_domain_object($name);
				if (!$dom)
					return false;

				if ($name_override)
					$name = $name_override;

				$ret[$name] = libvirt_domain_get_info($dom);
				return $ret;
			}
			else {
				$doms = libvirt_list_domains($this->conn);
				foreach ($doms as $dom) {
					$tmp = $this->domain_get_name($dom);
					$ret[$tmp] = libvirt_domain_get_info($dom);
				}
			}

			ksort($ret);
			return $ret;
		}

		function domain_get_info($name = false, $name_override = false) {
			if (!$name)
				return false;

			if (!$this->allow_cached)
				return $this->domain_get_info_call($name, $name_override);

			$domname = $name_override ? $name_override : $name;
			$dom = $this->get_domain_object($domname);
			$domkey  = $name_override ? $name_override : $this->domain_get_name($dom);
			if (!array_key_exists($domkey, $this->dominfos)) {
				$tmp = $this->domain_get_info_call($name, $name_override);
				$this->dominfos[$domkey] = $tmp[$domname];
			}

			return $this->dominfos[$domkey];
		}

		function get_last_error() {
			return $this->last_error;
		}

		function domain_get_xml($domain, $xpath = NULL) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_get_xml_desc($dom, $xpath);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_id($domain, $name = false) {
			$dom = $this->get_domain_object($domain);
			if ((!$dom) || (!$this->domain_is_running($dom, $name)))
				return false;

			$tmp = libvirt_domain_get_id($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_interface_stats($domain, $iface) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_interface_stats($dom, $iface);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_interface_devices($res) {
			$tmp = libvirt_domain_get_interface_devices($res);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_memory_stats($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_memory_stats($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_start($dom) {
			$dom=$this->get_domain_object($dom);
			if ($dom) {
				$ret = libvirt_domain_create($dom);
				$this->last_error = libvirt_get_last_error();
				return $ret;
			}

			$ret = libvirt_domain_create_xml($this->conn, $dom);
			$this->last_error = libvirt_get_last_error();
			return $ret;
		}

		function domain_define($xml) {
			if (strpos($xml,'<qemu:commandline>')) {
				$tmp = explode("\n", $xml);
				for ($i = 0; $i < sizeof($tmp); $i++)
					if (strpos('.'.$tmp[$i], "<domain type='kvm'"))
						$tmp[$i] = "<domain type='kvm' xmlns:qemu='http://libvirt.org/schemas/domain/qemu/1.0'>";
				$xml = join("\n", $tmp);
			}
			$tmp = libvirt_domain_define_xml($this->conn, $xml);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_destroy($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_destroy($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_reboot($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_reboot($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_suspend($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_suspend($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_save($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_managedsave($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_resume($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_resume($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_uuid($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_get_uuid_string($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_get_domain_by_uuid($uuid) {
			$dom = libvirt_domain_lookup_by_uuid_string($this->conn, $uuid);
			return ($dom) ? $dom : $this->_set_last_error();
		}

		function domain_get_name_by_uuid($uuid) {
			$dom = $this->domain_get_domain_by_uuid($uuid);
			if (!$dom)
				return false;
			$tmp = libvirt_domain_get_name($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_is_active($domain) {
			$domain = $this->get_domain_object($domain);
			$tmp = libvirt_domain_is_active($domain);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function generate_uuid($seed=false) {
			if (!$seed)
				$seed = time();
			srand($seed);

			$ret = array();
			for ($i = 0; $i < 16; $i++)
				$ret[] = $this->macbyte(rand() % 256);

			$a = $ret[0].$ret[1].$ret[2].$ret[3];
			$b = $ret[4].$ret[5];
			$c = $ret[6].$ret[7];
			$d = $ret[8].$ret[9];
			$e = $ret[10].$ret[11].$ret[12].$ret[13].$ret[14].$ret[15];

			return $a.'-'.$b.'-'.$c.'-'.$d.'-'.$e;
		}

		function domain_generate_uuid() {
			$uuid = $this->generate_uuid();

			//while ($this->domain_get_name_by_uuid($uuid))
				//$uuid = $this->generate_uuid();

			return $uuid;
		}

		function domain_shutdown($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_shutdown($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_undefine($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = libvirt_domain_undefine($dom);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

		function domain_delete($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;
			$disks = $this->get_disk_stats($dom);
			$tmp = libvirt_domain_undefine($dom);
			if (!$tmp)
				return $this->_set_last_error();

			// remove the first disk only
			if (array_key_exists('file', $disks[0])) {
				$disk = $disks[0]['file'];
				$pathinfo = pathinfo($disk);
				$dir = $pathinfo['dirname'];

				// remove the vm config
				$cfg_vm = $dir.'/'.$domain.'.cfg';
				if (file_exists($cfg_vm));
					unlink($cfg_vm);

				$cfg = $dir.'/'.$pathinfo['filename'].'.cfg';
				$xml = $dir.'/'.$pathinfo['filename'].'.xml';
					if (file_exists($disk));
						unlink($disk);
					if (file_exists($cfg));
						unlink($cfg);
					if (file_exists($xml));
						unlink($xml);
					if (is_dir($dir) && $this->is_dir_empty($dir))
						rmdir($dir);
			}

			return true;
		}

		function is_dir_empty($dir) {
			if (!is_readable($dir)) return NULL;
			  $handle = opendir($dir);
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != "..") {
					return FALSE;
				}
			}
			return TRUE;
		}

		function domain_is_running($domain, $name = false) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$tmp = $this->domain_get_info( $domain, $name );
			if (!$tmp)
				return $this->_set_last_error();
			$ret = ( ($tmp['state'] == VIR_DOMAIN_RUNNING) || ($tmp['state'] == VIR_DOMAIN_BLOCKED) );
			unset($tmp);
			return $ret;
		}

		function domain_get_state($domain) {
			$dom = $this->get_domain_object($domain);
			if (!$dom)
				return false;

			$info = libvirt_domain_get_info($dom);
			if (!$info)
				return $this->_set_last_error();

			return $this->domain_state_translate($info['state']);
		}

		function domain_state_translate($state) {
			switch ($state) {
				case VIR_DOMAIN_RUNNING:  return 'running';
				case VIR_DOMAIN_NOSTATE:  return 'nostate';
				case VIR_DOMAIN_BLOCKED:  return 'blocked';
				case VIR_DOMAIN_PAUSED:   return 'paused';
				case VIR_DOMAIN_SHUTDOWN: return 'shutdown';
				case VIR_DOMAIN_SHUTOFF:  return 'shutoff';
				case VIR_DOMAIN_CRASHED:  return 'crashed';
				//VIR_DOMAIN_PMSUSPENDED is 7 (not defined in libvirt-php yet)
				case 7:  return 'pmsuspended';
			}

			return 'unknown';
		}

		function domain_get_vnc_port($domain) {
			$tmp = $this->get_xpath($domain, '//domain/devices/graphics/@port', false);
			$var = (int)$tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_vnc_keymap($domain) {
			$tmp = $this->get_xpath($domain, '//domain/devices/graphics/@keymap', false);
			if (!$tmp)
				return 'en-us';

			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_ws_port($domain) {
			$tmp = $this->get_xpath($domain, '//domain/devices/graphics/@websocket', false);
			$var = (int)$tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_arch($domain) {
			$tmp = $this->get_xpath($domain, '//domain/os/type/@arch', false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_machine($domain) {
			$tmp = $this->get_xpath($domain, '//domain/os/type/@machine', false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_description($domain) {
			$tmp = $this->get_xpath($domain, '//domain/description', false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_clock_offset($domain) {
			$tmp = $this->get_xpath($domain, '//domain/clock/@offset', false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_cpu_type($domain) {
			$tmp = $this->get_xpath($domain, '//domain/cpu/@mode', false);
			if (!$tmp)
				return 'emulated';

			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_vcpu($domain) {
			$tmp = $this->get_xpath($domain, '//domain/vcpu', false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_vcpu_pins($domain) {
			$tmp = $this->get_xpath($domain, '//domain/cputune/vcpupin/@cpuset', false);
			if (!$tmp)
				return false;

			$devs = array();
			for ($i = 0; $i < $tmp['num']; $i++)
				$devs[] = $tmp[$i];

			return $devs;
		}

		function domain_get_memory($domain) {
			$tmp = $this->get_xpath($domain, '//domain/memory', false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_current_memory($domain) {
			$tmp = $this->get_xpath($domain, '//domain/currentMemory', false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

		function domain_get_feature($domain, $feature) {
			$tmp = $this->get_xpath($domain, '//domain/features/'.$feature.'/..', false);
			$ret = ($tmp != false);
			unset($tmp);

			return $ret;
		}

		function domain_get_boot_devices($domain) {
			$tmp = $this->get_xpath($domain, '//domain/os/boot/@dev', false);
			if (!$tmp)
				return false;

			$devs = array();
			for ($i = 0; $i < $tmp['num']; $i++)
				$devs[] = $tmp[$i];

			return $devs;
		}

		function domain_get_mount_filesystems($domain) {
			$xpath = '//domain/devices/filesystem[@type="mount"]';

			$sources = $this->get_xpath($domain, $xpath.'/source/@dir', false);
			$targets = $this->get_xpath($domain, $xpath.'/target/@dir', false);

			$ret = array();
			if (!empty($sources)) {
				for ($i = 0; $i < $sources['num']; $i++) {
					$ret[] = array(
						'source' => $sources[$i],
						'target' => $targets[$i]
					);
				}
			}

			return $ret;
		}

		function _get_single_xpath_result($domain, $xpath) {
			$tmp = $this->get_xpath($domain, $xpath, false);
			if (!$tmp)
				return false;

			if ($tmp['num'] == 0)
				return false;

			return $tmp[0];
		}

		function domain_get_ovmf($domain) {
			return $this->_get_single_xpath_result($domain, '//domain/os/loader');
		}

		function domain_get_multimedia_device($domain, $type, $display=false) {
			$domain = $this->get_domain_object($domain);

			if ($type == 'console') {
				$type = $this->_get_single_xpath_result($domain, '//domain/devices/console/@type');
				$targetType = $this->_get_single_xpath_result($domain, '//domain/devices/console/target/@type');
				$targetPort = $this->_get_single_xpath_result($domain, '//domain/devices/console/target/@port');

				if ($display)
					return $type.' ('.$targetType.' on port '.$targetPort.')';
				else
					return array('type' => $type, 'targetType' => $targetType, 'targetPort' => $targetPort);
			}
			else
			if ($type == 'input') {
				$type = $this->_get_single_xpath_result($domain, '//domain/devices/input/@type');
				$bus  = $this->_get_single_xpath_result($domain, '//domain/devices/input/@bus');

				if ($display)
					return $type.' on '.$bus;
				else
					return array('type' => $type, 'bus' => $bus);
			}
			else
			if ($type == 'graphics') {
				$type = $this->_get_single_xpath_result($domain, '//domain/devices/graphics/@type');
				$port = $this->_get_single_xpath_result($domain, '//domain/devices/graphics/@port');
				$autoport = $this->_get_single_xpath_result($domain, '//domain/devices/graphics/@autoport');

				if ($display)
					return $type.' on port '.$port.' with'.($autoport ? '' : 'out').' autoport enabled';
				else
					return array('type' => $type, 'port' => $port, 'autoport' => $autoport);
			}
			else
			if ($type == 'video') {
				$type  = $this->_get_single_xpath_result($domain, '//domain/devices/video/model/@type');
				$vram  = $this->_get_single_xpath_result($domain, '//domain/devices/video/model/@vram');
				$heads = $this->_get_single_xpath_result($domain, '//domain/devices/video/model/@heads');

				if ($display)
					return $type.' with '.($vram / 1024).' MB VRAM, '.$heads.' head(s)';
				else
					return array('type' => $type, 'vram' => $vram, 'heads' => $heads);
			}
			else
				return false;
		}

		function domain_get_host_devices_pci($domain) {
			$xpath = '//domain/devices/hostdev[@type="pci"]/source/address/@';

			$dom  = $this->get_xpath($domain, $xpath.'domain', false);
			$bus  = $this->get_xpath($domain, $xpath.'bus', false);
			$slot = $this->get_xpath($domain, $xpath.'slot', false);
			$func = $this->get_xpath($domain, $xpath.'function', false);

			$devs = array();
			for ($i = 0; $i < $bus['num']; $i++) {
				$devid = str_replace('0x', '', 'pci_'.$dom[$i].'_'.$bus[$i].'_'.$slot[$i].'_'.$func[$i]);
				$tmp2 = $this->get_node_device_information($devid);
				$devs[] = array(
					'domain' => $dom[$i],
					'bus' => $bus[$i],
					'slot' => $slot[$i],
					'func' => $func[$i],
					'id' => str_replace('0x', '', $bus[$i].':'.$slot[$i].'.'.$func[$i]),
					'vendor' => $tmp2['vendor_name'],
					'vendor_id' => $tmp2['vendor_id'],
					'product' => $tmp2['product_name'],
					'product_id' => $tmp2['product_id']
				);
			}

			// Get any pci devices contained in the qemu args
			$args = $this->get_xpath($domain, '//domain/*[name()=\'qemu:commandline\']/*[name()=\'qemu:arg\']/@value', false);

			for ($i = 0; $i < $args['num']; $i++) {
				if (strpos($args[$i], 'vfio-pci') !== 0) {
					continue;
				}

				$arg_list = explode(',', $args[$i]);

				foreach ($arg_list as $arg) {
					$keypair = explode('=', $arg);

					if ($keypair[0] == 'host' && !empty($keypair[1])) {
						$devid = 'pci_0000_' . str_replace(array(':', '.'), '_', $keypair[1]);
						$tmp2 = $this->get_node_device_information($devid);
						list($bus, $slot, $func) = explode(":", str_replace('.', ':', $keypair[1]));
						$devs[] = array(
							'domain' => '0x0000',
							'bus' => '0x' . $bus,
							'slot' => '0x' . $slot,
							'func' => '0x' . $func,
							'id' => $keypair[1],
							'vendor' => $tmp2['vendor_name'],
							'vendor_id' => $tmp2['vendor_id'],
							'product' => $tmp2['product_name'],
							'product_id' => $tmp2['product_id']
						);
						break;
					}
				}
			}

			return $devs;
		}

		function _lookup_device_usb($vendor_id, $product_id) {
			$tmp = $this->get_node_devices(false);
			for ($i = 0; $i < sizeof($tmp); $i++) {
				$tmp2 = $this->get_node_device_information($tmp[$i]);
				if (array_key_exists('product_id', $tmp2)) {
					if (($tmp2['product_id'] == $product_id)
						&& ($tmp2['vendor_id'] == $vendor_id))
							return $tmp2;
				}
			}

			return false;
		}

		function domain_get_host_devices_usb($domain) {
			$xpath = '//domain/devices/hostdev[@type="usb"]/source/';

			$vid = $this->get_xpath($domain, $xpath.'vendor/@id', false);
			$pid = $this->get_xpath($domain, $xpath.'product/@id', false);

			$devs = array();
			for ($i = 0; $i < $vid['num']; $i++) {
				$dev = $this->_lookup_device_usb($vid[$i], $pid[$i]);
				$devs[] = array(
					'id' => str_replace('0x', '', $vid[$i] . ':' . $pid[$i]),
					'vendor_id' => $vid[$i],
					'product_id' => $pid[$i],
					'product' => $dev['product_name'],
					'vendor' => $dev['vendor_name']
				);
			}

			return $devs;
		}

		function domain_get_host_devices($domain) {
			$domain = $this->get_domain_object($domain);

			$devs_pci = $this->domain_get_host_devices_pci($domain);
			$devs_usb = $this->domain_get_host_devices_usb($domain);

			return array('pci' => $devs_pci, 'usb' => $devs_usb);
		}

		function get_nic_info($domain) {
			$macs = $this->get_xpath($domain, "//domain/devices/interface/mac/@address", false);
			$net = $this->get_xpath($domain, "//domain/devices/interface/@type", false);
			$bridge = $this->get_xpath($domain, "//domain/devices/interface/source/@bridge", false);
			if (!$macs)
				return $this->_set_last_error();
			$ret = array();
			for ($i = 0; $i < $macs['num']; $i++) {
				if ($net[$i] != 'bridge')
					$tmp = libvirt_domain_get_network_info($domain, $macs[$i]);
				if ($tmp)
					$ret[] = $tmp;
				else {
					$this->_set_last_error();
					$ret[] = array(
						'mac' => $macs[$i],
						'network' => $bridge[$i],
						'nic_type' => 'virtio'
					);
				}
			}

			return $ret;
		}

		function domain_set_feature($domain, $feature, $val) {
			$domain = $this->get_domain_object($domain);

			if ($this->domain_get_feature($domain, $feature) == $val)
				return true;

			$xml = $this->domain_get_xml($domain);
			if ($val) {
				if (strpos('features', $xml))
					$xml = str_replace('<features>', "<features>\n<$feature/>", $xml);
				else
					$xml = str_replace('</os>', "</os><features>\n<$feature/></features>", $xml);
			}
			else
				$xml = str_replace("<$feature/>\n", '', $xml);

			return $this->domain_define($xml);
		}

		function domain_set_clock_offset($domain, $offset) {
			$domain = $this->get_domain_object($domain);

			if (($old_offset = $this->domain_get_clock_offset($domain)) == $offset)
				return true;

			$xml = $this->domain_get_xml($domain);
			$xml = str_replace("<clock offset='$old_offset'/>", "<clock offset='$offset'/>", $xml);

			return $this->domain_define($xml);
		}

//change vpus for domain
		function domain_set_vcpu($domain, $vcpu) {
			$domain = $this->get_domain_object($domain);

			if (($old_vcpu = $this->domain_get_vcpu($domain)) == $vcpu)
				return true;

			$xml = $this->domain_get_xml($domain);
			$xml = str_replace("$old_vcpu</vcpu>", "$vcpu</vcpu>", $xml);

			return $this->domain_define($xml);
		}

//change memory for domain
		function domain_set_memory($domain, $memory) {
			$domain = $this->get_domain_object($domain);
			if (($old_memory = $this->domain_get_memory($domain)) == $memory)
				return true;

			$xml = $this->domain_get_xml($domain);
			$xml = str_replace("$old_memory</memory>", "$memory</memory>", $xml);

			return $this->domain_define($xml);
		}

//change memory for domain
		function domain_set_current_memory($domain, $memory) {
			$domain = $this->get_domain_object($domain);
			if (($old_memory = $this->domain_get_current_memory($domain)) == $memory)
				return true;

			$xml = $this->domain_get_xml($domain);
			$xml = str_replace("$old_memory</currentMemory>", "$memory</currentMemory>", $xml);

			return $this->domain_define($xml);
		}

//change domain disk dev name
		function domain_set_disk_dev($domain, $olddev, $dev) {
			$domain = $this->get_domain_object($domain);

			$xml = $this->domain_get_xml($domain);
			$tmp = explode("\n", $xml);
			for ($i = 0; $i < sizeof($tmp); $i++)
				if (strpos('.'.$tmp[$i], "<target dev='".$olddev))
					$tmp[$i] = str_replace("<target dev='".$olddev, "<target dev='".$dev, $tmp[$i]);

			$xml = join("\n", $tmp);

			return $this->domain_define($xml);
		}

//set domain description
		function domain_set_description($domain, $desc) {
			$domain = $this->get_domain_object($domain);

			$description = $this->domain_get_description($domain);
			if ($description == $desc)
				return true;

			$xml = $this->domain_get_xml($domain);
			if (!$description)
				$xml = str_replace("</uuid>", "</uuid><description>$desc</description>", $xml);
			else {
				$tmp = explode("\n", $xml);
				for ($i = 0; $i < sizeof($tmp); $i++)
					if (strpos('.'.$tmp[$i], '<description'))
						$tmp[$i] = "<description>$desc</description>";

				$xml = join("\n", $tmp);
			}

			return $this->domain_define($xml);
		}

//create metadata node for domain
		function domain_set_metadata($domain) {
			$domain = $this->get_domain_object($domain);

			$xml = $this->domain_get_xml($domain);
			$metadata = $this->get_xpath($domain, '//domain/metadata', false);
			if (empty($metadata)){
				$description = $this->domain_get_description($domain);
				if(!$description)
					$node = "</uuid>";
				else
					$node = "</description>";
				$desc = "$node\n<metadata>\n<snapshots/>\n</metadata>";
				$xml = str_replace($node, $desc, $xml);
			}
			return $this->domain_define($xml);
		}

//set description for snapshot
		function snapshot_set_metadata($domain, $name, $desc) {
			$this->domain_set_metadata($domain);
			$domain = $this->get_domain_object($domain);

			$xml = $this->domain_get_xml($domain);
			$metadata = $this->get_xpath($domain, '//domain/metadata/snapshot'.$name, false);
			if (empty($metadata)){
				$desc = "<metadata>\n<snapshot$name>$desc</snapshot$name>\n";
				$xml = str_replace('<metadata>', $desc, $xml);
			} else {
				$tmp = explode("\n", $xml);
				for ($i = 0; $i < sizeof($tmp); $i++)
					if (strpos('.'.$tmp[$i], '<snapshot'.$name))
						$tmp[$i] = "<snapshot$name>$desc</snapshot$name>";

				$xml = join("\n", $tmp);
			}
			return $this->domain_define($xml);
		}

//get host node info
		function host_get_node_info() {
			$tmp = libvirt_node_get_info($this->conn);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//get domain autostart status true or false
		function domain_get_autostart($domain) {
			$domain = $this->get_domain_object($domain);
			$tmp = libvirt_domain_get_autostart($domain);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//set domain to start with libvirt
		function domain_set_autostart($domain,$flags) {
			$domain = $this->get_domain_object($domain);
			$tmp = libvirt_domain_set_autostart($domain,$flags);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//list all snapshots for domain
		function domain_snapshots_list($domain) {
			$tmp = libvirt_list_domain_snapshots($domain);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

// create a snapshot and metadata node for description
		function domain_snapshot_create($domain) {
			$this->domain_set_metadata($domain);
			$domain = $this->get_domain_object($domain);
			$tmp = libvirt_domain_snapshot_create($domain);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//delete snapshot and metadata
		function domain_snapshot_delete($domain, $name) {
			$this->snapshot_remove_metadata($domain, $name);
			$name = $this->domain_snapshot_lookup_by_name($domain, $name);
			$tmp = libvirt_domain_snapshot_delete($name);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//get resource number of snapshot
		function domain_snapshot_lookup_by_name($domain, $name) {
			$domain = $this->get_domain_object($domain);
			$tmp = libvirt_domain_snapshot_lookup_by_name($domain, $name);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//revert domain to snapshot state
		function domain_snapshot_revert($domain, $name) {
			$name = $this->domain_snapshot_lookup_by_name($domain, $name);
			$tmp = libvirt_domain_snapshot_revert($name);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//get snapshot description
		function domain_snapshot_get_info($domain, $name) {
			$domain = $this->get_domain_object($domain);
			$tmp = $this->get_xpath($domain, '//domain/metadata/snapshot'.$name, false);
			$var = $tmp[0];
			unset($tmp);

			return $var;
		}

//remove snapshot metadata
		function snapshot_remove_metadata($domain, $name) {
			$domain = $this->get_domain_object($domain);

			$xml = $this->domain_get_xml($domain);
			$tmp = explode("\n", $xml);
			for ($i = 0; $i < sizeof($tmp); $i++)
				if (strpos('.'.$tmp[$i], '<snapshot'.$name))
					$tmp[$i] = null;

			$xml = join("\n", $tmp);

			return $this->domain_define($xml);
		}

//change cdrom media
		function domain_change_cdrom($domain, $iso, $dev, $bus) {
			$domain = $this->get_domain_object($domain);
			$tmp = libvirt_domain_update_device($domain, "<disk type='file' device='cdrom'><driver name='qemu' type='raw'/><source file=".escapeshellarg($iso)."/><target dev='$dev' bus='$bus'/><readonly/></disk>", VIR_DOMAIN_DEVICE_MODIFY_CONFIG);
			if ($this->domain_is_active($domain))
				libvirt_domain_update_device($domain, "<disk type='file' device='cdrom'><driver name='qemu' type='raw'/><source file=".escapeshellarg($iso)."/><target dev='$dev' bus='$bus'/><readonly/></disk>", VIR_DOMAIN_DEVICE_MODIFY_LIVE);
			return ($tmp) ? $tmp : $this->_set_last_error();
		}

//change disk capacity
		function disk_set_cap($disk, $cap) {
			$xml = $this->domain_get_xml($domain);
			$tmp = explode("\n", $xml);
			for ($i = 0; $i < sizeof($tmp); $i++)
				if (strpos('.'.$tmp[$i], "<target dev='".$olddev))
					$tmp[$i] = str_replace("<target dev='".$olddev, "<target dev='".$dev, $tmp[$i]);

			$xml = join("\n", $tmp);

			return $this->domain_define($xml);
		}

//change domain boot device
		function domain_set_boot_device($domain, $bootdev) {
			$xml = $this->domain_get_xml($domain);
			$tmp = explode("\n", $xml);
			for ($i = 0; $i < sizeof($tmp); $i++)
				if (strpos('.'.$tmp[$i], "<boot dev="))
					$tmp[$i] = "<boot dev='$bootdev'/>";

			$xml = join("\n", $tmp);

			return $this->domain_define($xml);
		}
	}
?>