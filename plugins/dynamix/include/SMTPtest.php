<?PHP
/* Copyright 2015, Bergware International.
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
function PsExecute($command, $timeout = 20, $sleep = 2) {
  exec($command.'>/dev/null & echo $!',$op);
  $pid = (int)$op[0];
  $timer = 0;
  while ($timer<$timeout) {
    sleep($sleep);
    $timer += $sleep;
    if (PsEnded($pid)) return true;
  }
  PsKill($pid);
  return false;
}
function PsEnded($pid) {
  exec("ps -eo pid|grep $pid",$output);
  foreach ($output as $list) if (trim($list)==$pid) return false;
  return true;
}
function PsKill($pid) {
  exec("kill -9 $pid");
}
if (PsExecute("/usr/local/emhttp/webGui/scripts/notify -s 'unRAID SMTP Test' -d 'Test message received!' -i 'alert' -t")) {
  $result = exec("tail -3 /var/log/syslog|awk '/sSMTP/ {getline;print}'|cut -d']' -f2|cut -d'(' -f1");
  $color = strpos($result, 'Sent mail') ? 'green' : 'red';
  echo "Test result<span class='$color'>$result</span>";
} else {
  echo "Test result<span class='red'>: No reply from mail server</span>";
}
?>
