<?PHP
// PHP encrypt decrypt using base64
// v1 (2013-04-14)
// http://www.geocontext.org/publ/2013/04/base64_encrypt_decrypt/
// Krystian Pietruszka
// www.geocontext.org
// info@geocontext.org
// Public domain license

// Adapted by Bergware for use in unRAID
// forced use of hash key

/*
Example encrypt:
  base64_encrypt('AuthPass');

Example decrypt:
  base64_decrypt('AuthPass');
*/

$abc = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
$key = 'unraid#hash#key';

function base64_encrypt($pw) {
  global $abc, $key;
  $s = '';
  $a = str_split('+/='.$abc);
  $b = str_split(mixing_key(strrev('-_='.$abc), $key));
  $t = str_split(base64_encode(substr(md5($key),0,16).$pw));
  for ($i=0; $i<count($t); $i++) for ($j=0; $j<count($a); $j++) {
    if ($t[$i]==$a[$j]) $s .= $b[$j];
  }
  return $s;
}

function base64_decrypt($pw) {
  global $abc, $key;
  $s = '';
  $a = str_split('+/='.$abc);
  $b = str_split(mixing_key(strrev('-_='.$abc), $key));
  $t = str_split($pw);
  for ($i=0; $i<count($t); $i++) for ($j=0; $j<count($b); $j++) {
    if ($t[$i]==$b[$j]) $s .= $a[$j];
  }
  $s = base64_decode($s);
  // return decrypted or plain password (backward compability)
  return substr($s,0,16)==substr(md5($key),0,16) ? substr($s,16) : $pw;
}

function mixing_key($b, $key) {
  $s = '';
  $c = $b;
  $t = str_split($b);
  $k = str_split(sha1($key));
  for ($i=0; $i<count($k); $i++) for ($j=0; $j<count($t); $j++) {
    if ($k[$i]==$t[$j]) {
      $c = str_replace($t[$j],'',$c);
      if (!preg_match('/'.$t[$j].'/',$s)) $s .= $t[$j];
    }
  }
  return $c.$s;
}
?>
