#!/usr/bin/env php
<?php
if (!isset($argv[1])) {
	exit(0);
}

# We simply use this script to replace any arguments containing a user share path (e.g. /mnt/user/domains/) with the real backing disk (e.g. /mnt/disk1/domains/).

# arguments will look something like this:
# -name Windows Server 2012 R2 -S -machine pc-i440fx-2.3,accel=kvm,usb=off,mem-merge=off -cpu host,hv_time,hv_relaxed,hv_vapic,hv_spinlocks=0x1fff -m 4096 -realtime mlock=on -smp 2,sockets=1,cores=2,threads=1 -uuid 5c0a8b66-a09b-63f1-7835-7f538acee978 -no-user-config -nodefaults -chardev socket,id=charmonitor,path=/var/lib/libvirt/qemu/Windows Server 2012 R2.monitor,server,nowait -mon chardev=charmonitor,id=monitor,mode=control -rtc base=localtime -no-hpet -no-shutdown -boot strict=on -device piix3-usb-uhci,id=usb,bus=pci.0,addr=0x1.0x2 -device virtio-serial-pci,id=virtio-serial0,bus=pci.0,addr=0x4 -drive file=/mnt/cache/domains/kvm/win2012r2/win2012r2.qcow2,if=none,id=drive-virtio-disk2,format=qcow2,cache=writeback -device virtio-blk-pci,scsi=off,bus=pci.0,addr=0x5,drive=drive-virtio-disk2,id=virtio-disk2,bootindex=1 -drive file=/mnt/user/isos/virtio-win-0.1.109-1.iso,if=none,id=drive-ide0-0-1,readonly=on,format=raw -device ide-cd,bus=ide.0,unit=1,drive=drive-ide0-0-1,id=ide0-0-1 -netdev tap,fd=22,id=hostnet0,vhost=on,vhostfd=23 -device virtio-net-pci,netdev=hostnet0,id=net0,mac=52:54:00:de:f0:95,bus=pci.0,addr=0x3 -chardev pty,id=charserial0 -device isa-serial,chardev=charserial0,id=serial0 -chardev socket,id=charchannel0,path=/var/lib/libvirt/qemu/channel/target/Windows Server 2012 R2.org.qemu.guest_agent.0,server,nowait -device virtserialport,bus=virtio-serial0.0,nr=1,chardev=charchannel0,id=channel0,name=org.qemu.guest_agent.0 -device usb-tablet,id=input0 -vnc 0.0.0.0:0,websocket=5700 -k en-us -device vmware-svga,id=video0,vgamem_mb=16,bus=pci.0,addr=0x2 -device virtio-balloon-pci,id=balloon0,bus=pci.0,addr=0x6 -msg timestamp=on


function detect_user_share(&$arg) {
	$arg = preg_replace_callback('|(/mnt/user/[^, ]+)|', function($match) {
		if (is_file($match[0])) {
			// resolve the actual disk or cache backing device for this user share path
			$realdisk = trim(shell_exec("getfattr --absolute-names -n user.LOCATIONS ".escapeshellarg($match[0])." 2>/dev/null|grep -Po '^user.LOCATIONS=\"\K[^\\\"]+'"));

			// replace 'user' with say 'cache' or 'disk1' etc
			$replacement = str_replace('/mnt/user/', '/mnt/'.$realdisk.'/', $match[0]);

			if (!empty($realdisk) && is_file($replacement)) {
				// the replacement path (e.g. /mnt/disk1/domains/vmname/vdisk1.img) checks out so use it
				return $replacement;
			}
		}

	    return $match[0];
	}, $arg);
};

array_shift($argv);
array_walk($argv, 'detect_user_share');

$whole_cmd = '';
foreach ($argv as $arg) {
	$whole_cmd .= escapeshellarg($arg).' ';
}

echo trim($whole_cmd);