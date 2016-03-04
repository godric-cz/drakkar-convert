<?php

namespace Drakkar;

use Sunra\PhpSimple\HtmlDomParser;
use Drakkar\Postproces\Postprocesor;

class Konvertor {

  private
    $debug = false,
    $prekladac;

  function __construct() {
    $this->prekladac = new Prekladac;
  }

  function debug(/* variadic */) {
    if(func_num_args() == 1)
      $this->debug = func_get_arg(0);
    else
      return $this->debug;
  }

  function preved($vstupniHtmlSoubor, $vystupniSlozka) {
    $html = HtmlDomParser::file_get_html($vstupniHtmlSoubor);

    $souboryClanku = [];
    foreach($html->find('[class^=Z-hlav]') as $e) {
      if(!preg_match('@Z-hlav--.-titul@', $e->class)) continue; // skip not head titles, parser cannot do multiple attribute selectors

      $nadpis = $e;
      $e = $e->parent();
      $c = new Clanek;

      $c->hlavicka['Title'] = strtr(html_entity_decode($nadpis->innertext), ['<br>' => ' ', '<br />' => ' ']);
      $nadpis->outertext = '';

      // rozdělení stylu "rubrika" na autory a tagy
      $rubriky = $this->rubriky($e);
      foreach($rubriky as $r) {
        $r = preg_replace('@^napsala?\s+|^připravila?\s+@', '', $r, 1, $pocet);
        if($pocet > 0)
          $c->hlavicka['Authors'][] = $r;
        else
          $c->hlavicka['Tags'][] = $r;
      }

      // redukce tagů na markdown
      $text = $this->prekladac->preloz($e);
      $c->text = $text;

      // obrázky a poznámky
      $dalsi = $e;
      while($dalsi = $dalsi->next_sibling()) {
        $class = $dalsi->class;
        if($class == 'marginalie') {
          continue;
        } elseif(strpos($class, 'Obr-zek-') === 0) {
          $src = urldecode($dalsi->find('img', 0)->src);
          $obrazek = new Obrazek;
          $obrazek->cesta = dirname($vstupniHtmlSoubor) . '/' . $src;
          $c->doplnky[] = $obrazek;
        } elseif(strpos($class, 'Sidebar-') === 0) {
          $c->doplnky[] = '<div class="sidebar">' . trim($dalsi->innertext) . '</div>';
        } else {
          break;
        }
      }

      if($this->debug) echo $c->md(), "\n\n\n\n\n\n";

      $c->zapisDoSlozky($vystupniSlozka);
      $souboryClanku[] = $c->url() . '.md'; // TODO lépe nějaká třída kolekce článků, co to pořeší
    }

    // vytvořit seznam článků
    file_put_contents($vystupniSlozka . '/metadata.yaml',
      "---\n" .
      "pdf: (DOPLŇ) drakkar_2015_51_srpen.pdf\n" .
      "articles:\n(DOPLŇ ÚVOD)\n" .
      implode('', array_map(function($e){ return "- $e\n"; }, $souboryClanku))
    );

    // případný postprocessing
    $i = pathinfo($vstupniHtmlSoubor);
    $postprocesor = $i['dirname'] . '/' . $i['filename'] . '.php';
    if(is_file($postprocesor)) {
      $p = new Postprocesor($vystupniSlozka, $postprocesor);
      $p->spust();
    }
  }

  function zachovatTagy($set) {
    $this->prekladac->zachovatTagy($set);
  }

  /**
   * Vyhledá v elemetu článku "rubriky" (autory a tagy) a vrátí pole řetězců
   */
  protected function rubriky($e) {
    $rubriky = [];
    foreach($e->find('[class$=-rubrika]') as $re) {
      $text = html_entity_decode($re->innertext);
      $text = trim($text);
      $text = strtr($text, ['<span>2</span>' => '²']); // E² ;)
      if(strpos($text, "\t") !== false)
        $rubriky = array_merge($rubriky, explode("\t", $text));
      else
        $rubriky[] = $text;
      $re->outertext = '';
      // ukončit, aby se nenačetly elementy později v textu
      $next = $re->next_sibling();
      if(!preg_match('@-rubrika$@', $next->class)) break;
    }
    return $rubriky;
  }

}
