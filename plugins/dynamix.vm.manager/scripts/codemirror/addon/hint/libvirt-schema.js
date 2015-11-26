function getLibvirtSchema() {

	var root = {};

	root.domain = {
		"!attrs": {
			type: ["kvm"],
			"xmlns:qemu": ["http://libvirt.org/schemas/domain/qemu/1.0"]
		}
	};

	root.domain.name = {
		"!value": ""
	};

	root.domain.description = {
		"!value": ""
	};

	root.domain.maxMemory = {
		"!attrs": {
			slots: null,
			unit: ["MiB", "KiB", "GiB"]
		},
		"!value": 512
	};

	root.domain.memory = {
		"!attrs": {
			unit: ["MiB", "KiB", "GiB"]
		},
		"!value": 512
	};

	root.domain.currentMemory = {
		"!attrs": {
			unit: ["MiB", "KiB", "GiB"]
		},
		"!value": 512
	};

	root.domain.memoryBacking = {};
	root.domain.memoryBacking.hugepages = {};
	root.domain.memoryBacking.hugepages.page = {
		"!attrs": {
			size: [2, 1],
			unit: ["M", "G", "K"],
			nodeset: null
		},
		"!novalue": 1
	};
	root.domain.memoryBacking.nosharepages = {
		"!novalue": 1
	};
	root.domain.memoryBacking.locked = {
		"!novalue": 1
	};

	root.domain.numatune = {};
	root.domain.numatune.memory = {
		"!attrs": {
			mode: ["strict", "preferred", "interleave"],
			nodeset: null,
			placement: ["static", "auto"]
		},
		"!novalue": 1
	};
	root.domain.numatune.memnode = {
		"!attrs": {
			cellid: null,
			mode: ["strict", "preferred", "interleave"],
			nodeset: null
		},
		"!novalue": 1
	};

	root.domain.vcpu = {
		"!attrs": {
			placement: ["static"]
		},
		"!value": 1
	};

	root.domain.cputune = {};
	root.domain.cputune.vcpupin = {
		"!attrs": {
			vcpu: null,
			cpuset: null
		},
		"!novalue": 1
	};
	root.domain.cputune.emulatorpin = {
		"!attrs": {
			cpuset: null
		},
		"!novalue": 1
	};
	root.domain.cputune.iothreadpin = {
		"!attrs": {
			iothread: null,
			cpuset: null
		},
		"!novalue": 1
	};
	root.domain.cputune.shares = {
		"!value": null
	};
	root.domain.cputune.period = {
		"!value": null
	};
	root.domain.cputune.quota = {
		"!value": null
	};
	root.domain.cputune.emulator_period = {
		"!value": null
	};
	root.domain.cputune.emulator_quota = {
		"!value": null
	};
	root.domain.cputune.vcpusched = {
		"!attrs": {
			vcpus: null,
			scheduler: ["batch", "idle", "fifo", "rr"],
			priority: null
		},
		"!novalue": 1
	};
	root.domain.cputune.iothreadsched = {
		"!attrs": {
			iothreads: null,
			scheduler: ["batch", "idle", "fifo", "rr"]
		},
		"!novalue": 1
	};

	root.domain.cpu = {
		"!attrs": {
			match: ["exact", "minimum", "strict"],
			mode: ["host-passthrough", "host-model", "custom"]
		}
	};
	root.domain.cpu.model = {
		"!value": ""
	};
	root.domain.cpu.topology = {
		"!attrs": {
			sockets: null,
			cores: null,
			threads: null
		}
	};

	root.domain.os = {};
	root.domain.os.type = {
		"!attrs": {
			arch: ["x86_64"],
			machine: ["pc", "q35"]
		},
		"!value": "hvm"
	};
	root.domain.os.loader = {
		"!attrs": {
			readonly: ["yes"],
			type: ["pflash"]
		},
		"!value": "/usr/share/qemu/ovmf-x64/OVMF_CODE-pure-efi.fd"
	};
	root.domain.os.nvram = {
		"!value": "/etc/libvirt/qemu/nvram/{{UUID}}_VARS-pure-efi.fd"
	};

	root.domain.features = {};
	root.domain.features.acpi = {
		"!novalue": 1
	};
	root.domain.features.apic = {
		"!novalue": 1
	};
	root.domain.features.hyperv = {};
	root.domain.features.hyperv.relaxed = {
		"!attrs": {
			state: ["on", "off"]
		}
	};
	root.domain.features.hyperv.vapic = {
		"!attrs": {
			state: ["on", "off"]
		}
	};
	root.domain.features.hyperv.spinlocks = {
		"!attrs": {
			state: ["on", "off"],
			retries: null
		}
	};
	root.domain.features.pae = {
		"!novalue": 1
	};

	root.domain.clock = {
		"!attrs": {
			offset: ["localtime", "utc"]
		}
	};
	root.domain.clock.timer = {
		"!attrs": {
			name: ["hypervclock", "hpet", "rtc", "pit"],
			tickpolicy: ["catchup", "delay"],
			present: ["no", "yes"]
		}
	};

	root.domain.on_poweroff = {
		"!value": "destroy"
	};

	root.domain.on_reboot = {
		"!value": "restart"
	};

	root.domain.on_crash = {
		"!value": "destroy"
	};

	root.domain.devices = {};

	root.domain.devices.emulator = {
		"!value": "/usr/bin/qemu-system-x86_64"
	};

	root.domain.devices.disk = {
		"!attrs": {
			type: ["file"],
			device: ["disk", "cdrom"]
		}
	};
	root.domain.devices.disk.driver = {
		"!attrs": {
			name: ["qemu"],
			type: ["raw", "bochs", "qcow2", "qed"],
			error_policy: ["report", "stop", "ignore", "enospace"],
			rerror_policy: ["report", "stop", "ignore"],
			cache: ["writeback", "default", "directsync", "none", "writethrough", "unsafe"],
			io: ["native", "threads"],
			ioeventfd: ["on", "off"],
			event_idx: ["on", "off"],
			copy_on_read: ["off", "on"],
			discard: ["ignore", "unmap"],
			iothread: null
		}
	};
	root.domain.devices.disk.source = {
		"!attrs": {
			file: null
		}
	};
	root.domain.devices.disk.backingStore = {
		"!novalue": 1
	};
	root.domain.devices.disk.target = {
		"!attrs": {
			dev: null,
			bus: ["ide", "sata", "scsi", "virtio", "usb"]
		}
	};
	root.domain.devices.disk.readonly = {
		"!novalue": 1
	};
	root.domain.devices.disk.boot = {
		"!attrs": {
			order: null
		}
	};

	root.domain.devices.interface = {
		"!attrs": {
			type: ["bridge"]
		}
	};
	root.domain.devices.interface.mac = {
		"!attrs": {
			address: null
		}
	};
	root.domain.devices.interface.source = {
		"!attrs": {
			bridge: null
		}
	};
	root.domain.devices.interface.model = {
		"!attrs": {
			type: ["virtio"]
		}
	};

	root.domain.devices.input = {
		"!attrs": {
			type: ["tablet", "mouse", "keyboard"],
			bus: ["usb", "ps2"]
		}
	};

	root.domain.devices.graphics = {
		"!attrs": {
			type: ["vnc"],
			port: ["-1"],
			autoport: ["yes", "no"],
			websocket: ["-1"],
			listen: ["0.0.0.0"],
			keymap: ["en-us", "en-gb", "ar", "hr", "cz", "da", "nl", "nl-be", "es", "et", "fo",
					"fi", "fr", "bepo", "fr-be", "fr-ca", "fr-ch", "de-ch", "hu", "is", "it",
					"ja", "lv", "lt", "mk", "no", "pl", "pt-br", "ru", "sl", "sv", "th", "tr"]
		}
	};

	root.domain.devices.graphics.listen = {
		"!attrs": {
			type: ["address"],
			address: ["0.0.0.0"]
		}
	};

	root.domain.devices.hostdev = {
		"!attrs": {
			mode: ["subsystem"],
			type: ["pci", "usb"],
			managed: ["yes", "no"]
		}
	};
	root.domain.devices.hostdev.driver = {
		"!attrs": {
			name: ["vfio"]
		}
	};
	root.domain.devices.hostdev.source = {};
	root.domain.devices.hostdev.source.address = {
		"!attrs": {
			domain: null,
			bus: null,
			slot: null,
			function: null
		}
	};
	root.domain.devices.hostdev.source.vendor = {
		"!attrs": {
			id: null
		}
	};
	root.domain.devices.hostdev.source.product = {
		"!attrs": {
			id: null
		}
	};

	root.domain.devices.memballoon = {
		"!attrs": {
			model: ["virtio"]
		}
	};
	root.domain.devices.memballoon.alias = {
		"!attrs": {
			name: ["balloon0"]
		}
	};

	root.domain['qemu:commandline'] = {};
	root.domain['qemu:commandline']['qemu:arg'] = {
		"!attrs": {
			value: null
		}
	};


	return root;

}