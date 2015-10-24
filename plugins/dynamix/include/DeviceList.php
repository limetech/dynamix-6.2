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
require_once('Helpers.php');

$path    = $_POST['path'];
$width   = $_POST['width'];
$var     = parse_ini_file("state/var.ini");
$devs    = parse_ini_file("state/devs.ini",true);
$disks   = parse_ini_file("state/disks.ini",true);
$screen  = '/tmp/screen_buffer';

$temps=0; $counts=0; $tot_size=0; $tot_used=0; $tot_free=0; $reads=0; $writes=0; $errors=0;

extract(parse_plugin_cfg("dynamix",true));

function device_info($disk) {
  global $path, $width, $var, $display, $screen;
  $href = $disk['name'];
  if ($href != 'preclear') {
    $name = my_disk($href);
    $type = $disk['type'];
  } else {
    $name = $disk['device'];
    $type = 'Preclear';
    $href = "{$disk['device']}&file=$screen";
  }
  if ($var['fsState']=='Started' && $type!='Flash' && $type!='Preclear' && strpos($disk['status'], 'DISK_NP')===false) {
    $action = strpos($disk['color'],'blink')===false ? "down" : "up";
    $ctrl = "<a href='update.htm?cmdSpin{$action}={$href}' title='Click to spin $action device' class='none' target='progressFrame' onclick=\"$.removeCookie('one',{path:'/'});\"><i class='fa fa-sort-$action spacing'></i></a>";
  }
  else
    $ctrl = "";
  $ball = "/webGui/images/{$disk['color']}.png";
  switch ($disk['color']) {
    case 'green-on': $help = 'Normal operation, device is active'; break;
    case 'green-blink': $help = 'Device is in standby mode (spun-down)'; break;
    case 'blue-on': $help = ($disk['name']=='preclear' ? 'Unassigned device' : 'New device'); break;
    case 'blue-blink': $help = ($disk['name']=='preclear' ? 'Unassigned device, in standby mode' : 'New device, in stadby mode (spun-down)'); break;
    case 'yellow-on': $help = ($disk['type']=='Parity' ? 'Parity is invalid' : 'Device contents emulated'); break;
    case 'yellow-blink': $help = 'Device contents emulated, in standby mode (spun-down)'; break;
    case 'red-on':
    case 'red-blink': $help = ($disk['type']=='Parity' ? 'Parity device is disabled' : 'Device is disabled, contents emulated'); break;
    case 'red-off': $help = ($disk['type']=='Parity' ? 'Parity device missing' : 'Device is missing (disabled), contents emulated'); break;
    case 'grey-off': $help = 'Device not present'; break;
  }
  switch ($type) {
  case 'Flash':
    $device = "Flash";
    break;
  default:
    $device = "Device";
    break;
  }
  $status = "<a class='info nohand' onclick='return false'><img src='$ball' class='icon'><span>${help}</span></a>";
  $link = strpos($disk['status'], 'DISK_NP')===false ? "<a href='$path/$device?name=$href'>$name</a>" : $name;
  return $ctrl.$status.$link;
}
function device_browse($disk) {
  global $path;
  if ($disk['fsStatus']=='Mounted') {
    $dir = $disk['name']=="flash" ? "/boot" : "/mnt/{$disk['name']}";
    return "<a href='$path/Browse?dir=$dir'><img src='/webGui/images/explore.png' title='Browse $dir'></a>";
  }
}
function device_desc($disk) {
  global $var;
  $size = my_scale($disk['size']*1024, $unit);
  return "{$disk['id']} - $size $unit ({$disk['device']})";
}
function assignment($disk) {
  global $var, $disks, $devs, $screen;
  $out = "<form method='POST' name=\"{$disk['name']}Form\" action='/update.htm' target='progressFrame'>";
  $out .= "<input type='hidden' name='changeDevice' value='Apply'>";
  $out .= "<select style=\"max-width:none\" name=\"slotId.{$disk['idx']}\" onChange=\"{$disk['name']}Form.submit()\">";
  $empty = ($disk['idSb']!="" ? "no device" : "unassigned");
  if ($disk['id']!="") {
    $out .= "<option value=\"{$disk['id']}\" selected>".device_desc($disk)."</option>";
    $out .= "<option value=''>$empty</option>";
  } else {
    $out .= "<option value='' selected>$empty</option>";
  }
  foreach ($devs as $dev) {
    if (!file_exists("{$screen}_{$dev['device']}")) {
      $out .= "<option value=\"{$dev['id']}\">".device_desc($dev)."</option>";
    }
  }
  return $out."</select></form>";
}
function render_used_and_free($disk) {
  global $display;
  if ($disk['type']=='Parity' || $disk['fsStatus']=='-') {
    echo "<td colspan='4'></td>";
  } else if ($disk['fsStatus']=='Mounted') {
    echo "<td>{$disk['fsType']}</td>";
    echo "<td>".my_scale($disk['fsSize']*1024, $unit)." $unit</td>";
    switch ($display['text']) {
    case 0:
      $text1 = true; $text2 = true; break;
    case 1: case 2:
      $text1 = false; $text2 = false; break;
    case 10: case 20:
      $text1 = true; $text2 = false; break;
    case 11: case 21:
      $text1 = false; $text2 = true; break;
    }
    if ($text1) {
      echo "<td>".my_scale($disk['fsUsed']*1024, $unit)." $unit</td>";
    } else {
      $used = $disk['fsSize'] ? 100 - round(100*$disk['fsFree']/$disk['fsSize']) : 0;
      echo "<td><div class='usage-disk'><span style='margin:0;width:{$used}%' class='".usage_color($used,false)."'><span>".my_scale($disk['fsUsed']*1024, $unit)." $unit</span></span></div></td>";
    }
    if ($text2) {
      echo "<td>".my_scale($disk['fsFree']*1024, $unit)." $unit</td>";
    } else {
      $free = $disk['fsSize'] ? round(100*$disk['fsFree']/$disk['fsSize']) : 0;
      echo "<td><div class='usage-disk'><span style='margin:0;width:{$free}%' class='".usage_color($free,true)."'><span>".my_scale($disk['fsFree']*1024, $unit)." $unit</span></span></div></td>";
    }
  } else {
    echo "<td colspan='2'></td><td>{$disk['fsStatus']}</td><td></td>";
  }
}
function array_offline($disk) {
  echo "<tr>";
  switch ($disk['status']) {
  case "DISK_NP":
  case "DISK_OK_NP":
  case "DISK_NP_DSBL":
    echo "<td>".device_info($disk)."</td>";
    echo "<td>".assignment($disk)."</td>";
    echo "<td colspan='9'></td>";
    break;
  case "DISK_OK":
  case "DISK_INVALID":
  case "DISK_DSBL":
  case "DISK_DSBL_NEW":
  case "DISK_NEW":
    echo "<td>".device_info($disk)."</td>";
    echo "<td>".assignment($disk)."</td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td colspan='8'></td>";
    break;
  case "DISK_NP_MISSING":
    echo "<td>".device_info($disk)."<span class='diskinfo'><em>Missing</em></span></td>";
    echo "<td>".assignment($disk)."<em>{$disk['idSb']} - ".my_scale($disk['sizeSb']*1024, $unit)." $unit</em></td>";
    echo "<td colspan='9'></td>";
    break;
  case "DISK_WRONG":
    echo "<td>".device_info($disk)."<span class='diskinfo'><em>Wrong</em></span></td>";
    echo "<td>".assignment($disk)."<em>{$disk['idSb']} - ".my_scale($disk['sizeSb']*1024, $unit)." $unit</em></td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td colspan='8'></td>";
    break;
  }
  echo "</tr>";
}
function array_online($disk) {
  global $display, $temps, $counts, $tot_size, $tot_used, $tot_free, $reads, $writes, $errors;
  if (is_numeric($disk['temp'])) {
    $temps += $disk['temp'];
    $counts++;
  }
  $reads += $disk['numReads'];
  $writes += $disk['numWrites'];
  $errors += $disk['numErrors'];
  if (isset($disk['fsFree']) && $disk['type']!='Parity') {
    $disk['fsUsed'] = $disk['fsSize'] - $disk['fsFree'];
    $tot_size += $disk['fsSize'];
    $tot_free += $disk['fsFree'];
    $tot_used += $disk['fsUsed'];
  }
  echo "<tr>";
  switch ($disk['status']) {
  case "DISK_NP":
//  Suppress empty slots to keep device list short
//  this actually should be configurable
//  echo "<td>".device_info($disk)."</td>";
//  echo "<td colspan='9'>Not installed</td>";
//  echo "<td></td>";
    break;
  case "DISK_OK_NP":
    echo "<td>".device_info($disk)."</td>";
    echo "<td>Not Installed</td>";
    echo "<td colspan='4'></td>";
    render_used_and_free($disk);
    echo "<td>".device_browse($disk)."</td>";
    break;
  case "DISK_NP_DSBL":
    echo "<td>".device_info($disk)."</td>";
    if ($disk['type']=="Parity") {
      echo "<td>Not installed</td>";
      echo "<td colspan='9'></td>";
    } else {
      echo "<td><em>Not installed</em></td>";
      echo "<td colspan='4'></td>";
      render_used_and_free($disk);
      echo "<td>".device_browse($disk)."</td>";
    }
    break;
  case "DISK_DSBL":
    echo "<td>".device_info($disk)."</td>";
    echo "<td>".device_desc($disk)."</td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td>".my_number($disk['numReads'])."</td>";
    echo "<td>".my_number($disk['numWrites'])."</td>";
    echo "<td>".my_number($disk['numErrors'])."</td>";
    if ($disk['type']=="Parity") {
      echo "<td colspan='5'></td>";
    } else {
      render_used_and_free($disk);
      echo "<td>".device_browse($disk)."</td>";
    }
    break;
  default:
    echo "<td>".device_info($disk)."</td>";
    echo "<td>".device_desc($disk)."</td>";
    echo "<td>".my_temp($disk['temp'])."</td>";
    echo "<td>".my_number($disk['numReads'])."</td>";
    echo "<td>".my_number($disk['numWrites'])."</td>";
    echo "<td>".my_number($disk['numErrors'])."</td>";
    render_used_and_free($disk);
    echo "<td>".device_browse($disk)."</td>";
    break;
  }
  echo "</tr>";
}
function my_clock($time) {
  if (!$time) return 'less than a minute';
  $days = floor($time/1440);
  $hour = $time/60%24;
  $mins = $time%60;
  return plus($days,'day',($hour|$mins)==0).plus($hour,'hour',$mins==0).plus($mins,'minute',true);
}
function read_disk($device, $item) {
  global $var;
  $smart = "/var/local/emhttp/smart/$device";
  if (!file_exists($smart) || (time()-filemtime($smart)>=$var['poll_attributes'])) exec("smartctl -n standby -A /dev/$device > $smart");
  $temp = exec("awk '/Temperature/{print \$10;exit}' $smart");
  switch ($item) {
    case 'color': return $temp ? 'blue-on' : 'blue-blink';
    case 'temp' : return $temp ? $temp : '*';
  }
}
function show_totals($text) {
  global $var, $display, $temps, $counts, $tot_size, $tot_used, $tot_free, $reads, $writes, $errors;
  echo "<tr class='tr_last'>";
  echo "<td><img src='/webGui/images/sum.png' class='icon'>Total</td>";
  echo "<td>$text</td>";
  echo "<td>".($counts>0?my_temp(round($temps/$counts, 1)):'*')."</td>";
  echo "<td>".my_number($reads)."</td>";
  echo "<td>".my_number($writes)."</td>";
  echo "<td>".my_number($errors)."</td>";
  echo "<td></td>";
  if (strstr($text,"Array") && ($var['startMode'] == "Normal")) {
    echo "<td>".my_scale($tot_size*1024, $unit)." $unit</td>";
    switch ($display['text']) {
    case 0:
      $text1 = true; $text2 = true; break;
    case 1: case 2:
      $text1 = false; $text2 = false; break;
    case 10: case 20:
      $text1 = true; $text2 = false; break;
    case 11: case 21:
      $text1 = false; $text2 = true; break;
    }
    if ($text1) {
      echo "<td>".my_scale($tot_used*1024, $unit)." $unit</td>";
    } else {
      $used = $tot_size ? 100 - round(100*$tot_free/$tot_size) : 0;
      echo "<td><div class='usage-disk'><span style='margin:0;width:{$used}%' class='".usage_color($used,false)."'><span>".my_scale($tot_used*1024, $unit)." $unit</span></span></div></td>";
    }
    if ($text2) {
      echo "<td>".my_scale($tot_free*1024, $unit)." $unit</td>";
    } else {
      $free = $tot_size ? round(100*$tot_free/$tot_size) : 0;
      echo "<td><div class='usage-disk'><span style='margin:0;width:{$free}%' class='".usage_color($free,true)."'><span>".my_scale($tot_free*1024, $unit)." $unit</span></span></div></td>";
    }
    echo "<td></td>";
  }
  else
    echo "<td colspan=4></td>";
  echo "</tr>";
}
function array_slots() {
  global $var;
  $min = max($var['sbNumDisks'], 3);
  $max = $var['MAX_ARRAYSZ'];
  $out = "";
  $out .= "<form method='POST' action='/update.htm' target='progressFrame'>";
  $out .= "<input type='hidden' name='changeSlots' value='Apply'>";
  $out .= "<select name='SYS_ARRAY_SLOTS' onChange='this.form.submit()'>";
  for ($n=$min; $n<=$max; $n++) {
    $selected = ($n == $var['SYS_ARRAY_SLOTS'])? " selected" : "";
    $out .= "<option value='$n'{$selected}>$n</option>";
  }
  $out .= "</select></form>";
  return $out;
}
function cache_slots() {
  global $var;
  $min = $var['cacheSbNumDisks'];
  $max = $var['MAX_CACHESZ'];
  $out = "";
  $out .= "<form method='POST' action='/update.htm' target='progressFrame'>";
  $out .= "<input type='hidden' name='changeSlots' value='Apply'>";
  $out .= "<select name='SYS_CACHE_SLOTS' onChange='this.form.submit()'>";
  for ($n=$min; $n<=$max; $n++) {
    $option = $n ? $n : "none";
    $selected = ($n == $var['SYS_CACHE_SLOTS'])? " selected" : "";
    $out .= "<option value='$n'{$selected}>$option</option>";
  }
  $out .= "</select></form>";
  return $out;
}
switch ($_POST['device']) {
case 'array':
  if ($var['fsState']=='Stopped') {
    foreach ($disks as $disk) {if ($disk['type']=='Parity') array_offline($disk);}
    foreach ($disks as $disk) {if ($disk['type']=='Data') array_offline($disk);}
    echo "<tr class='tr_last'><td><img src='/webGui/images/sum.png' class='icon'>Slots:</td><td colspan='9'>".array_slots()."</td><td></td></tr>";
  } else {
    foreach ($disks as $disk) {if ($disk['type']=='Parity') array_online($disk);}
    foreach ($disks as $disk) {if ($disk['type']=='Data') array_online($disk);}
    if ($display['total']) show_totals("Array of ".my_word($var['mdNumDisks'])." devices");
  }
  break;
case 'flash':
  $disk = &$disks['flash'];
  $disk['fsUsed'] = $disk['fsSize'] - $disk['fsFree'];
  echo "<tr>";
  echo "<td>".device_info($disk)."</td>";
  echo "<td>".device_desc($disk)."</td>";
  echo "<td>*</td>";
  echo "<td>".my_number($disk['numReads'])."</td>";
  echo "<td>".my_number($disk['numWrites'])."</td>";
  echo "<td>".my_number($disk['numErrors'])."</td>";
  render_used_and_free($disk);
  echo "<td>".device_browse($disk)."</td>";
  echo "</tr>";
  break;
case 'cache':
  if ($var['fsState']=='Stopped') {
    foreach ($disks as $disk) {if ($disk['type']=='Cache') array_offline($disk);}
    echo "<tr class='tr_last'><td><img src='/webGui/images/sum.png' class='icon'>Slots:</td><td colspan='9'>".cache_slots()."</td><td></td></tr>";
    echo "<tr><td colspan='10'></td><td></td></tr>";
  } else {
    foreach ($disks as $disk) {if ($disk['type']=='Cache') array_online($disk);}
    if ($display['total'] && $var['cacheSbNumDisks']>1) show_totals("Pool of ".my_word($var['cacheNumDevices'])." devices");
  }
  break;
case 'open':
  $status = isset($confirm['preclear']) ? '' : '_NP';
  foreach ($devs as $dev) {
    $dev['name'] = 'preclear';
    $dev['color'] = read_disk($dev['device'], 'color');
    $dev['temp'] = read_disk($dev['device'], 'temp');
    $dev['status'] = $status;
    echo "<tr>";
    echo "<td>".device_info($dev)."</td>";
    echo "<td>".device_desc($dev)."</td>";
    echo "<td>".my_temp($dev['temp'])."</td>";
    if (file_exists("/tmp/preclear_stat_{$dev['device']}")) {
      $text = exec("cut -d'|' -f3 /tmp/preclear_stat_{$dev['device']} | sed 's:\^n:\<br\>:g'");
      if (strpos($text,'Total time')===false) $text = 'Preclear in progress... '.$text;
      echo "<td colspan='8' style='text-align:right'><em>$text</em></td>";
    } else
      echo "<td colspan='8'></td>";
    echo "</tr>";
  }
  break;
case 'parity':
  $data = array();
  if ($var['mdResync']>0) {
    $data[] = my_scale($var['mdResync']*1024, $unit)." $unit";
    $data[] = my_clock(floor(($var['currTime']-$var['sbUpdated'])/60));
    $data[] = my_scale($var['mdResyncPos']*1024, $unit)." $unit (".number_format(($var['mdResyncPos']/($var['mdResync']/100+1)),1,substr($display['number'],0,1),'')." %)";
    $data[] = my_scale($var['mdResyncDb']/$var['mdResyncDt']*1024, $unit, 1)." $unit/sec";
    $data[] = my_clock(round(((($var['mdResyncDt']*(($var['mdResync']-$var['mdResyncPos'])/($var['mdResyncDb']/100+1)))/100)/60),0));
    $data[] = $var['sbSyncErrs'];
    echo implode(';',$data);
  }
  break;
}
?>
