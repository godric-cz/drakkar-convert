<?php

namespace Drakkar\Postproces;

use Exception;

class Clanek {
    use MagicGetSet;

    public $cesta;
    private $text;
    public $frontMatter;
    private $obsah;
    public $doplnky = null;

    function __construct($cesta) {
        $this->cesta = $cesta;
        $this->text = file_get_contents($cesta);
        $text = $this->text;

        //
        if (!str_startswith($text, "---\n")) {
            throw new Exception('Špatná front matter');
        }
        $konecFrontMatter = strpos($text, "\n---\n", 4);
        $this->frontMatter = str_slice($text, 4, $konecFrontMatter); // TODO možná yaml decode?

        // doplňky (nemusí v článku být)
        $zacatekDoplnku = strrpos($text, "\n---\n");
        if ($zacatekDoplnku != $konecFrontMatter) {
            $this->doplnky = substr($text, $zacatekDoplnku + 5);
        }

        //
        $this->obsah = str_slice($text, $konecFrontMatter + 5, $zacatekDoplnku);
    }

    function obrazek($castNazvu) {
        $f = preg_quote($castNazvu);
        preg_match('@\n*!\[[^\]]*\]\([^\)]*' . $f . '[^\)]*\)@', $this->text, $m, PREG_OFFSET_CAPTURE, strrpos($this->text, "---\n\n"));
        if (!isset($m[0])) {
            throw new \Exception('nenalezen obrázek: ' . $castNazvu);
        }
        return new Kus($this->text, $m[0][1], strlen($m[0][0]));
    }

    function obrazky() {
        preg_match_all('/\n*!\[[^\]]*\]\([^\)]*\)/', $this->text, $matches, PREG_OFFSET_CAPTURE, strrpos($this->text, "---\n\n"));
        $kusy = [];
        foreach ($matches[0] as $m) {
            $kusy[] = new Kus($this->text, $m[1], strlen($m[0]));
        }
        return $kusy;
    }

    function getObsah() {
        return $this->obsah;
    }

    function setObsah($obsah) {
        $this->obsah = $obsah;

        // aktualizovat text
        // TODO stejně je tu dichotomie s "kusy" a předáváním obsahu odkazem, takže se to časem bude muset opravit
        $this->text =
            "---\n" .
            $this->frontMatter .
            "\n---\n" .
            $this->obsah;

        if ($this->doplnky) {
            $this->text .= "\n---\n" . $this->doplnky;
        }
    }

    function uloz() {
        $this->text = preg_replace('@[\n\s]+---[\n\s]+$@', "\n", $this->text);
        file_put_contents($this->cesta, $this->text);
    }
}
