<?php

namespace Drakkar;

class Clanek {

  public
    $doplnky,
    $hlavicka,
    $text;

  protected function doplnkyMd($slozka = null) {
    if(!$this->doplnky) return '';

    $out = "\n\n---\n\n";
    foreach($this->doplnky as $doplnek) {
      if($doplnek instanceof Obrazek) {
        $i = pathinfo($doplnek->cesta);
        $nazev = $this->urlPreved($i['filename']);
        $pripona = strtr($i['extension'], ['jpeg' => 'jpg']);
        $cil = "$nazev.$pripona";
        $out .= "![]($cil)\n\n";
        if($slozka) {
          copy($doplnek->cesta, $slozka . '/' . $cil);
        }
      } else {
        $out .= $doplnek . "\n\n";
      }
    }
    return $out;
  }

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

    $out .= $this->doplnkyMd($slozka);

    return $out;
  }

  function url() {
    return $this->urlPreved($this->hlavicka['Title']);
  }

  protected function urlPreved($r) {
    $sDia   = "ÁÄČÇĎÉĚËÍŇÓÖŘŠŤÚŮÜÝŽáäčçďéěëíňóöřšťúůüýž";
    $bezDia = "aaccdeeeinoorstuuuyzaaccdeeeinoorstuuuyz";
    $r = iconv('utf-8', 'Windows-1250//IGNORE', $r);
    $r = strtr($r, iconv('utf-8', 'Windows-1250', $sDia), $bezDia);
    $r = preg_replace('@[^a-zA-Z0-9\-]+@', '-', $r);
    $r = trim($r, '-');
    $r = strtolower($r);
    return $r;
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
