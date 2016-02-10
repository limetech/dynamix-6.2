<?PHP
$cmd  = isset($_POST['cmd']) ? $_POST['cmd'] : 'load';
$path = $_POST['path'];
$file = rawurldecode($_POST['filename']);
$temp = "/var/tmp";

switch ($cmd) {
case 'load':
  if (isset($_POST['filedata'])) {
    exec("rm -f $temp/*.png");
    $result = file_put_contents("$temp/$file", base64_decode(str_replace(['data:image/png;base64,',' '],['','+'],$_POST['filedata'])));
  }
  break;
case 'save':
  exec("mkdir -p $path");
  $result = @rename("$temp/$file", "$path/{$_POST['output']}");
  break;
case 'delete':
  exec("rm -f $path/$file");
  $result = true;
  break;
}
echo ($result ? 'OK 200' : 'Internal Error 500');
?>
