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
require_once 'include/Helpers.php';
require_once 'include/PageBuilder.php';

// Extract the 'querystring'
// variables provided by emhttp:
//   path=<path>   page path, e.g., path=Main/Disk
//   prev=<path>   prev path, e.g., prev=Main (used to deterine if page was refreshed)
extract($_GET);

// Define some paths
$docroot = $_SERVER['DOCUMENT_ROOT'];

// The current "task" is the first element of the path
$task = strtok($path, '/');

// Get the webGui configuration preferences
extract(parse_plugin_cfg("dynamix",true));

// Read emhttp status
$var     = parse_ini_file('state/var.ini');
$sec     = parse_ini_file('state/sec.ini',true);
$devs    = parse_ini_file('state/devs.ini',true);
$disks   = parse_ini_file('state/disks.ini',true);
$users   = parse_ini_file('state/users.ini',true);
$shares  = parse_ini_file('state/shares.ini',true);
$sec_nfs = parse_ini_file('state/sec_nfs.ini',true);
$sec_afp = parse_ini_file('state/sec_afp.ini',true);

// Read network settings
extract(parse_ini_file('state/network.ini',true));

// Merge SMART settings
require_once 'include/CustomMerge.php';

// Build webGui pages first, then plugins pages
$site = [];
build_pages('webGui/*.page');
foreach (glob('plugins/*', GLOB_ONLYDIR) as $plugin) {
  if ($plugin != 'plugins/dynamix') build_pages("$plugin/*.page");
}

// Here's the page we're rendering
$myPage = $site[basename($path)];
$pageroot = "{$docroot}/".dirname($myPage['file']);
$update = $display['refresh']>0 || ($display['refresh']<0 && $var['mdResync']==0);

// Giddyup
require_once 'include/DefaultPageLayout.php';
?>
