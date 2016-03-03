<?php

namespace Drakkar;

require_once 'vendor/autoload.php';

use Sunra\PhpSimple\HtmlDomParser;

$html = HtmlDomParser::file_get_html('drakkar_2016_54_unor.html'); // TODO

foreach($html->find('[class^=Z-hlav]') as $e) {
  if(!preg_match('@Z-hlav--.-titul@', $e->class)) continue; // skip not head titles, parser cannot do multiple attribute selectors

  $nadpis = $e;
  $e = $e->parent();
  $c = new Clanek;

  $c->hlavicka['Title'] = strtr($nadpis->innertext, ['<br>' => ' ']);
  $nadpis->outertext = '';

  $rubriky = [];
  foreach($e->find('[class$=-rubrika]') as $re) {
    $text = trim($re->innertext);
    if(strpos($text, "\t") !== false)
      $rubriky = array_merge($rubriky, explode("\t", $text));
    else
      $rubriky[] = $text;
    $re->outertext = '';
    // ukončit, aby se nenačetly elementy později v textu
    $next = $re->next_sibling();
    if(!preg_match('@-rubrika$@', $next->class)) break;
  }
  // rozdělení stylu "rubrika" na autory a tagy
  foreach($rubriky as $r) {
    $r = preg_replace('@^napsala?\s+|^připravila?\s+@', '', $r, 1, $pocet);
    if($pocet > 0)
      $c->hlavicka['Authors'][] = $r;
    else
      $c->hlavicka['Tags'][] = $r;
  }

  // redukce tagů na markdown
  $p = new Prekladac;
  $text = $p->preloz($e);
  //echo $text, "\n\n\n\n\n\n";

  $c->text = $text;
  $c->zapisDoSlozky('out');
}
