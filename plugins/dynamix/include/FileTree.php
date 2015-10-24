<?php
/**
 * jQuery File Tree PHP Connector
 *
 * Version 1.1.1
 *
 * @author - Cory S.N. LaViska A Beautiful Site (http://abeautifulsite.net/)
 * @author - Dave Rogers - https://github.com/daverogers/jQueryFileTree
 *
 * History:
 *
 * 1.1.1 - SECURITY: forcing root to prevent users from determining system's file structure (per DaveBrad)
 * 1.1.0 - adding multiSelect (checkbox) support (08/22/2014)
 * 1.0.2 - fixes undefined 'dir' error - by itsyash (06/09/2014)
 * 1.0.1 - updated to work with foreign characters in directory/file names (12 April 2008)
 * 1.0.0 - released (24 March 2008)
 *
 * Output a list of files for jQuery File Tree
 */

/**
 * filesystem root - USER needs to set this!
 * -> prevents debug users from exploring system's directory structure
 * ex: $root = $_SERVER['DOCUMENT_ROOT'];
 */
$root = '/';
if( !$root ) exit("ERROR: Root filesystem directory not set in jqueryFileTree.php");

$postDir = $root.(isset($_POST['dir']) ? $_POST['dir'] : '' );
if (substr($postDir, -1) != '/') {
	$postDir .= '/';
}

$filters = (array)(isset($_POST['filter']) ? $_POST['filter'] : '');

// set checkbox if multiSelect set to true
$checkbox = ( isset($_POST['multiSelect']) && $_POST['multiSelect'] == 'true' ) ? "<input type='checkbox' />" : null;

if( file_exists($postDir) ) {

	$files		= scandir($postDir);
	$returnDir	= substr($postDir, strlen($root));

	natcasesort($files);

	if( count($files) > 2 ) { // The 2 accounts for . and ..

		echo "<ul class='jqueryFileTree'>";

		// All dirs
		if ($_POST['show_parent'] == "true" ) echo "<li class='directory collapsed'>{$checkbox}<a href='#' rel='" . htmlentities(dirname($returnDir), ENT_QUOTES) . "/'>..</a></li>";
		foreach( $files as $file ) {
			if( file_exists($postDir . $file) && $file != '.' && $file != '..' ) {
				if( is_dir($postDir . $file) ) {
					$htmlRel	= htmlentities($returnDir . $file, ENT_QUOTES);
					$htmlName	= htmlentities((strlen($file) > 33) ? substr($file,0,33).'...' : $file);

					echo "<li class='directory collapsed'>{$checkbox}<a href='#' rel='" . $htmlRel . "/'>" . $htmlName . "</a></li>";
				}
			}
		}

		// All files
		foreach( $files as $file ) {
			if( file_exists($postDir . $file) && $file != '.' && $file != '..' ) {
				if( !is_dir($postDir . $file) ) {
					$htmlRel	= htmlentities($returnDir . $file, ENT_QUOTES);
					$htmlName	= htmlentities($file);
					$ext		= strtolower(preg_replace('/^.*\./', '', $file));

    				foreach ($filters as $filter) {
						if (empty($filter) | $ext==$filter) {
							echo "<li class='file ext_{$ext}'>{$checkbox}<a href='#' rel='" . $htmlRel . "'>" . $htmlName . "</a></li>";
						}
					}
				}
			}
		}

		echo "</ul>";
	}
}

?>
