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
require_once('Wrappers.php');

// Helper functions
function my_scale($value, &$unit, $precision = NULL) {
  global $display;
  $scale = $display['scale'];
  $number = $display['number'];
  $dot = substr($number,0,1);
  $comma = substr($number,1,1);
  $units = array('B','KB','MB','GB','TB','PB');
  if ($scale==0 && !$precision) {
    $unit = '';
    return number_format($value, 0, $dot, ($value>=10000 ? $comma : ''));
  } else {
    $base = $value ? floor(log($value, 1000)) : 0;
    if ($scale>0 && $base>$scale) $base = $scale;
    $unit = $units[$base];
    $value = round($value/pow(1000, $base), $precision ? $precision : 2);
    return number_format($value, $precision ? $precision : (($value-intval($value)==0 || $value>=100) ? 0 : ($value>=10 ? 1 : 2)), $dot, ($value>=10000 ? $comma : ''));
  }
}
function my_number($value) {
  global $display;
  $number = $display['number'];
  $dot = substr($number,0,1);
  $comma = substr($number,1,1);
  return number_format($value, 0, $dot, ($value>=10000 ? $comma : ''));
}
function my_time($time, $fmt = NULL) {
  global $display;
  if (!$fmt) $fmt = $display['date'].($display['date']!='%c' ? ", {$display['time']}" : "");
  return $time ? strftime($fmt, $time) : "unset";
}
function my_temp($value) {
  global $display;
  $unit = $display['unit'];
  $dot = substr($display['number'],0,1);
  return is_numeric($value) ? (($unit=='C' ? str_replace('.', $dot, $value) : round(9/5*$value+32))." $unit") : $value;
}
function my_disk($name) {
  return ucfirst(preg_replace(array('/^(parity|disk|cache)([0-9]+)/','/^parity,cache,disk/'),array('$1 $2','Parity, Cache, Disk '),$name));
}
function my_word($num) {
  $words = array('zero','one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen','twenty');
  return $num<count($words) ? $words[$num] : $num;
}
function my_usage() {
  global $disks,$var;
  $arraysize=0;
  $arrayfree=0;
  foreach ($disks as $disk) {
    if (strpos($disk['name'],'disk')!==false) {
      $arraysize += $disk['sizeSb'];
      $arrayfree += $disk['fsFree'];
    }
  }
  if ($var['fsNumMounted']>0) {
    $used = $arraysize ? 100-round(100*$arrayfree/$arraysize) : 0;
    echo "<div class='usage-bar'><span style='width:{$used}%' class='".usage_color($used,false)."'><span>{$used}%</span></span></div>";
  } else {
    echo "<div class='usage-bar'><span><center>".($var['fsState']=='Started'?'Maintenance':'off-line')."</center></span></div>";
  }
}
function usage_color($limit,$free) {
  global $display;
  if ($display['text']==1 || intval($display['text']/10)==1) return '';
  if (!$free) {
    if ($limit>=$display['critical']) return 'redbar';
    if ($limit>=$display['warning']) return 'orangebar';
    return 'greenbar';
  } else {
    if ($limit<=100-$display['critical']) return 'redbar';
    if ($limit<=100-$display['warning']) return 'orangebar';
    return 'greenbar';
  }
}
function my_check($time) {
  global $var;
  if (!$time) return "unavailable (system reboot or log rotation)";
  $days = floor($time/86400);
  $hmss = $time-$days*86400;
  $hour = floor($hmss/3600);
  $mins = $hmss/60%60;
  $secs = $hmss%60;
  return plus($days,'day',($hour|$mins|$secs)==0).plus($hour,'hour',($mins|$secs)==0).plus($mins,'minute',$secs==0).plus($secs,'second',true).". Average speed: ".my_scale($var['mdResyncSize']*1024/$time,$unit,1)." $unit/sec";
}
function my_error($code) {
  switch ($code) {
  case -4:
    return "<em>user abort</em>";
  default:
    return "<strong>$code</strong>";
  }
}
function mk_option($select, $value, $text, $extra = "") {
  return "<option value='$value'".($value==$select ? " selected" : "").(strlen($extra) ? " $extra" : "").">$text</option>";
}
function mk_option_check($name, $value, $text = "") {
  if ($text) {
    $checked = strpos("$name,", "$value,")===false ? "" : " selected";
    return "<option value='$value'$checked>$text</option>";
  }
  if (strpos($name, 'disk')!==false) {
    $checked = strpos("$value,", "$name,")===false ? "" : " selected";
    return "<option value='$name'$checked>".my_disk($name)."</option>";
  }
}
function day_count($time) {
  $now  = new DateTime("@".intval(time()/86400)*86400);
  $last = new DateTime("@".intval($time/86400)*86400);
  $days = date_diff($last,$now)->format('%a');
  switch (true) {
  case ($days<0):
    return "";
  case ($days==0):
    return " (today)";
  case ($days==1):
    return " (yesterday)";
  case ($days<=31):
    return " (".my_word($days)." days ago)";
  case ($days<=61):
    return " <span class='orange-text'>($days days ago)</span>";
  case ($days>61):
    return " <span class='red-text'>($days days ago)</span>";
  }
}
function plus($val, $word, $last) {
  return $val>0 ? (($val || $last) ? ($val.' '.$word.($val!=1?'s':'').($last ?'':', ')) : '') : '';
}
function urlencode_path($path) {
  return str_replace("%2F", "/", urlencode($path));
}
function pgrep($process_name) {
  $pid = exec("pgrep $process_name", $output, $retval);
  return $retval == 0 ? $pid : false;
}
function input_secure_users($sec) {
  global $name, $users;
  echo "<table class='settings'>";
  $write_list = explode(",", $sec[$name]['writeList']);
  foreach ($users as $user) {
    $idx = $user['idx'];
    if ($user['name'] == "root") {
      echo "<input type='hidden' name='userAccess.$idx' value='no-access'>";
      continue;
    }
    if (in_array( $user['name'], $write_list))
      $userAccess = "read-write";
    else
      $userAccess = "read-only";
    echo "<tr><td>{$user['name']}</td>";
    echo "<td><select name='userAccess.$idx' size='1'>";
    echo mk_option($userAccess, "read-write", "Read/Write");
    echo mk_option($userAccess, "read-only", "Read-only");
    echo "</select></td></tr>";
  }
  echo "</table>";
}
function input_private_users($sec) {
  global $name, $users;
  echo "<table class='settings'>";
  $read_list = explode(",", $sec[$name]['readList']);
  $write_list = explode(",", $sec[$name]['writeList']);
  foreach ($users as $user) {
    $idx = $user['idx'];
    if ($user['name'] == "root") {
      echo "<input type='hidden' name='userAccess.$idx' value='no-access'>";
      continue;
    }
    if (in_array( $user['name'], $read_list))
      $userAccess = "read-only";
    elseif (in_array( $user['name'], $write_list))
      $userAccess = "read-write";
    else
      $userAccess = "no-access";
    echo "<tr><td>{$user['name']}</td>";
    echo "<td><select name='userAccess.$idx' size='1'>";
    echo mk_option($userAccess, "read-write", "Read/Write");
    echo mk_option($userAccess, "read-only", "Read-only");
    echo mk_option($userAccess, "no-access", "No Access");
    echo "</select></td></tr>";
  }
  echo "</table>";
}
function is_block($path) {
  return (@filetype(realpath($path)) == 'block');
}
function autov($file) {
  global $docroot;
  clearstatcache(true, $docroot.$file);
  echo "$file?v=".filemtime($docroot.$file);
}
function transpose_user_path($path) {
  if (strpos($path, '/mnt/user/') === 0 && file_exists($path)) {
    $realdisk = trim(shell_exec("getfattr --absolute-names -n user.LOCATIONS ".escapeshellarg($path)." 2>/dev/null|grep -Po '^user.LOCATIONS=\"\K[^\\\"]+'"));
    if (!empty($realdisk)) {
      // there may be several disks participating in this path (e.g. disk1,2,3) so
      // only return the first disk and replace 'user' with say 'cache' or 'disk1'
      $path = str_replace('/mnt/user/', '/mnt/'.strtok($realdisk.',', ',').'/', $dir);
    }
  }
  return $path;
}
?>
