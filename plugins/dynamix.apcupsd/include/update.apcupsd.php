<?PHP
/* Copyright 2015, Dan Landon.
 * Copyright 2015, Bergware International.
 * Copyright 2015, Lime Technology
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
$conf  = "/etc/apcupsd/apcupsd.conf";
$new   = array_replace_recursive($_POST, $default);
$cable = $new['UPSCABLE']=='custom' ? $new['CUSTOMUPSCABLE'] : $new['UPSCABLE'];

exec("/etc/rc.d/rc.apcupsd stop");
exec("sed -i -e '/^NISIP/c\\NISIP 0.0.0.0' $conf");
exec("sed -i -e '/^UPSTYPE/c\\UPSTYPE '{$new['UPSTYPE']}'' $conf");
exec("sed -i -e '/^DEVICE/c\\DEVICE '{$new['DEVICE']}'' $conf");
exec("sed -i -e '/^BATTERYLEVEL/c\\BATTERYLEVEL '{$new['BATTERYLEVEL']}'' $conf");
exec("sed -i -e '/^MINUTES/c\\MINUTES '{$new['MINUTES']}'' $conf");
exec("sed -i -e '/^TIMEOUT/c\\TIMEOUT '{$new['TIMEOUT']}'' $conf");
exec("sed -i -e '/^UPSCABLE/c\\UPSCABLE '{$cable}'' $conf");

if ($new['KILLUPS']=='yes' && $new['SERVICE']=='enable')
  exec("! grep -q apccontrol /etc/rc.d/rc.6 && sed -i -e 's:/sbin/poweroff:/etc/apcupsd/apccontrol killpower; /sbin/poweroff:' /etc/rc.d/rc.6");
else
  exec("grep -q apccontrol /etc/rc.d/rc.6 && sed -i -e 's:/etc/apcupsd/apccontrol killpower; /sbin/poweroff:/sbin/poweroff:' /etc/rc.d/rc.6");

if ($new['SERVICE']=='enable') exec("/etc/rc.d/rc.apcupsd start");
?>
