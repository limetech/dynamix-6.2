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
if (isset($_POST['#default'])) {
  $text = '';
  if (isset($_POST['#section'])) {
    unset($keys[$_POST['#section']]);
    foreach ($keys as $section => $block) {
      $text .= "[$section]\n";
      foreach ($block as $key => $value) $text .= "$key=\"$value\"\n";
    }
  }
  if ($text) file_put_contents($file, $text); else @unlink($file);
  $save = false;
}
?>