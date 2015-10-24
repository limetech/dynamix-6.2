<?PHP
/* Copyright 2015, Bergware International.
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
$var = parse_ini_file("state/var.ini");

switch ($var['fsState']) {
case 'Stopped':
  echo '<span class="red"><strong>Array Stopped</strong></span>'; break;
case 'Starting':
  echo '<span class="orange"><strong>Array Starting</strong></span>'; break;
default:
  echo '<span class="green"><strong>Array Started</strong></span>'; break;
}
if ($var['mdResync']) {
  echo '&bullet;<span class="orange"><strong>'.($var['mdNumInvalid']==0 ? 'Parity-Check:' : ($var['mdInvalidDisk']==0 ? 'Parity-Sync:' : 'Data-Rebuild:')).' '.number_format(($var['mdResyncPos']/($var['mdResync']/100+1)),1,$_POST['dot'],'').' %</strong></span>';
  if ($_POST['mode']<0) echo '#stop';
}
?>