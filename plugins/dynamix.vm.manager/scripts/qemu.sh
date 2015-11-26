#!/bin/bash

eval exec /usr/bin/qemu-system-x86_64 $(/usr/local/emhttp/plugins/dynamix.vm.manager/scripts/qemu.php "$@")