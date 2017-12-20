<?php

namespace Drakkar;

use Sunra\PhpSimple\HtmlDomParser;
use Drakkar\Postproces\Postprocesor;

class Konvertor {

  private
    $bezObrazku = false,
    $debug = false,
    $prekladac;

  function __construct() {
    $this->prekladac = new Prekladac;
  }

  function bezObrazku($set) {
    $this->bezObrazku = $set;
  }

  function debug(/* variadic */) {
    if(func_num_args() == 1)
      $this->debug = func_get_arg(0);
    else
      return $this->debug;
  }

  private function nactiClanky($htmlRetezec) {
    $html = HtmlDomParser::str_get_html(file_get_contents($htmlRetezec));

    $clanky = [];
    foreach($html->find('body', 0)->children() as $element) {
      try {
        $clanky[] = new Clanek($element);
      } catch(ElementNeniClanek $e) {}
    }

    return $clanky;
  }

  function preved($vstupniHtmlSoubor, $vystupniSlozka, $vydani) {
    $clanky = $this->nactiClanky($vstupniHtmlSoubor);

    // vytvoření složky pro výstup
    if(!is_dir($vystupniSlozka) && !mkdir($vystupniSlozka))
      throw new \Exception('Výstupní složka neexistuje a nejde ani vytvořit.');
    if(!is_writeable($vystupniSlozka))
      throw new \Exception('Do výstupní složky nelze zapisovat.');

    /*
      if($this->bezObrazku) $c->bezObrazku(true);

      if(strpos($c->url(), 'bezejmenny-hrdina') !== false) {
        // lepší kvalita obrázků pro bezejmenného hrdinu
        $obrazek->sirka = 1000;
        $obrazek->kvalita = 98;
      }

      if($this->debug) echo $c->md(), "\n\n\n\n\n\n";
    */

    $clankyYaml = '';
    foreach($clanky as $clanek) {
      $clanekSoubor = $clanek->url() . '.md';
      file_put_contents($vystupniSlozka . '/' . $clanekSoubor, $clanek->md());
      $clankyYaml .= "- $clanekSoubor\n";
      //$clanek->konvertujObrazky(dirname($vstupniHtmlSoubor), $vystupniSlozka);
    }

    // vytvořit yaml seznam článku ve vydání
    $pdfVerze = pathinfo($vstupniHtmlSoubor)['filename'] . '.pdf';
    file_put_contents($vystupniSlozka . '/metadata.yaml',
      "---\n" .
      "pdf: $pdfVerze\n" .
      "articles: \n- uvodni-haiku.md\n" .
      $clankyYaml
    );

    // případný postprocessing
    $i = pathinfo($vstupniHtmlSoubor);
    $postprocesor = $i['dirname'] . '/' . $vydani . '.yaml';
    if(is_file($postprocesor)) {
      $p = new Postprocesor($vystupniSlozka, $postprocesor);
      $p->spust();
    }
  }

  function zachovatTagy($set) {
    $this->prekladac->zachovatTagy($set);
  }

}
