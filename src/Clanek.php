<?php

namespace Drakkar;

class Clanek {

  public
    $doplnky,
    $hlavicka,
    $text;

  /**
   * @return text článku v markdownu vč. front matter
   */
  function md($slozka = null) {
    $out = '';

    $hlavicky = $this->hlavicka;
    $hlavicky['Fulltext'] = 'yes';
    $out .= "---\n";
    foreach($hlavicky as $pole => $hodnota) {
      if($pole == 'Title')    $hodnota = '"' . $hodnota . '"';
      if(is_array($hodnota))  $hodnota = implode(', ', $hodnota);
      $out .= "$pole: $hodnota\n";
    }
    $out .= "---\n";

    $out .= $this->text;

    if($this->doplnky) {
      $out .= "\n\n---\n";
      foreach($this->doplnky as $doplnek) {
        if($doplnek instanceof Obrazek) {
          $cil = strtolower(basename($doplnek->cesta));
          $cil = strtr($cil, ['.jpeg' => '.jpg']);
          $out .= "![]($cil)\n\n";
          if($slozka) {
            copy($doplnek->cesta, $slozka . '/' . $cil);
          }
        } else {
          $out .= $doplnek . "\n\n";
        }
      }
    }

    return $out;
  }

  function url() {
    $nazev = iconv('utf-8', 'Windows-1250//IGNORE', $this->hlavicka['Title']);
    $nazev = strtr(
      $nazev,
      iconv('utf-8', 'Windows-1250',  "ÁÄČÇĎÉĚËÍŇÓÖŘŠŤÚŮÜÝŽáäčçďéěëíňóöřšťúůüýž"),
                                      "aaccdeeeinoorstuuuyzaaccdeeeinoorstuuuyz"
    );
    $nazev = preg_replace('@[^a-zA-Z0-9\-]+@', '-', $nazev);
    $nazev = trim($nazev, '-');
    $nazev = strtolower($nazev);
    return $nazev;
  }

  function zapisDoSlozky($slozka) {
    if(!is_dir($slozka) && !mkdir($slozka)) throw new Exception('složka neexistuje a nejde ani vytvořit');
    if(!is_writeable($slozka)) throw new Exception('do složky nelze zapsat');
    file_put_contents(
      $slozka . '/' . $this->url() . '.md',
      $this->md($slozka)
    );
  }

}
