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

function duration($h) {
  $time = ceil(time()/3600)*3600;
  $now = new DateTime("@$time");
  $poh = new DateTime("@".($time-$h*3600));
  $age = date_diff($poh,$now);
  return " (".($age->y?"{$age->y}y, ":"").($age->m?"{$age->m}m, ":"").($age->d?"{$age->d}d, ":"")."{$age->h}h)";
}

$port = $_POST['port'];

switch ($_POST['cmd']) {
case "attributes":
  $unraid = parse_plugin_cfg("dynamix",true);
  $events = explode('|', $unraid['notify']['events']);
  $temps = array(190,194);
  $max = $unraid['display']['max'];
  $hot = $unraid['display']['hot'];
  exec("smartctl -A /dev/$port|awk 'NR>7'",$output);
  foreach ($output as $line) {
    if (!$line) continue;
    $info = explode(' ', trim(preg_replace('/\s+/',' ',$line)), 10);
    $color = "";
    if (array_search($info[0], $events)!==false && $info[9]>0) $color = " class='orange-text'";
    else if (array_search($info[0], $temps)!==false) {
      if ($info[9]>=$max) $color = " class='red-text'"; else if ($info[9]>=$hot) $color = " class='orange-text'";
    }
    echo "<tr{$color}>";
    if ($info[0] == 9 && is_numeric($info[9])) $info[9] .= duration($info[9]);
    foreach ($info as $field) echo "<td>".str_replace('_',' ',$field)."</td>";
    echo "</tr>";
  }
  break;
case "capabilities":
  exec("smartctl -c /dev/$port|awk 'NR>5'",$output);
  $row = ["","",""];
  foreach ($output as $line) {
    if (!$line) continue;
    $line = preg_replace('/^_/','__',preg_replace(array('/__+/','/_ +_/'),'_',str_replace(array(chr(9),')','('),'_',$line)));
    $info = array_map('trim', explode('_', preg_replace('/_( +)_ /','__',$line), 3));
    if (isset($info[0])) $row[0] .= ($row[0] ? " " : "").$info[0];
    if (isset($info[1])) $row[1] .= ($row[1] ? " " : "").$info[1];
    if (isset($info[2])) $row[2] .= ($row[2] ? " " : "").$info[2];
    if (substr($row[2],-1)=='.') {
      echo "<tr><td>{$row[0]}</td><td>{$row[1]}</td><td>{$row[2]}</td></tr>";
      $row = ["","",""];
    }
  }
  break;
case "identify":
  exec("smartctl -i /dev/$port|awk 'NR>4'",$output);
  exec("smartctl -H /dev/$port|grep 'result'|sed 's:self-assessment test result::'",$output);
  foreach ($output as $line) {
    if (!strlen($line)) continue;
    $info = array_map('trim', explode(':', $line, 2));
    if ($info[1]=='PASSED') $info[1] = "<span class='green-text'>Passed</span>";
    if ($info[1]=='FAILED') $info[1] = "<span class='red-text'>Failed</span>";
    echo "<tr><td>".preg_replace("/ is$/","",$info[0]).":</td><td>$info[1]</td></tr>";
  }
  break;
case "save":
  exec("smartctl -a /dev/$port >{$_SERVER['DOCUMENT_ROOT']}/{$_POST['file']}");
  break;
case "short":
  exec("smartctl -t short /dev/$port");
  break;
case "long":
  exec("smartctl -t long /dev/$port");
  break;
case "stop":
  exec("smartctl -X /dev/$port");
  break;
case "update":
  if (!exec("hdparm -C /dev/$port|grep -om1 active")) {
    echo "<a href='/update.htm?cmdSpinup={$_POST['name']}' class='info' target='progressFrame'><input type='button' value='Spin Up'></a><span class='orange-text'><big>Unavailable - disk must be spun up</big></span>";
    break;
  }
  $progress = exec("smartctl -c /dev/$port|grep -Pom1 '\d+%'");
  if ($progress) {
    echo "<big><i class='fa fa-spinner fa-pulse'></i> ".(100-substr($progress,0,-1))."% complete</big>";
    break;
  }
  $result = trim(exec("smartctl -l selftest /dev/$port|grep -m1 '^# 1'|cut -c26-55"));
  if (!$result) {
    echo "<big>No self-tests logged on this disk</big>";
    break;
  }
  if (strpos($result, "Completed without error")!==false) {
    echo "<span class='green-text'><big>$result</big></span>";
    break;
  }
  if (strpos($result, "Aborted")!==false or strpos($result, "Interrupted")!==false) {
    echo "<span class='orange-text'><big>$result</big></span>";
    break;
  }
  echo "<span class='red-text'><big>Errors occurred - Check SMART report</big></span>";
  break;
case "selftest":
  echo shell_exec("smartctl -l selftest /dev/$port|awk 'NR>5'");
  break;
case "errorlog":
  echo shell_exec("smartctl -l error /dev/$port|awk 'NR>5'");
  break;
}
?>
