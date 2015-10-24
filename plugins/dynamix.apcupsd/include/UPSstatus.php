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
$state = [
  'TRIM ONLINE'  => 'Online (trim)',
  'BOOST ONLINE' => 'Online (boost)',
  'ONLINE'       => 'Online',
  'ONBATT'       => 'On battery',
  'COMMLOST'     => 'Lost communication',
  'NOBATT'       => 'No battery detected'
];

$red    = "class='red-text'";
$green  = "class='green-text'";
$orange = "class='orange-text'";
$status = array_fill(0,6,"<td>-</td>");
$all    = $_GET['all']=='true';
$result = array();

if (file_exists("/var/run/apcupsd.pid")) {
  exec("/sbin/apcaccess 2>/dev/null", $rows);
  for ($i=0; $i<count($rows); $i++) {
    $row = array_map('trim', explode(':', $rows[$i], 2));
    $key = $row[0];
    $val = strtr($row[1], $state);
    switch ($key) {
    case 'STATUS':
      $status[0] = $val ? (stripos($val,'online')===false ? "<td $red>$val</td>" : "<td $green>$val</td>") : "<td $orange>Refreshing...</td>";
      break;
    case 'BCHARGE':
      $status[1] = strtok($val,' ')<=10 ? "<td $red>$val</td>" : "<td $green>$val</td>";
      break;
    case 'TIMELEFT':
      $status[2] = strtok($val,' ')<=5 ? "<td $red>$val</td>" : "<td $green>$val</td>";
      break;
    case 'NOMPOWER':
      $power = strtok($val,' ');
      $status[3] = $power==0 ? "<td $red>$val</td>" : "<td $green>$val</td>";
      break;
    case 'LOADPCT':
      $load = strtok($val,' ');
      $status[5] = $load>=90 ? "<td $red>$val</td>" : "<td $green>$val</td>";
      break;
    }
    if ($all) {
      if ($i%2==0) $result[] = "<tr>";
      $result[]= "<td><strong>$key</strong></td><td>$val</td>";
      if ($i%2==1) $result[] = "</tr>";
    }
  }
  if ($all && count($rows)%2==1) $result[] = "<td></td><td></td></tr>";
  if ($power && $load) $status[4] = ($load>=90 ? "<td $red>" : "<td $green>").intval($power*$load/100)." Watts</td>";
}
if ($all && !$rows) $result[] = "<tr><td colspan='4'><center>No information available</center></td></tr>";

echo "<tr>".implode('', $status)."</tr>";
if ($all) echo "\n".implode('', $result);
?>
