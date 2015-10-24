<?PHP
/* Copyright 2015, Lime Technology
 * Copyright 2015, Guilherme Jardim, Eric Schultz, Jon Panozzo.
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
readfile("/usr/local/emhttp/plugins/dynamix.docker.manager/log.htm");

if ( isset( $_GET['cmd'] )) {
  $commands = urldecode(($_GET['cmd']));
  $descriptorspec = array(
        0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
        1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
        2 => array("pipe", "w")    // stderr is a pipe that the child will write to
        );

  foreach (explode(';', $commands) as $command){
    $parts = explode(" ", $command);
    $command = escapeshellcmd(realpath($_SERVER['DOCUMENT_ROOT'].array_shift($parts)));
    if (!$command) continue;
    $command .= " ".implode(" ", $parts); // should add 'escapeshellarg' here, but this requires changes in all the original arguments
    $id = mt_rand();
    $output = array();
    echo "<p class=\"logLine\" id=\"logBody\"></p>";
    echo "<script>addLog('<fieldset style=\"margin-top:1px;\" class=\"CMD\"><legend>Command:</legend>";
    echo "root@localhost:# {$command}<br>";
    echo "<span id=\"wait{$id}\">Please wait </span>";
    echo "<p class=\"logLine\" id=\"logBody\"></p></fieldset>');</script>";
    echo "<script>show_Wait({$id});</script>";
    $proc = proc_open($command." 2>&1", $descriptorspec, $pipes, '/', array());
    while ($out = fgets( $pipes[1] )) {
      $out = preg_replace("%[\t\n\x0B\f\r]+%", '', $out );
      @flush();
      echo "<script>addLog(\"" . htmlentities($out) . "\");</script>\n";
      @flush();
    }
    $retval = proc_close($proc);
    echo "<script>stop_Wait($id);</script>\n";
    $out = $retval ?  "The command failed." : "The command finished successfully!";
    echo "<script>addLog('<br><b>".$out. "</b>');</script>";
  }
}
?>
