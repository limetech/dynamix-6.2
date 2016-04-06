<?PHP
/* Copyright 2015-2016, Lime Technology
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
require_once 'webGui/include/Wrappers.php';

$memory = '/tmp/memory.tmp';
if (isset($_POST['#apply'])) {
  $cron = "";
  if ($_POST['mode']>0) {
    $time = isset($_POST['hour']) ? $_POST['hour'] : '* *';
    $dotm = isset($_POST['dotm']) ? $_POST['dotm'] : '*';
    switch ($dotm) {
    case '28-31':
      $term = '[[ $(date +%e -d +1day) -eq 1 ]] && ';
      break;
    case 'W1':
      $dotm = '*';
      $term = '[[ $(date +%e) -le 7 ]] && ';
    break;
    case 'W2':
      $dotm = '*';
      $term = '[[ $(date +%e -d -7days) -le 7 ]] && ';
      break;
    case 'W3':
      $dotm = '*';
      $term = '[[ $(date +%e -d -14days) -le 7 ]] && ';
      break;
    case 'W4':
      $dotm = '*';
      $term = '[[ $(date +%e -d -21days) -le 7 ]] && ';
      break;
    case 'WL':
      $dotm = '*';
      $term = '[[ $(date +%e -d +7days) -le 7 ]] && ';
      break;
    default:
      $term = '';
    }
    $month = isset($_POST['month']) ? $_POST['month'] : '*';
    $day = isset($_POST['day']) ? $_POST['day'] : '*';
    $write = isset($_POST['write']) ? $_POST['write'] : '';
    $cron = "# Generated parity check schedule:\n$time $dotm $month $day $term/usr/local/sbin/mdcmd check $write &> /dev/null\n\n";
  }
  parse_cron_cfg("dynamix", "parity-check", $cron);
  unlink($memory);
} else {
  file_put_contents($memory, http_build_query($_POST));
  $save = false;
}
?>