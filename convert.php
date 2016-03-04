<?php

namespace Drakkar;

require_once 'vendor/autoload.php';

$opt = getopt('v:dt'); // TODO zdokumentovat

$k = new Konvertor;
if(isset($opt['d'])) $k->debug(true);
if(isset($opt['t'])) $k->zachovatTagy(true);
foreach(glob('in/*.html') as $f) {
  preg_match('@_(\d\d)_@', basename($f), $m);
  $vydani = $m[1];
  if(isset($opt['v']) && $opt['v'] != $vydani) continue;
  $k->preved($f, 'out/' . $vydani);
}
