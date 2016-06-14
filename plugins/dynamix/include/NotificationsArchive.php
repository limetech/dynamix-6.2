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
require_once 'Wrappers.php';

$dynamix = parse_plugin_cfg('dynamix',true);
$files = glob("{$dynamix['notify']['path']}/archive/*.notify", GLOB_NOSORT);
usort($files, create_function('$a,$b', 'return filemtime($b)-filemtime($a);'));

$row = 1;
foreach ($files as $file) {
  $fields = explode(PHP_EOL, file_get_contents($file));
  $archive = basename($file);
  if ($extra = count($fields)>6) {
    $td_ = "<td rowspan='3'><a href='#' onclick='openClose($row)'>"; $_td = "<i class='fa fa-anchor'></i></a></td>";
  } else {
    $td_ = "<td>"; $_td = "</td>";
  }
  $c = 0;
  foreach ($fields as $field) {
    if ($c==5) break;
    $item = $field ? explode('=', $field, 2) : array("","-");
    echo (!$c++) ? "<tr>$td_".date("{$dynamix['notify']['date']} {$dynamix['notify']['time']}", $item[1])."$_td" : "<td>{$item[1]}</td>";
  }
  echo "<td style='text-align:right'><a href='#' onclick='$.post(\"/webGui/include/DeleteLogFile.php\",{log:\"$archive\"},function(){archiveList();});return false' title='Delete notification'><i class='fa fa-trash-o'></i></a></td></tr>";
  if ($extra) {
    $item = explode('=', $field, 2);
    echo "<tr class='expand-child row$row'><td colspan='5'>{$item[1]}</td></tr><tr class='expand-child row$row'><td colspan='5'></td></tr>";
    $row++;
  }
}
if (empty($files)) echo "<tr><td colspan='6' style='text-align:center'><em>No notifications available</em></td></tr>";
?>
