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
function plus($val, $word, $last) {
  return $val>0 ? (($val||$last)?($val.' '.$word.($last?'':', ')):'') : '';
}
function my_duration($time) {
  if (!$time) return 'Unavailable';
  $days = floor($time/86400);
  $hmss = $time-$days*86400;
  $hour = floor($hmss/3600);
  $mins = $hmss/60%60;
  $secs = $hmss%60;
  return plus($days,'day',($hour|$mins|$secs)==0).plus($hour,'hr',($mins|$secs)==0).plus($mins,'min',$secs==0).plus($secs,'sec',true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-fonts.css">
<link type="text/css" rel="stylesheet" href="/webGui/styles/default-white.css">
</head>
<body>
<table class='share_status' style='margin-top:0'><thead><tr><td>Date</td><td>Duration</td><td>Speed</td><td>Status</td></tr></thead><tbody>
<?
$log = '/boot/config/parity-checks.log'; $row = 0;
if (file_exists($log)) {
  $handle = fopen($log, 'r');
  while (($line = fgets($handle)) !== false) {
    list($date,$duration,$speed,$status) = explode('|',$line);
    if ($speed==0) $speed = 'Unavailable';
    if ($duration>0||$status<>0) {echo "<tr><td>$date</td><td>".my_duration($duration)."</td><td>$speed</td><td>".($status==0?'OK':$status)."</td></tr>"; $row++;}
  }
  fclose($handle);
}
if ($row==0) echo "<tr><td colspan='4' style='text-align:center'>No parity check history present!</td></tr>";
?>
</tbody></table>
<center><input type="button" value="Done" onclick="top.Shadowbox.close()"></center>
</body>
</html>