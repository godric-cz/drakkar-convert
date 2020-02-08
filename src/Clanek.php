<?php

namespace Drakkar;

use Intervention\Image\ImageManagerStatic as Image;
use \Exception;
use Intervention\Image\Exception\NotReadableException;

class Clanek {
    private $doplnky = [];
    private $hlavicky = [];
    private $obrazky = [];
    private $obsah;

    private static $poradiHlavicek = [
        'layout',
        'Title',
        'Authors',
        'Tags',
        'Color', // TODO
        'summary', // TODO není, pouze "úvodní haiku" to vypadá
    ];

    /**
     * Vytvoří článek z objektu html elementu.
     */
    function __construct($element, $globalniConfig) {
        $this->nactiAVymazHlavicky($element);
        $config = $this->nactiConfig($globalniConfig);

        $hledaneExtraDoplnky = $config['extra_doplnky'][0] ?? null;
        $this->nactiDoplnky($element, $hledaneExtraDoplnky);

        $prekladac = new Prekladac;
        $prekladac->normalizovatNadpisy = !($config['denormalizovany'] ?? false);
        $this->obsah = $prekladac->preloz($element);
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
            '<br>' => ' ',
            '<br />' => ' ',
        ]);
        $out = preg_replace('/<span[^>]*>2<\/span>/', '²', $out); // E² :)
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
        foreach ($this->obrazky as [$zdroj, $cil]) {
            $kvalita = 92;
            $sirka = 459;
            $sirkaPortrait = 300;

            // lepší kvalita obrázků pro bezejmenného hrdinu
            if (strpos($this->url(), 'bezejmenny-hrdina') !== false) {
                $kvalita = 98;
                $sirka = 1000;
            }

            try {
                $jenZvetsit = function ($constraint) {
                    $constraint->upsize();
                };

                $in = Image::make($zdrojovaSlozka . '/' . $zdroj);
                if ($in->height() > 1.3 * $in->width()) {
                    // obrázky na výšku dělat užší
                    $in->widen($sirkaPortrait, $jenZvetsit);
                } else {
                    $in->widen($sirka, $jenZvetsit);
                }

                // bíle pozadí, aby se u konvertovaných png nerozbíjela průhlednost
                $out = Image::canvas($in->width(), $in->height(), '#ffffff');
                $out->insert($in);
                $out->save($cilovaSlozka . '/' . $cil, $kvalita);
            } catch (NotReadableException $e) {
                throw new Exception("Obrázek '$zdrojovaSlozka/$zdroj' nelze přečíst.\n\nNepokazilo se kódování v názvu souboru při rozbalení archivu?");
            }
        }
    }

    /**
     * @return string text článku v markdownu vč. front matter
     */
    function md() {
        $out = "---\n";

        foreach ($this->hlavicky as $pole => $hodnota) {
            if (is_array($hodnota)) {
                $hodnota = implode(', ', $hodnota);
            }
            if (str_contains($hodnota, ':')) {
                $hodnota = '"' . $hodnota . '"';
            }
            $poleOut = strtolower($pole);
            $out .= "$poleOut: $hodnota\n";
        }

        if (strlen($this->obsah) < 1000) {
            // explicitně říct, že je to fulltext, pokud je článek krátký
            $out .= "fulltext: true\n";
        }

        $out .= "---\n\n";

        $out .= $this->obsah;

        if ($this->doplnky) {
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
     * @param $element
     * @param string? $hledanyText pokud je zadáno, bere se jako doplňek i
     *  každý element obsahující daný text (bez ohledu na css třídu)
     */
    private function nactiDoplnky($element, $hledanyText = null) {
        $dalsi = $element;

        while ($dalsi = $dalsi->next_sibling()) {
            $class = $dalsi->class;

            if ($class == 'marginalie') {
                continue;
            } elseif (strpos($class, 'Obr-zek-') === 0 || $class == 'frame-2') {
                // přeskočit obrázkové pozadí bezejmenných hrdinů
                if ($dalsi->find('img', 0)->alt == 'blackbg.png') {
                    continue;
                }

                $vstupniSoubor = urldecode($dalsi->find('img', 0)->src);
                $vystupniSoubor = slugify(substr(basename($vstupniSoubor), 0, strrpos(basename($vstupniSoubor), '.'))) . '.jpg';

                // zkusit, jestli za obrázkem není popisek
                $popisek = '';
                $nasledujici = $dalsi->next_sibling();
                if (str_startswith($nasledujici->class, 'Sidebar-') && !str_contains($this->url(), 'bezejmenny-hrdina')) {
                    $popisek = trim(strip_tags($nasledujici->innertext, '<a>'));
                    $dalsi = $dalsi->next_sibling(); // přeskočit element
                }

                $this->doplnky[] = "![$popisek]($vystupniSoubor)";
                $this->obrazky[] = [$vstupniSoubor, $vystupniSoubor]; // zapamatovat pro případnou pozdější konverzi
            } elseif (strpos($class, 'Sidebar-') === 0) {
                $this->doplnky[] =
                    '<div class="sidebar" markdown="1">' .
                    (new Prekladac)->preloz($dalsi->innertext) .
                    '</div>';
            } elseif ($hledanyText && str_contains($dalsi->innertext, $hledanyText)) {
                $this->doplnky[] = '<div markdown="1">' . (new Prekladac)->preloz($dalsi->innertext) . '</div>';
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
            'Z-hlav--.-titul|Pov-dka---nadpis' => function ($text) {
                // duplicitní titulky ponechat v textu (jsou to názvy podčlánků u článků z více částí)
                if (!empty($this->hlavicky['Title'])) {
                    return false;
                }

                $this->hlavicky['Title'] = $text;
            },

            // autoři a štítky
            '-rubrika$' => function ($text) {
                // autory po titulku ponechat v textu (článek z více částí)
                if (!empty($this->hlavicky['Title'])) {
                    return false;
                }

                // v jednom elementu může být víc položek oddělených tabem
                foreach (explode("\t", $text) as $polozka) {
                    // jestli je to štítek nebo autor se rozliší podle obsahu
                    if (preg_match('/^(napsala?|připravila?|napísala?)\s+(.*)$/i', $polozka, $shody)) {
                        $autor = $shody[2];
                        $autor = preg_match('/^\s*„([^“]+)“\s*$/', $autor, $shody2) ? $shody2[1] : $autor;
                        $this->hlavicky['Authors'][] = $autor;
                    } elseif (preg_match('/(napsali )?různí autoři/', $polozka)) {
                        $this->hlavicky['Authors'][] = 'různí autoři';
                    } else {
                        $tag = mb_strtolower($polozka);
                        $this->hlavicky['Tags'][$tag] = true;
                    }
                }
            },

            // autor u povídek
            'Pov-dka---autor' => function ($text) {
                $this->hlavicky['Tags']['povídka'] = true;
                if (preg_match('/^Přeložila?\s+(.*)/', $text, $shody)) {
                    $this->hlavicky['Tags']['překlad'] = true;
                    $this->hlavicky['Authors'][] = $shody[1];
                } elseif (preg_match('/^[\s\W]*$/', $text)) { // jen mezery a smetí
                    return false;
                } else {
                    $this->hlavicky['Authors'][] = $text;
                }
            },

            // perex
            'Z-hlav--.-perex' => function ($text) {
                $this->hlavicky['summary'] = $text;
            }

        ];

        // přečíst a vymazat elementy definované ve filtrech
        foreach ($element->children() as $potomek) {
            foreach ($filtry as $rv => $filtr) {
                if (preg_match('@' . $rv . '@', $potomek->class) && !empty($potomek->innertext)) {
                    $vysledek = $filtr(self::filtrujRadek($potomek->innertext));
                    if ($vysledek === false) {
                        continue;
                    }
                    $potomek->outertext = '';
                }
            }
        }

        if (empty($this->hlavicky['Title'])) {
            throw new ElementNeniClanek;
        }
        $this->hlavicky['layout'] = 'article';
        $this->hlavicky['Tags'] = array_keys($this->hlavicky['Tags']);

        // seřadit hlavičky
        $hlavicky = array_merge(array_flip(self::$poradiHlavicek), $this->hlavicky);
        $hlavicky = array_intersect_key($hlavicky, $this->hlavicky);
        $this->hlavicky = $hlavicky;
    }

    /**
     * Načte config článku z globálního konfigu tak, že nechá jen konfigy k
     * tomuto článku a data dá přímo k sekci konfigu (tj. eliminuje 2. level
     * klíčů ve vstupním yamlu)
     */
    private function nactiConfig($globalniConfig) {
        $url = $this->url();
        $out = [];

        foreach ($globalniConfig as $sekce => $clanky) {
            foreach ($clanky as $castUrlClanku => $config) {
                if (str_contains($url, $castUrlClanku)) {
                    $out[$sekce] = $config;
                }
            }
        }

        return $out;
    }

    function url() {
        return slugify($this->hlavicky['Title']);
    }
}

class ElementNeniClanek extends Exception {
}

function str_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}
