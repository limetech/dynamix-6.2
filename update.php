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
/* UPDATE.PHP is used to update selected name=value variables in a configuration file.
 * Note that calling this function will write the configuration file on the flash.
 * The $_POST variable contains a list of key/value parameters to be updated in the file.
 * There are a number of special parameters prefixed with a hash '#' character:
 *
 * #file    : the pathname of the file to be updated. It does not need to previously exist.
 *            If pathname is relative (no leading '/'), the configuration file will placed
 *            placed under '/boot/config/plugins'.
 *            This parameter may be omitted to perform a command execution only (see #command).
 * #section : if present, then the ini file consists of a set of named sections, and all of the
 *            configuration parameters apply to this one particular section.
 *            if omitted, then it's just a flat ini file without sections.
 * #default : if present, then the default values will be restored instead.
 * #include : specifies name of an include file to read and execute in before saving the file contents
 * #cleanup : if present then parameters with empty strings are omitted from being written to the file
 * #command : a shell command to execute after updating the configuration file
 * #arg     : an array of arguments for the shell command
 */
function write_log($string) {
  if (empty($string)) return;
  $string = str_replace("\n", "<br>", $string);
  $string = str_replace('"', "\\\"", trim($string));
  echo "<script>addLog(\"{$string}\");</script>";
  @flush();
}
// unRAID update control
readfile('update.htm');

$docroot = $_SERVER['DOCUMENT_ROOT'];
if (isset($_POST['#file'])) {
  $file = $_POST['#file'];
  // prepend with boot (flash) if path is relative
  if ($file[0]!='/') $file = "/boot/config/plugins/$file";
  $section = isset($_POST['#section']) ? $_POST['#section'] : false;
  $cleanup = isset($_POST['#cleanup']);
  $default = isset($_POST['#default']) ? @parse_ini_file("$docroot/plugins/".basename(dirname($file))."/default.cfg", $section) : array();

  $keys = @parse_ini_file($file, $section);
  // the 'save' switch can be reset by the include file to disallow settings saving
  $save = true;
  if (isset($_POST['#include'])) {
    $include = realpath($docroot.'/'.$_POST['#include']);
    if (strpos($include, $docroot) === 0) include $include; else {
      syslog(LOG_INFO, "Include file not allowed: $include. Settings not saved!");
      $save = false;
    }
  }
  if ($save) {
    $text = "";
    if ($section) {
      foreach ($_POST as $key => $value) if ($key[0]!='#') $keys[$section][$key] = isset($default[$section][$key]) ? $default[$section][$key] : $value;
      foreach ($keys as $section => $block) {
        $text .= "[$section]\n";
        foreach ($block as $key => $value) if (strlen($value) || !$cleanup) $text .= "$key=\"$value\"\n";
      }
    } else {
      foreach ($_POST as $key => $value) if ($key[0]!='#') $keys[$key] = isset($default[$key]) ? $default[$key] : $value;
      foreach ($keys as $key => $value) if (strlen($value) || !$cleanup) $text .= "$key=\"$value\"\n";
    }
    @mkdir(dirname($file));
    file_put_contents($file, $text);
  }
}
if (isset($_POST['#command'])) {
  if (isset($_POST['#env'])) {
    foreach ($_POST['#env'] as $env) putenv($env);
  }
  $command = $_POST['#command'];
  if (strpos($command, $docroot) !== 0)
    $command = "$docroot/$command";
  $command = realpath($command);
  if ($command === false)
    syslog(LOG_INFO, "Invalid #command: {$_POST['#command']}");
  else {
    $command = escapeshellcmd($command);
    if (isset($_POST['#arg'])) {
      $args = $_POST['#arg'];
      ksort($args);
      $command .= " ".implode(" ", array_map("escapeshellarg", $args));
    }
    syslog(LOG_INFO, $command);
    $proc = popen($command, 'r');
    while (!feof($proc)) {
      write_log(fgets($proc));
    }
  }
}
?>
