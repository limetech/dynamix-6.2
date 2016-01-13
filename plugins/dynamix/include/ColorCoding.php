<?PHP
/* Copyright 2015, Lime Technology
 * Copyright 2015, Bergware International.
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
// Color coding for syslog and disk log
$match =
[['class' => 'text',
  'text'  => ['to the standard error','non[ -]fatal error','correct gpt errors','error handler\b','(kernel|logger): [|+ #-.]','logger: (naming|log)']
 ],
 ['class' => 'login',
  'text'  => ['(accepted|failed) password','sshd\[\d+\]:']
 ],
 ['class' => 'warn',
  'text'  => ['\b(warning|conflicts|kill|failed|checksum|spurious|replayed|preclear_disk)\b','acpi (error|exception)','\b(soft|hard) resetting ','\<errno=[^0]','host protected area','invalid signature','limiting speed to','duplicate (object|error)\b','power is back','gpt:partition_entry','no floppy controller']
 ],
 ['class' => 'error',
  'text'  => ['\b(error|emask|tainted|killed|fsck\?|parity incorrect|invalid opcode|kernel bug|power failure)\b','\b(dma|ata\d+[.:]) disabled','nobody cared','unknown boot option','write protect is on','call trace','out[ _]of[ _]memory','hpa detected: current \d+']
 ],
 ['class' => 'system',
  'text'  => ['\b(checksumming|controller|driver|version|highmem|lowmem|bogomips)\b','throttling rate','get value of subfeature','[mg]hz processor','cpu\d*: (intel|amd)','kernel: (processors|memory|smp|console):','\bmd: xor using','thermal zone','adding \d+k swap on','kernel command line:','_sse','found.*chip','\b(mouse|speaker|kbd port|aux port|ps\/2|keyboard)\b']
 ],
 ['class' => 'array',
  'text'  => [': (mdcmd|md|super\.dat|unraid system|unregistered|running, size)\b','key detected, registered']
 ]
];
?>