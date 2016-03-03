<?php

namespace Drakkar;

use Sunra\PhpSimple\HtmlDomParser;

class Prekladac {

  // pozor že obsah již jednou modifikovaného nelze znova modifikovat (vnitřní uzly zmizí z domu)
  // je tedy vhodné začít "nejmenšími" elementy
  // TODO v případě největší nouze přidat nějaké znovusestavení DOMu
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
    'p[class^=P--klad]'   =>  ["{{p-priklad}}@{{/p-priklad}}\n\n", 'orez' => true],
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
//     $text = strip_tags($text); // nutné zde kvůli správnému oříznutí řádků
    $text = html_entity_decode($text, ENT_HTML5, 'utf-8');
    $text = preg_replace('@^[ \t]+|[ \t]+$@m', '', $text);

    $bileznaky = html_entity_decode('&nbsp;') . '\s'; // pcre modifikátor "s" nebere v úvahu utf-8 kódované nbsp
    $text = preg_replace("@\n[$bileznaky]*\n+@", "\n\n", $text);
    //$text = $this->prelozMeta($text);
    return $text;
  }

  protected function aplikujPrepisy($dom) {
    foreach(self::$prepisy as $selector => $pravidlo) {
      foreach($dom->find($selector) as $e) {
        if(is_array($pravidlo)) {
          $orez = isset($pravidlo['orez']);
          $prepis = $pravidlo[0];
        } else {
          $orez = false;
          $prepis = $pravidlo;
        }
        // nahrazení spec. znaků v pravidle hodnotami
        if(strpos($prepis, '@href') !== false)
          $prepis = str_replace('@href', $e->href, $prepis);
        $prepis = str_replace('@', $orez ? trim($e->innertext) : $e->innertext, $prepis);
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
      '{{p-priklad}}'   =>  '<p class="sample">',
      '{{/p-priklad}}'  =>  '</p>',
    ]);
  }

}
