<?PHP
/* Copyright 2015, Lime Technology
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
/* This file provides basic local setup for php cli and is
 * named in /etc/php/php.ini like this:
 * auto_prepend_file="/usr/local/emhttp/webGui/include/local_prepend.php"
 */
setlocale(LC_ALL,'en_US.UTF-8');
date_default_timezone_set(substr(readlink('/etc/localtime-copied-from'),20));
?>
