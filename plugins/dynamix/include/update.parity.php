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
require_once 'webGui/include/Wrappers.php';

$memory = '/tmp/memory.tmp';
if (isset($_POST['#apply'])) {
  $cron = "";
  if ($_POST['mode']>0) {
    $hour = isset($_POST['hour']) ? $_POST['hour'] : '* *';
    $dotm = isset($_POST['dotm']) ? $_POST['dotm'] : '*';
    switch ($dotm) {
    case '28-31':
      $term = '[ $(date +%d -d tomorrow) -eq 1 ] && ';
      break;
    case 'W1':
      $dotm = '*';
    $term = '[ $(date +%d) -le 7 ] && ';
    break;
    case 'W2':
      $dotm = '*';
      $term = '[ $(date +%d) -ge 8 -a $(date +%d) -le 14 ] && ';
      break;
    case 'W3':
      $dotm = '*';
      $term = '[ $(date +%d) -ge 15 -a $(date +%d) -le 21 ] && ';
      break;
    case 'W4':
      $dotm = '*';
      $term = '[ $(date +%d) -ge 22 -a $(date +%d) -le 28 ] && ';
      break;
    case 'WL':
      $dotm = '*';
      $term = '[ $(date +%d -d +7days) -le 7 ] && ';
      break;
    default:
      $term = '';
    }
    $month = isset($_POST['month']) ? $_POST['month'] : '*';
    $day = isset($_POST['day']) ? $_POST['day'] : '*';
    $write = isset($_POST['write']) ? $_POST['write'] : '';
    $cron = "# Generated parity check schedule:\n$hour $dotm $month $day $term/usr/local/sbin/mdcmd check $write &> /dev/null\n\n";
  }
  parse_cron_cfg("dynamix", "parity-check", $cron);
  unlink($memory);
} else {
  file_put_contents($memory, http_build_query($_POST));
  $save = false;
}
?>