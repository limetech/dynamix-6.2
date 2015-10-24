<?PHP
$cmd  = isset($_POST['cmd']) ? $_POST['cmd'] : 'load';
$path = $_POST['path'];
$file = rawurldecode($_POST['filename']);
$temp = "/var/tmp";

switch ($cmd) {
case 'load':
  if (isset($_POST['filedata'])) {
    exec("rm -f $temp/*.png");
    $result = file_put_contents("$temp/$file", base64_decode(str_replace(array('data:image/png;base64,',' '),array('','+'),$_POST['filedata'])));
  }
  break;
case 'save':
  exec("mkdir -p $path");
  if (isset($_POST['flash'])) @copy("$temp/$file", $_POST['flash']);
  $result = @rename("$temp/$file", "$path/{$_POST['output']}.png");
  break;
case 'delete':
  @unlink("$path/{$_POST['output']}.png");
  $result = true;
  break;
case 'reset':
  if (isset($_POST['flash'])) @unlink($_POST['flash']);
  $result = @copy("$path/$file", "$path/{$_POST['output']}.png");
  break;
}
echo ($result ? '200 OK' : '500 Internal Error');
?>
