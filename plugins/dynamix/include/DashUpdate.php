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
function normalize($type,$count) {
  $words = explode('_',$type);
  foreach ($words as &$word) $word = $word==strtoupper($word) ? $word : preg_replace(['/^(ct|cnt)$/','/^blk$/'],['count','block'],strtolower($word));
  return ucfirst(implode(' ',$words)).": ".str_replace('_',' ',strtolower($count))."\n";
}
function my_insert(&$source,$string) {
  $source = substr_replace($source,$string,4,0);
}
function my_smart(&$source,$name,$page) {
  global $var,$disks,$path,$failed,$numbers,$saved;
  $disk   = &$disks[$name];
  $select = isset($disk['smSelect']) ? $disk['smSelect'] : -1; if ($select==-1) $select = isset($var['smSelect']) ? $var['smSelect'] : 0;
  $level  = isset($disk['smLevel']) ? $disk['smLevel'] : -1; if ($level==-1) $level = isset($var['smLevel']) ? $var['smLevel'] : 1;
  $events = isset($disk['smEvents']) ? explode('|',$disk['smEvents']) : (isset($var['smEvents']) ? explode('|',$var['smEvents']) : $numbers);
  $thumb = 'good';
  $file   = "state/smart/$name";
  if (file_exists("$file.ssa") && in_array(file_get_contents("$file.ssa"),$failed)) {
    $thumb = 'bad';
  } else {
    if (empty($saved["smart"]["$name.ack"])) {
      exec("awk 'NR>7{print $1,$2,$4,$6,$9,$10}' $file 2>/dev/null", $codes);
      foreach ($codes as $code) {
        if (!$code) continue;
        list($id,$class,$value,$thres,$when,$raw) = explode(' ',$code);
        $fail = strpos($when,'FAILING_NOW')!==false;
        if (!$fail && !in_array($id,$events)) continue;
        if ($fail || ($select ? $thres>0 && $value<=$thres*$level : $raw>0)) {$thumb = 'alert'; break;};
      }
    }
  }
  my_insert($source, "<span id='smart-$name' name='$page' class='$thumb'><img src=\"$path/$thumb.png\" onmouseover=\"this.style.cursor='pointer'\" title=\"Click to get context menu\"></span>");
}
function my_usage(&$source,$used) {
  my_insert($source, $used ? "<div class='usage-disk all'><span style='width:$used'>$used</span></div>" : "-");
}
function my_temp($value,$unit) {
  return ($unit=='C' ? $value : round(9/5*$value+32))." $unit";
}
function my_clock($time) {
  if (!$time) return 'less than a minute';
  $days = floor($time/1440);
  $hour = $time/60%24;
  $mins = $time%60;
  return plus($days,'day',($hour|$mins)==0).plus($hour,'hour',$mins==0).plus($mins,'minute',true);
}
function plus($val,$word,$last) {
  return $val>0?(($val||$last)?($val.' '.$word.($val!=1?'s':'').($last ?'':', ')):''):'';
}
function active_disks($disk) {
  return $disk['status']!='DISK_NP' && preg_match('/^(Parity|Data|Cache)$/',$disk['type']);
}
$path   = '/webGui/images';
$failed = ['FAILED','NOK'];
switch ($_POST['cmd']) {
case 'disk':
  $i = 1;
  $var = [];
  $disks = @array_filter(parse_ini_file('state/disks.ini',true),'active_disks');
  $devs  = @parse_ini_file('state/devs.ini',true);
  $saved = @parse_ini_file('state/monitor.ini',true);
  require_once 'CustomMerge.php';
  require_once 'Preselect.php';
  $slots = $_POST['slots'];
  $row1 = array_fill(0,31,'<td></td>'); my_insert($row1[0],'Active');
  $row2 = array_fill(0,31,'<td></td>'); my_insert($row2[0],'Inactive');
  $row3 = array_fill(0,31,'<td></td>'); my_insert($row3[0],'Unassigned');
  $row4 = array_fill(0,31,'<td></td>'); my_insert($row4[0],'Faulty');
  $row5 = array_fill(0,31,'<td></td>'); my_insert($row5[0],'Heat alarm');
  $row6 = array_fill(0,31,'<td></td>'); my_insert($row6[0],'SMART status');
  $row7 = array_fill(0,31,'<td></td>'); my_insert($row7[0],'Utilization');
  $funcRenderRow = function($n,$disk) use (&$row1,&$row2,&$row3,&$row4,&$row5,&$row6,&$row7,$path,$_POST) {
    if ($n>0) {
      $state = $disk['color'];
      switch ($state) {
      case 'grey-off':
      break; //ignore
      case 'green-on':
        my_insert($row1[$n],"<img src=$path/$state.png>");
      break;
      case 'green-blink':
        my_insert($row2[$n],"<img src=$path/$state.png>");
      break;
      case 'blue-on':
      case 'blue-blink':
        my_insert($row3[$n],"<img src=$path/$state.png>");
      break;
      default:
        if ($disk['type']=='Parity' && $disk['status']=='DISK_NP_DSBL')
          my_insert($row3[$n],"<img src=$path/$state.png>"); //parity is really unassigned
        else
          my_insert($row4[$n],"<img src=$path/$state.png>");
      break;}
      $temp = $disk['temp'];
      $hot  = strlen($disk['hotTemp']) ? $disk['hotTemp'] : $_POST['hot'];
      $max  = strlen($disk['maxTemp']) ? $disk['maxTemp'] : $_POST['max'];
      $beep = $temp>=$max && $max>0 ? 'max' : ($temp>=$hot && $hot>0 ? 'hot' : '');
      if ($beep) my_insert($row5[$n],"<span class='heat-img'><img src='$path/$beep.png'></span><span class='heat-text' style='display:none'>".my_temp($temp,$_POST['unit'])."</span>");
      if ($disk['device'] && !strpos($state,'blink')) my_smart($row6[$n],$disk['name'],'Device');
      my_usage($row7[$n],($disk['type']!='Parity' && $disk['fsStatus']=='Mounted')?(round((1-$disk['fsFree']/$disk['fsSize'])*100).'%'):'');
    }
  };
  foreach ($disks as $disk) if ($disk['type']=='Parity') $funcRenderRow($i++,$disk);
  foreach ($disks as $disk) if ($disk['type']=='Data') $funcRenderRow($i++,$disk);
  if ($slots <= 30) {
    foreach ($disks as $disk) if ($disk['type']=='Cache') $funcRenderRow($i++,$disk);
    foreach ($devs as $dev) {
      $device = $dev['device'];
      $state = exec("hdparm -C /dev/$device|grep -Po active") ? 'blue-on' : 'blue-blink';
      if ($state=='blue-on') my_smart($row6[$i],$device,'New');
      my_insert($row3[$i++],"<img src=$path/$state.png>");
    }
  }
  echo "<tr>".implode('',$row1)."</tr>";
  echo "<tr>".implode('',$row2)."</tr>";
  echo "<tr>".implode('',$row3)."</tr>";
  echo "<tr>".implode('',$row4)."</tr>";
  echo "<tr>".implode('',$row5)."</tr>";
  echo "<tr>".implode('',$row6)."</tr>";
  echo "<tr>".implode('',$row7)."</tr>";
  if ($slots > 30) {
    echo '#'; $i = 1;
    $row1 = array_fill(0,31,'<td></td>'); my_insert($row1[0],'Active');
    $row2 = array_fill(0,31,'<td></td>'); my_insert($row2[0],'Inactive');
    $row3 = array_fill(0,31,'<td></td>'); my_insert($row3[0],'Unassigned');
    $row4 = array_fill(0,31,'<td></td>'); my_insert($row4[0],'Faulty');
    $row5 = array_fill(0,31,'<td></td>'); my_insert($row5[0],'Heat alarm');
    $row6 = array_fill(0,31,'<td></td>'); my_insert($row6[0],'SMART status');
    $row7 = array_fill(0,31,'<td></td>'); my_insert($row7[0],'Utilization');
    foreach ($disks as $disk) if ($disk['type']=='Cache') $funcRenderRow($i++,$disk);
    foreach ($devs as $dev) {
      $device = $dev['device'];
      $state = exec("hdparm -C /dev/$device|grep -Po active") ? 'blue-on' : 'blue-blink';
      if ($state=='blue-on') my_smart($row6[$i],$device,'New');
      my_insert($row3[$i++],"<img src=$path/$state.png>");
    }
    echo "<tr>".implode('',$row1)."</tr>";
    echo "<tr>".implode('',$row2)."</tr>";
    echo "<tr>".implode('',$row3)."</tr>";
    echo "<tr>".implode('',$row4)."</tr>";
    echo "<tr>".implode('',$row5)."</tr>";
    echo "<tr>".implode('',$row6)."</tr>";
    echo "<tr>".implode('',$row7)."</tr>";
  }
break;
case 'sys':
  exec("grep -Po '^Mem(Total|Available):\s+\K\d+' /proc/meminfo",$memory);
  exec("df /boot /var/log /var/lib/docker|grep -Po '\d+%'",$sys);
  $mem = max(round((1-$memory[1]/$memory[0])*100),0);
  echo "{$mem}%#".implode('#',$sys);
break;
case 'cpu':
  echo @file_get_contents('state/cpuload.ini');
break;
case 'fan':
  exec("sensors -uA 2>/dev/null|grep -Po 'fan\d_input: \K\d+'",$rpms);
  if ($rpms) echo implode(' RPM#',$rpms).' RPM';
break;
case 'port':
  switch ($_POST['view']) {
  case 'main':
    $ports = explode(',',$_POST['ports']); $i = 0;
    foreach ($ports as $port) {
      $mtu = file_get_contents("/sys/class/net/$port/mtu");
      if ($port=='bond0') {
        $ports[$i++] = exec("grep -Pom1 '^Bonding Mode: \K.+' /proc/net/bonding/bond0").", mtu $mtu";
      } else if ($port=='lo') {
        $ports[$i++] = str_replace('yes','loopback',exec("ethtool lo|grep -Pom1 '^\s+Link detected: \K.+'"));
      } else {
        unset($info);
        exec("ethtool $port|grep -Po '^\s+(Speed|Duplex|Link\sdetected): \K[^U\\n]+'",$info);
        $ports[$i++] = (array_pop($info)=='yes' && $info[0]) ? str_replace(['M','G'],[' M',' G'],$info[0]).", ".strtolower($info[1])." duplex, mtu $mtu" : "not connected";
      }
    }
  break;
  case 'port': exec("ifconfig -a -s|awk '/^(bond|eth|lo\s)/{print $3\"#\"$7}'",$ports); break;
  case 'link': exec("ifconfig -a -s|awk '/^(bond|eth|lo\s)/{print \"Errors: \"$4\"<br>Drops: \"$5\"<br>Overruns: \"$6\"#Errors: \"$8\"<br>Drops: \"$9\"<br>Overruns: \"$10}'",$ports); break;
  default: $ports = [];}
  echo implode('#',$ports);
break;
case 'parity':
  $var  = parse_ini_file("state/var.ini");
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
  echo "<span class='orange p0'><strong>".$mode." in progress... Completed: ".number_format(($var['mdResyncPos']/($var['mdResync']/100+1)),0)." %.</strong></span>";
  echo "<br><em>Elapsed time: ".my_clock(floor(($var['currTime']-$var['sbUpdated'])/60)).". Estimated finish: ".my_clock(round(((($var['mdResyncDt']*(($var['mdResync']-$var['mdResyncPos'])/($var['mdResyncDb']/100+1)))/100)/60),0))."</em>";
break;
case 'shares':
   $names = explode(',',$_POST['names']);
   switch ($_POST['com']) {
   case 'smb':
     exec("lsof /mnt/user /mnt/disk* 2>/dev/null|awk '/^smbd/ && $0!~/\.AppleD(B|ouble)/ && $5==\"REG\"'|awk -F/ '{print $4}'",$lsof);
     $counts = array_count_values($lsof); $count = [];
     foreach ($names as $name) $count[] =  isset($counts[$name]) ? $counts[$name] : 0;
     echo implode('#',$count);
   break;
   case 'afp':
   case 'nfs':
   // not available
   break;}
break;}
