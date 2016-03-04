<?php

namespace Drakkar;

use Sunra\PhpSimple\HtmlDomParser;

class Prekladac {

  private
    $zachovatTagy = false;

  // pozor že obsah již jednou modifikovaného nelze znova modifikovat (vnitřní uzly zmizí z domu)
  // je tedy vhodné začít "nejmenšími" elementy
  // TODO v případě největší nouze přidat nějaké znovusestavení DOMu
  // Operace:
  //  orez - trim na obsahu
  //  posledni - změněný přepis u elementů, po kterých následuje jiný element
  private static $prepisy = [
    'a[href]'             =>  '[@](@href)',
    '[class^=Nadpis-]'    =>  "# @ \n\n",
    '[class^=Podnadpis-]' =>  "## @\n\n",
    '[class*=Tu-n-]'      =>  '__@__',
    '[class*=Kurz-va]'    =>  '_@_',
    'li [class^=char-style-override-]' =>  '',
    'ul li'               =>  "\n* @",
    'ul'                  =>  "@\n\n",
    'ol li'               =>  "\n1. @",
    'ol'                  =>  "@\n\n",
    'p[class^=P--klad]'   =>  ["{{priklad}}@{{/priklad}}\n\n", 'orez' => true],
    'p[class=Seznam-bez-punt-k-]' =>  ["* @\n", 'posledni' => "* @\n\n"], // TODO mělo by být bez puntíků
     // p musí být poslední, protože modifikace inner/outertextů degraduje schopnost vyhledávat v DOMu
    'p'                   =>  "@\n\n",
  ];

  private static $znameTridyOdstavcu = [ // reg. výrazy
    'Text--l-nku',
    'Prvn--odstavec-textu',
    'Z-hlav--.-perex', // TODO možno nějak zvýraznit
  ];

  function preloz($text) {
    $e = HtmlDomParser::str_get_html($text); // znovusestavení DOMu bez odstraněných elementů

    $this->aplikujPrepisy($e);

    // výstup
    $text = $e->innertext;
    $text = preg_replace('@^<div>|</div>$@', '', $text);
    if(!$this->zachovatTagy) $text = strip_tags($text); // nutné zde kvůli správnému oříznutí řádků
    $text = html_entity_decode($text, ENT_HTML5, 'utf-8');
    $text = preg_replace('@^[ \t]+|[ \t]+$@m', '', $text);

    $bileznaky = html_entity_decode('&nbsp;') . '\s'; // pcre modifikátor "s" nebere v úvahu utf-8 kódované nbsp
    $text = preg_replace("@\n[$bileznaky]*\n+@", "\n\n", $text);
    if(!$this->zachovatTagy) $text = $this->prelozMeta($text);
    return $text;
  }

  function zachovatTagy(/* variadic */) {
    if(func_num_args() == 1)
      $this->zachovatTagy = func_get_arg(0);
    else
      return $this->zachovatTagy;
  }

  protected function aplikujPrepisy($dom) {
    foreach(self::$prepisy as $selector => $pravidlo) {
      foreach($dom->find($selector) as $e) {
        // extra parametry pravidla
        if(is_array($pravidlo)) {
          $orez = isset($pravidlo['orez']);
          $posledni = isset($pravidlo['posledni']) ? $pravidlo['posledni'] : null;
          $prepis = $pravidlo[0];
        } else {
          $orez = false;
          $posledni = null;
          $prepis = $pravidlo;
        }
        // nahrazení spec. znaků v pravidle hodnotami
        if($posledni && ($e->next_sibling()->class != $e->class || $e->next_sibling()->innertext == '')) // workaroud jen porovnání class místo hledání v elementech
          $prepis = $posledni;
        if(strpos($prepis, '@href') !== false)
          $prepis = str_replace('@href', $e->href, $prepis);
        $prepis = str_replace('@', $orez ? trim($e->innertext) : $e->innertext, $prepis);
        if(trim($e->innertext) == '') // prázdné elementy vypustit
          $prepis = '';
        $e->innertext = $prepis;
        // odstranění tagů kvůli snadnějšímu debugování
        if(!isset($e->modified) && ($selector != 'p' || self::in_array_preg($e->class, self::$znameTridyOdstavcu))) {
          $e->outertext = $prepis;
          $e->modified = true;
        }
      }
    }
  }

  protected static function in_array_preg($needle, $haystack) {
    foreach($haystack as $e) if(preg_match('@'.$e.'@', $needle)) return true;
    return false;
  }

  protected function prelozMeta($text) {
    return strtr($text, [
      '{{priklad}}'   =>  '<p class="sample">',
      '{{/priklad}}'  =>  '</p>',
    ]);
  }

}
