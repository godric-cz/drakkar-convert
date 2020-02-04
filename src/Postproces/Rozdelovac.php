<?php

namespace Drakkar\Postproces;

use Symfony\Component\Yaml\Yaml;
use \Exception;

/**
 * Umí rozřezat článek na víc samostatných menších.
 */
class Rozdelovac {
    public $rozdelovac;
    public $atributy;

    function rozdel($souborClanku, $vystupniSlozka) {
        $obsah = file_get_contents($souborClanku);
        $yaml = Yaml::parse(substr($obsah, 4, strpos($obsah, "\n---", 3) - 3));
        $text = substr($obsah, strpos($obsah, "\n---", 3) + 5);

        $rRozdelovac = '/('.$this->rozdelovac.')/m';

        // načíst část (=pracovní těla nových článků)
        $castiZdvojene = preg_split($rRozdelovac, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $casti = [];
        foreach ($castiZdvojene as $i => $cast) {
            if ($i % 2 == 0) {
                continue;
            }
            $casti[] = $castiZdvojene[$i] . $castiZdvojene[$i + 1];
        }

        // vygenerovat články
        foreach ($casti as $cast) {
            [$outYaml, $outText] = $this->nactiAtributy($yaml, $cast);

            if (empty($outYaml['title'])) {
                throw new Exception("Chybí title, nešlo by vytvořit nový soubor");
            }

            $novySoubor = $vystupniSlozka . '/' . slugify($outYaml['title']) . '.md';

            file_put_contents(
                $novySoubor,
                "---\n" .
                Yaml::dump($outYaml) .
                "---\n\n" .
                trim($outText) .
                "\n"
            );
        }

        // smazat přenesené části z původního souboru
        preg_match($rRozdelovac, $obsah, $shoda, PREG_OFFSET_CAPTURE);
        $zacatekPrvniCasti = $shoda[0][1];
        $zkracenyObsah = substr($obsah, 0, $zacatekPrvniCasti);
        file_put_contents($souborClanku, $zkracenyObsah);
    }

    private function nactiAtributy($puvodniYaml, $text) {
        $outText = $text;
        $outYaml = $puvodniYaml;

        // vygenerovat novou front matter a vyházet shody z textu
        foreach ($this->atributy as $atribut => [$vyhledavani, $vysledek]) {
            $regex = '/'.$vyhledavani.'/m';
            $shoda = null;

            preg_match($regex, $outText, $shoda);
            if (!$shoda) {
                throw new Exception("Nenalezeno '$vyhledavani'.");
            }

            // nastavit atribut podle vzoru v $vysledek
            $outYaml[$atribut] = preg_replace($regex, $vysledek, $shoda[0]);

            // odstranit původní shodu z textu
            $outText = preg_replace($regex, '', $outText, 1);
        }

        return [$outYaml, $outText];
    }
}
