<?php

namespace Drakkar;

require_once 'vendor/autoload.php';

$k = new Konvertor;
foreach(glob('in/*.html') as $f) {
  preg_match('@_(\d\d)_@', basename($f), $m);
  $k->preved($f, 'out/' . $m[1]);
}
