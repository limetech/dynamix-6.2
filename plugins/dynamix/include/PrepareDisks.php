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
@unlink('/boot/config/smart-one.cfg');
@unlink('/boot/config/smart-all.cfg');
if ($_POST['preset']=='true') @copy('/var/local/emhttp/disks.ini', '/var/tmp/disks.ini');
?>
