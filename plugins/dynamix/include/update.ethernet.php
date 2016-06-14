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
if ($_POST['#arg'][1] != 'none') {
  if ($_POST['BONDING']=='yes') {
    $nics = explode(',',str_replace('eth0','',$_POST['BONDNICS']));
    foreach ($nics as $nic) if ($nic) unset($keys[$nic]);
  }
  if ($_POST['BRIDGING']=='yes') {
    $nics = explode(',',str_replace('eth0','',$_POST['BRNICS']));
    foreach ($nics as $nic) if ($nic) unset($keys[$nic]);
  }
  unset($keys[$_POST['#section']]);
}
?>