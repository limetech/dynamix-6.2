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
$keys = parse_ini_file($_POST['#cfg'], true);
$text = "";

foreach ($_POST as $field => $value) {
  if ($field[0] == '#') continue;
  list($section,$key) = explode('_', $field, 2);
  $keys[$section][$key] = $value;
}
foreach ($keys as $section => $block) {
  $text .= "[$section]\n";
  foreach ($block as $key => $value) $text .= "$key=\"$value\"\n";
}
file_put_contents($_POST['#cfg'], $text);
?>