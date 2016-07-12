<?PHP
/* Copyright 2005-2016, Lime Technology
 * Copyright 2012-2016, Bergware International.
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
function preset($disk) {
  return strpos($_POST['preset'],$disk['type'])!==false;
}

@unlink('/boot/config/smart-one.cfg');
@unlink('/boot/config/smart-all.cfg');
@unlink('/boot/config/plugins/dynamix/monitor.ini');
if ($_POST['preset']) {
  $disks = parse_ini_file('/var/local/emhttp/disks.ini',true);
  $disks = array_filter($disks,'preset');
  $text = '';
  foreach ($disks as $disk => $block) {
    $text .= "[$disk]\n";
    foreach ($block as $key => $value) $text .= "$key=\"$value\"\n";
  }
  file_put_contents('/var/tmp/disks.ini',$text);
}
?>
