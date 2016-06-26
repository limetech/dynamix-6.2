<?PHP
/* Copyright 2005-2016, Lime Technology
 * Copyright 2012-2016, Bergware International.
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
require_once 'Wrappers.php';

$dynamix = parse_plugin_cfg('dynamix',true);
if (strpos($_POST['log'],'*')===false) @unlink("{$dynamix['notify']['path']}/archive/{$_POST['log']}"); else array_map('unlink',glob("{$dynamix['notify']['path']}/archive/{$_POST['log']}",GLOB_NOSORT));
?>
