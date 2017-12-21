<?php

namespace Drakkar;

use Intervention\Image\ImageManagerStatic as Image;
use \Exception;

class Clanek {

  private
    $doplnky = [],
    $hlavicky = [],
    $obrazky = [],
    $obsah;

  private static $poradiHlavicek = [
    'Title',
    'Authors',
    'Tags',
    'Color', // TODO
    'Summary', // TODO není, pouze "úvodní haiku" to vypadá
    'Fulltext',
  ];

  /**
   * Vytvoří článek z objektu html elementu.
   */
  function __construct($element) {
    $this->nactiAVymazHlavicky($element);
    $this->nactiDoplnky($element);
    $this->obsah = (new Prekladac)->preloz($element);
  }

  /**
   * Převede HTML řetězec načtený z prvku v hlavičkách na uklizený utf-8
   * jednořádkový výsledek.
   */
  private static function filtrujRadek($retezec) {
    $out = $retezec;
    $out = html_entity_decode($out);
    $out = trim($out);
    $out = strtr($out, [
      '<br>'            =>  ' ',
      '<br />'          =>  ' ',
      '<span>2</span>'  =>  '²', // E² :)
    ]);
    $out = strip_tags($out);
    return $out;
  }

  /**
   * Škáluje a zkomprimuje obrázky u článku pro publikaci na webu.
   * @param string $zdrojovaSlozka složka, vůči níž jsou udány src parametry
   *  obrázků v původním html souboru.
   * @param string $cilovaSlozka složka, do níž se zkonvertované obrázky mají
   *  zapsat. Měla by být stejná jako výstup .md souboru.
   */
  function konvertujObrazky($zdrojovaSlozka, $cilovaSlozka) {
    foreach($this->obrazky as [$zdroj, $cil]) {
      $kvalita = 92;
      $sirka   = 555;

      // lepší kvalita obrázků pro bezejmenného hrdinu
      if(strpos($this->url(), 'bezejmenny-hrdina') !== false) {
        $kvalita = 98;
        $sirka   = 1000;
      }

      $jenZvetsit = function($constraint) { $constraint->upsize(); };
      Image::make($zdrojovaSlozka . '/' . $zdroj)
        ->widen($sirka, $jenZvetsit)
        ->save($cilovaSlozka . '/' . $cil, $kvalita);
    }
  }

  /**
   * @return string text článku v markdownu vč. front matter
   */
  function md() {
    $out = "---\n";

    foreach($this->hlavicky as $pole => $hodnota) {
      if($pole == 'Title')    $hodnota = '"' . $hodnota . '"';
      if(is_array($hodnota))  $hodnota = implode(', ', $hodnota);
      $out .= "$pole: $hodnota\n";
    }

    $out .= "---\n";

    $out .= $this->obsah;

    if($this->doplnky) {
      $out .= "\n\n---\n\n";
      $out .= implode("\n\n", $this->doplnky);
      $out .= "\n\n";
    }

    $out = preg_replace('@[\n\s]+$@', "\n", $out);
    return $out;
  }

  /**
   * Načte doplňky (obrázky, sidebary) k článku z elementů _následujících_
   * předanému elementu.
   */
  private function nactiDoplnky($element) {
    $dalsi = $element;

    while($dalsi = $dalsi->next_sibling()) {
      $class = $dalsi->class;

      if($class == 'marginalie') {
        continue;
      } elseif(strpos($class, 'Obr-zek-') === 0 || $class == 'frame-2') {
        if($dalsi->find('img', 0)->alt == 'blackbg.png') continue; // přeskočit obrázkové pozadí bezejmenných hrdinů

        $vstupniSoubor  = urldecode($dalsi->find('img', 0)->src);
        $vystupniSoubor = self::urlPreved(substr(basename($vstupniSoubor), 0, strrpos(basename($vstupniSoubor), '.'))) . '.jpg';

        $this->doplnky[] = "![obrazek]($vystupniSoubor)";
        $this->obrazky[] = [$vstupniSoubor, $vystupniSoubor]; // zapamatovat pro případnou pozdější konverzi
      } elseif(strpos($class, 'Sidebar-') === 0) {
        $this->doplnky[] = '<div class="sidebar">' . trim($dalsi->innertext) . '</div>';
      } else {
        break;
      }
    }
  }

  /**
   * Načte hlavičky (název, autory, ...) z elementu článku. Tato metoda
   * hlavičkové podelementy zároveň nahradí prázdnými řetězci, aby se později
   * neobjevily v textu článku.
   */
  private function nactiAVymazHlavicky($element) {

    // RV => funkce na zpcracování řádkového textu
    $filtry = [

      // titulek článku
      'Z-hlav--.-titul|Pov-dka---nadpis' => function($text) {
        // duplicitní titulky ponechat v textu (jsou to názvy podčlánků u článků z více částí)
        if(!empty($this->hlavicky['Title']))
          return false;

        $this->hlavicky['Title'] = $text;
      },

      // autoři a štítky
      '-rubrika$' => function($text) {
        // v jednom elementu může být víc položek oddělených tabem
        foreach(explode("\t", $text) as $polozka) {
          // jestli je to štítek nebo autor se rozliší podle obsahu
          if(preg_match('@^(napsala?|připravila?)\s+(.*)$@', $polozka, $shody)) {
            $this->hlavicky['Authors'][] = $shody[2];
          } else {
            $this->hlavicky['Tags'][$polozka] = true;
          }
        }
      },

      // autor u povídek
      'Pov-dka---autor' => function($text) {
        $this->hlavicky['Tags']['povídka'] = true;
        if(preg_match('/^Přeložila?\s+(.*)/', $text, $shody)) {
          $this->hlavicky['Tags']['překlad'] = true;
          $this->hlavicky['Authors'][] = $shody[1];
        } else if(preg_match('/^[\s\W]*$/', $text)) { // jen mezery a smetí
          return false;
        } else {
          $this->hlavicky['Authors'][] = $text;
        }
      },

    ];

    // přečíst a vymazat elementy definované ve filtrech
    foreach($element->children() as $potomek) {
      foreach($filtry as $rv => $filtr) {
        if(preg_match('@' . $rv . '@', $potomek->class) && !empty($potomek->innertext)) {
          $vysledek = $filtr(self::filtrujRadek($potomek->innertext));
          if($vysledek === false) continue;
          $potomek->outertext = '';
        }
      }
    }

    if(empty($this->hlavicky['Title'])) throw new ElementNeniClanek;
    $this->hlavicky['Fulltext'] = 'yes';
    $this->hlavicky['Tags'] = array_keys($this->hlavicky['Tags']);

    // seřadit hlavičky
    $hlavicky = array_merge(array_flip(self::$poradiHlavicek), $this->hlavicky);
    $hlavicky = array_intersect_key($hlavicky, $this->hlavicky);
    $this->hlavicky = $hlavicky;
  }

  function url() {
    return self::urlPreved($this->hlavicky['Title']);
  }

  private static function urlPreved($r) {
    $sDia   = "ÁÄČÇĎÉĚËÍŇÓÖŘŠŤÚŮÜÝŽáäčçďéěëíňóöřšťúůüýž";
    $bezDia = "aaccdeeeinoorstuuuyzaaccdeeeinoorstuuuyz";
    $r = iconv('utf-8', 'Windows-1250//IGNORE', $r);
    $r = strtr($r, iconv('utf-8', 'Windows-1250', $sDia), $bezDia);
    $r = preg_replace('@[^a-zA-Z0-9\-]+@', '-', $r);
    $r = trim($r, '-');
    $r = strtolower($r);
    return $r;
  }

}

class ElementNeniClanek extends Exception {}
