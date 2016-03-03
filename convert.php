<?php

namespace Drakkar;

require_once 'vendor/autoload.php';

use Sunra\PhpSimple\HtmlDomParser;

$htmlSoubor = 'in/drakkar_2016_54_unor.html';
$slozkaVystup = 'out/drakkar_2016_54_unor';

$html = HtmlDomParser::file_get_html($htmlSoubor);

$souboryClanku = [];
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
  $c->text = $text;
  //echo $text, "\n\n\n\n\n\n";

  // obrázky a poznámky
  $dalsi = $e;
  while($dalsi = $dalsi->next_sibling()) {
    $class = $dalsi->class;
    if($class == 'marginalie') {
      continue;
    } elseif(strpos($class, 'Obr-zek--bez-r-mu-') === 0) {
      $src = $dalsi->find('img', 0)->src;
      $obrazek = new Obrazek;
      $obrazek->cesta = dirname($htmlSoubor) . '/' . $src;
      $c->doplnky[] = $obrazek;
    } elseif(strpos($class, 'Sidebar-') === 0) {
      $c->doplnky[] = '<div class="sidebar">' . trim($dalsi->innertext) . '</div>';
    } else {
      break;
    }
  }

  $c->zapisDoSlozky($slozkaVystup);
  $souboryClanku[] = $c->url() . '.md'; // TODO lépe nějaká třída kolekce článků, co to pořeší
}

// vytvořit seznam článků
file_put_contents($slozkaVystup . '/metadata.yaml',
  "---\n" .
  "pdf: (DOPLŇ) drakkar_2015_51_srpen.pdf\n" .
  "articles:\n(DOPLŇ ÚVOD)\n" .
  implode('', array_map(function($e){ return "- $e\n"; }, $souboryClanku))
);
