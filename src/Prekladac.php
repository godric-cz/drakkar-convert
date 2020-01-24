<?php

namespace Drakkar;

use KubAT\PhpSimple\HtmlDomParser;

/**
 * Slouží k překladu vnitřního HTML textu článku na markdown.
 */
class Prekladac {
    private $zachovatTagy = false;

    // pozor že obsah již jednou modifikovaného nelze znova modifikovat (vnitřní uzly zmizí z domu)
    // je tedy vhodné začít "nejmenšími" elementy
    // TODO v případě největší nouze přidat nějaké znovusestavení DOMu
    // Operace:
    //  orez - trim na obsahu
    //  posledni - změněný přepis u elementů, po kterých následuje jiný element
    //  mezeryVen - přesunout mezery kolem obsahu před a za celý přepis
    private static $prepisy = [
        'a[href]' => '[@](@href)',
        '[class^=Z-hlav--C-titul]' => "# @\n\n",
        '[class^=Nadpis-]' => "## @\n\n",
        '[class^=Podnadpis-]' => "### @\n\n",
        '[class*=Tu-n-]' => ['__@__', 'mezeryVen' => true],
        '[class*=Kurz-va]' => ['_@_',   'mezeryVen' => true],
        'li [class^=char-style-override-]' => '',
        'ul li' => "\n* @",
        'ul' => "@\n\n",
        'ol li' => "\n1. @",
        'ol' => "@\n\n",
        'p[class^=P--klad]' => ["> @\n>\n", 'posledni' => "> @\n\n", 'orez' => true],
        'p[class=Seznam-bez-punt-k-]' => ["* @\n", 'posledni' => "* @\n\n"], // TODO mělo by být bez puntíků

        // p musí být poslední, protože modifikace inner/outertextů degraduje schopnost vyhledávat v DOMu
        'p' => "@\n\n",
    ];

    private static $znameTridyOdstavcu = [ // reg. výrazy
        'Text--l-nku',
        'Prvn--odstavec-textu',
        //'Z-hlav--.-perex', // TODO možno nějak zvýraznit
    ];

    function preloz($text) {
        $text = html_entity_decode($text, ENT_HTML5, 'utf-8');
        $e = HtmlDomParser::str_get_html($text); // znovusestavení DOMu bez odstraněných elementů

        $this->aplikujPrepisy($e);

        // výstup
        $text = $e->innertext;
        $text = preg_replace('@^<div>|</div>$@', '', $text);
        if (!$this->zachovatTagy) {
            $text = strip_tags($text);
        } // nutné zde kvůli správnému oříznutí řádků

        $nbsp = html_entity_decode('&nbsp;');

        // odstranit mezery na začátku a konci řádku
        $text = preg_replace("/^[ \\t$nbsp]+|[ \\t$nbsp]+$/um", '', $text);

        // sloučit vícenásobné mezery
        // nbsp nahrazovat mezerou jen, pokud má kolem sebe i normální mezery
        // nbsp musí být první, protože jinak by regex padl do druhého pravidla a nbsp by nebylo nalezeno
        $text = preg_replace("/[ \\t$nbsp]{2,}|[ \\t]+/um", ' ', $text);

        $bileznaky = html_entity_decode('&nbsp;') . '\s'; // pcre modifikátor "s" nebere v úvahu utf-8 kódované nbsp
        $text = preg_replace("@\n[$bileznaky]*\n+@", "\n\n", $text);
        if (!$this->zachovatTagy) {
            $text = $this->prelozMeta($text);
        }

        $text = $this->normalizujUrovenNadpisu($text);

        return $text;
    }

    function zachovatTagy(/* variadic */) {
        if (func_num_args() == 1) {
            $this->zachovatTagy = func_get_arg(0);
        } else {
            return $this->zachovatTagy;
        }
    }

    protected function aplikujPrepisy($dom) {
        foreach (self::$prepisy as $selector => $pravidlo) {
            foreach ($dom->find($selector) as $e) {
                // extra parametry pravidla
                if (is_array($pravidlo)) {
                    $orez = isset($pravidlo['orez']);
                    $posledni = isset($pravidlo['posledni']) ? $pravidlo['posledni'] : null;
                    $mezeryVen = isset($pravidlo['mezeryVen']);
                    $prepis = $pravidlo[0];
                } else {
                    $orez = false;
                    $posledni = null;
                    $mezeryVen = false;
                    $prepis = $pravidlo;
                }

                // nahrazení spec. znaků v pravidle hodnotami
        if ($posledni && ($e->next_sibling()->class != $e->class || $e->next_sibling()->innertext == '')) { // workaroud jen porovnání class místo hledání v elementech
          $prepis = $posledni;
        }
                if (strpos($prepis, '@href') !== false) {
                    $prepis = str_replace('@href', $e->href, $prepis);
                }

                $vnitrniText = $e->innertext;
                if ($orez) {
                    $vnitrniText = trim($vnitrniText);
                } elseif ($mezeryVen) {
                    preg_match('/^(\s*)(.*?)(\s*)$/', $vnitrniText, $shody);
                    $mezeryPred = $shody[1];
                    $vnitrniText = $shody[2];
                    $mezeryZa = $shody[3];
                }

                // nahradit vnitřek elementu finálním přepisem vč. vnitřního textu
                $prepis = str_replace('@', $vnitrniText, $prepis);
                if (trim($e->innertext) == '') { // prázdné elementy vypustit
                    $prepis = '';
                }
                $e->innertext = $prepis;

                // doplnit vnější mezery, pokud je třeba (implicitně odstraní i HTML tagy)
                if ($mezeryVen) {
                    $e->outertext = $mezeryPred . $prepis . $mezeryZa;
                    $e->modified = true;
                }

                // odstranění tagů kvůli snadnějšímu debugování
                if (!isset($e->modified) && ($selector != 'p' || self::in_array_preg($e->class, self::$znameTridyOdstavcu))) {
                    $e->outertext = $prepis;
                    $e->modified = true;
                }
            }
        }
    }

    protected static function in_array_preg($needle, $haystack) {
        foreach ($haystack as $e) {
            if (preg_match('@'.$e.'@', $needle)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Pokud jsou v textu nadpisy 1. úrovně, převede všechny nadpisy o úroveň níž.
     */
    protected function normalizujUrovenNadpisu($text) {
        // pokud tam je h1 (markdown)
        if (preg_match('/^# /m', $text)) {
            // převést všechny nadpisy o úroveň níž
            $text = preg_replace('/^(#+) /m', '#$1 ', $text);
        }
        return $text;
    }

    protected function prelozMeta($text) {
        return strtr($text, [
            '{{priklad}}' => '> ',
            '{{/priklad}}' => '',
        ]);
    }
}
