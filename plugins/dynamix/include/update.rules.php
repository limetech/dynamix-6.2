<?PHP
/* Copyright 2016, Lime Technology
 * Copyright 2016, Bergware International.
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
$cfg = $_POST['#cfg'];
foreach ($_POST as $name => $mac) {
  if ($name[0]=='#') continue;
  $row = exec("grep -n '$mac' $cfg|cut -d: -f1");
  if ($row) exec("sed -ri '{$row}s/(NAME=\")[^\"]+/\\1{$name}/' $cfg");
}
exec("touch /tmp/network-rules.tmp");
$save = false;
?>