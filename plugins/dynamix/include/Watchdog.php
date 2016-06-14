<?PHP
/* Copyright 2005-2016, Lime Technology
 * Copyright 2015-2016, Bergware International.
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
  $mode = '';
  if (strstr($var['mdResyncAction'],"recon")) {
    $mode = 'Parity-Sync / Data-Rebuild';
  } elseif (strstr($var['mdResyncAction'],"clear")) {
    $mode = 'Clearing';
  } elseif ($var['mdResyncAction']=="check") {
    $mode = 'Read-Check';
  } elseif (strstr($var['mdResyncAction'],"check")) {
    $mode = 'Parity-Check';
  }
  echo '&bullet;<span class="orange"><strong>'.$mode.' '.number_format(($var['mdResyncPos']/($var['mdResync']/100+1)),1,$_POST['dot'],'').' %</strong></span>';
  if ($_POST['mode']<0) echo '#stop';
}
?>