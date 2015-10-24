<?PHP
/* Copyright 2015, Bergware International
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
$var = parse_ini_file("state/var.ini");
switch ($var['fsState']) {
case 'Copying':
  echo "<strong>Copying, {$var['fsCopyPrcnt']}% complete...</strong>";
  break;
case 'Clearing':
  echo "<strong>Clearing, {$var['fsClearPrcnt']}% complete...</strong>";
  break;
default:
  echo substr($var['fsState'],-3)=='ing' ? 'wait' : 'stop';
  break;
}
?>