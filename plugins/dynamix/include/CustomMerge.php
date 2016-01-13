<?PHP
/* Copyright 2015, Bergware International.
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
// Merge SMART settings
$smartONE = '/boot/config/smart-one.cfg';
$smartALL = '/boot/config/smart-all.cfg';
if (file_exists($smartONE)) $disks = array_merge_recursive($disks, parse_ini_file($smartONE,true));
if (file_exists($smartALL)) $var = array_merge($var, parse_ini_file($smartALL));
?>
