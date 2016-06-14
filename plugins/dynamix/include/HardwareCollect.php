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
$var = parse_ini_file('state/var.ini');
$unraid = parse_ini_file('/etc/unraid-version');
$keyfile = trim(base64_encode(@file_get_contents($var['regFILE'])));
$hwxml = shell_exec('lshw -xml -sanitize -quiet');

$json = json_encode([
  'osversion' => $unraid['version'],
  'keyfile' => $keyfile,
  'hwxml' => $hwxml
]);

header('Content-Type: application/json');
die($json);
?>