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
// Invoke the plugin command with indicated method
function plugin($method, $arg = '') {
  exec("/usr/local/emhttp/plugins/dynamix.plugin.manager/scripts/plugin $method $arg", $output, $retval);
  if ($retval != 0) return false;
  return implode("\n", $output);
}

function make_link($method, $arg) {
  $id = basename($arg, ".plg").$method;
  $check = $method=='update' ? "" : "<input type='checkbox' onClick='document.getElementById(\"$id\").disabled=!this.checked'>";
  $disabled = $check ? " disabled" : "";
  $cmd = $method == "delete" ? "/plugins/dynamix.plugin.manager/scripts/plugin_rm&arg1=$arg" : "/plugins/dynamix.plugin.manager/scripts/plugin&arg1=$method&arg2=$arg";
  return "{$check}<input type='button' id='$id' value='{$method}' onclick='openBox(\"{$cmd}\",\"{$method} Plugin\",600,900,true)'{$disabled}>";
}

// trying our best to find an icon
function icon($name) {
// this should be the default location and name
  $icon = "plugins/{$name}/images/{$name}.png";
  if (file_exists($icon)) return $icon;
// try alternatives if default is not present
  $plugin = strtok($name, '.');
  $icon = "plugins/{$plugin}/images/{$plugin}.png";
  if (file_exists($icon)) return $icon;
  $icon = "plugins/{$plugin}/images/{$name}.png";
  if (file_exists($icon)) return $icon;
  $icon = "plugins/{$plugin}/{$plugin}.png";
  if (file_exists($icon)) return $icon;
  $icon = "plugins/{$plugin}/{$name}.png";
  if (file_exists($icon)) return $icon;
// last resort - plugin manager icon
  return "plugins/dynamix.plugin.manager/images/dynamix.plugin.manager.png";
}
?>
