<?php

namespace Drakkar;

use Intervention\Image\ImageManagerStatic as Image;
use Intervention\Image\Exception\NotReadableException as ImageNotReadableException;

class Clanek {

  public
    $bezObrazku = false,
    $doplnky,
    $hlavicka,
    $slozka = null,
    $text;

  function bezObrazku($set) {
    $this->bezObrazku = $set;
  }

  protected function doplnkyMd() {
    if(!$this->doplnky) return '';

    $out = "\n\n---\n\n";
    foreach($this->doplnky as $doplnek) {
      if($doplnek instanceof Obrazek) {
        $i = pathinfo($doplnek->cesta);
        $cil = $this->urlPreved($i['filename']) . '.jpg';
        $out .= "![obrazek]($cil)\n\n";
        if($this->slozka && !$this->bezObrazku) {
          // TODO vysunout nastavení obrázků ven
          $constraints = function($constraint) { $constraint->upsize(); }; // jen zvětšit
          try {
            Image::make($doplnek->cesta)
              ->widen(555, $constraints)
              ->save($this->slozka . '/' . $cil, 92);
          } catch(ImageNotReadableException $e) {
            throw new \Exception('obrázek nelze přečíst: ' . $doplnek->cesta);
          }
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
  function md() {
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

    $out .= $this->doplnkyMd();

    $out = preg_replace('@[\n\s]+$@', "\n", $out);
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
    if(!is_dir($slozka) && !mkdir($slozka)) throw new \Exception('složka neexistuje a nejde ani vytvořit');
    if(!is_writeable($slozka)) throw new \Exception('do složky nelze zapsat');
    $this->slozka = $slozka;
    file_put_contents(
      $this->slozka . '/' . $this->url() . '.md',
      $this->md()
    );
  }

}
