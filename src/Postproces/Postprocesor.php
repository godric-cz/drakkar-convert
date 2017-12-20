<?php

namespace Drakkar\Postproces;

use Symfony\Component\Yaml\Yaml;

class Postprocesor {

  private
    $postprocesor;

  function __construct($slozka, $postprocesor) {
    $this->slozka = $slozka;
    $this->postprocesor = $postprocesor;
  }

  function clanek($castNazvu) {
    foreach(glob($this->slozka . "/*$castNazvu*.md") as $f) {
      return new Clanek($f);
    }
    throw new \Exception("článek $castNazvu neexistuje");
  }

  function spust() {
    $yaml = Yaml::parse(file_get_contents($this->postprocesor));
    foreach($yaml['obrfix'] as $castNazvu => $posuny) {
      $c = $this->clanek($castNazvu);
      $obrazky = $c->obrazky();
      if(count($obrazky) != count($posuny))
        throw new \Exception("počet obrázků v článku $castNazvu (" . count($obrazky) . ") neodpovídá počtu v yaml souboru (" . count($posuny) . ")");
      foreach($obrazky as $i => $obrazek) {
        $obrazek->presunZa($posuny[$i]);
      }
      $c->uloz();
    }
  }

}

class Clanek {

  private
    $cesta,
    $text;

  function __construct($cesta) {
    $this->cesta = $cesta;
    $this->text = file_get_contents($cesta);
  }

  function obrazek($castNazvu) {
    $f = preg_quote($castNazvu);
    preg_match('@\n*!\[[^\]]*\]\([^\)]*' . $f . '[^\)]*\)@', $this->text, $m, PREG_OFFSET_CAPTURE, strrpos($this->text, "---\n\n"));
    if(!isset($m[0])) throw new \Exception('nenalezen obrázek: ' . $castNazvu);
    return new Kus($this->text, $m[0][1], strlen($m[0][0]));
  }

  function obrazky() {
    preg_match_all('/\n*!\[[^\]]*\]\([^\)]*\)/', $this->text, $matches, PREG_OFFSET_CAPTURE, strrpos($this->text, "---\n\n"));
    $kusy = [];
    foreach($matches[0] as $m) {
      $kusy[] = new Kus($this->text, $m[1], strlen($m[0]));
    }
    return $kusy;
  }

  function uloz() {
    $this->text = preg_replace('@[\n\s]+---[\n\s]+$@', "\n", $this->text);
    file_put_contents($this->cesta, $this->text);
  }

}

class Kus {

  function __construct(&$text, $zacatek, $delka) {
    $this->text = &$text;
    $this->zacatek = $zacatek;
    $this->delka = $delka;
    $this->vyraz = substr($this->text, $zacatek, $delka);
  }

  function presunZa($podretezec) {
    if(strpos($this->text, $podretezec) === false) throw new \Exception('podřetězec za který se má přesouvat nenalezen: ' . $podretezec);
    $pos = strpos($this->text, $podretezec) + strlen($podretezec);
    $novy =
      substr($this->text, 0, $pos) .  // od začátku po $pos nevčetně
      substr($this->text, $this->zacatek, $this->delka) . // původní výraz
      substr($this->text, $pos, $this->zacatek - $pos) . // od $pos včetně po začátek výrazu nevčetně
      substr($this->text, $this->zacatek + $this->delka) . // od konce výrazu nevčetně až na konec dokumentu
      '';
    $this->text = $novy;
  }

}
