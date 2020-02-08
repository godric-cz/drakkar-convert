<?php

namespace Drakkar\Postproces;

class Postprocesor {
    use MagicGetSet;

    private $slozka;
    private $config;

    function __construct($slozka, $config) {
        $this->slozka = $slozka;
        $this->config = $config;
    }

    function getClanky() {
        $clanky = glob($this->slozka . '/*.md');
        $clanky = array_filter($clanky, function ($f) {
            return basename($f) != 'index.md';
        });
        $clanky = array_map(function ($f) {
            return new Clanek($f);
        }, $clanky);
        return $clanky;
    }

    function clanek($castNazvu) {
        foreach (glob($this->slozka . "/*$castNazvu*.md") as $f) {
            return new Clanek($f);
        }
        throw new \Exception("článek $castNazvu neexistuje");
    }

    function spust() {
        $yaml = $this->config;

        $obrfix = $yaml['obrfix'] ?? [];
        foreach ($obrfix as $castNazvu => $posuny) {
            $c = $this->clanek($castNazvu);
            $obrazky = $c->obrazky();
            if (count($obrazky) != count($posuny)) {
                throw new \Exception("počet obrázků v článku $castNazvu (" . count($obrazky) . ") neodpovídá počtu v yaml souboru (" . count($posuny) . ")");
            }
            foreach ($obrazky as $i => $obrazek) {
                $obrazek->presunZa($posuny[$i]);
            }
            $c->uloz();
        }

        // automatické rozmístění obrázků
        if (!$obrfix) {
            foreach ($this->clanky as $clanek) {
                if (!$clanek->doplnky) {
                    continue;
                }
                if (str_contains(basename($clanek->cesta), 'hrdina')) {
                    continue;
                }

                $reObrazek = '/\n*!\[[^\]]*\]\([^\)]*\)/';

                $obrazky = [];
                $clanek->doplnky = preg_replace_callback($reObrazek, function ($shoda) use (&$obrazky) {
                    $obrazky[] = trim($shoda[0]);
                    return '';
                }, $clanek->doplnky);

                $autoobrazky = new Autoobrazky;
                $clanek->obsah = $autoobrazky->zarovnej($clanek->obsah, $obrazky);

                $clanek->uloz();
            }
        }

        $rozdeleni = $yaml['rozdeleni'] ?? [];
        foreach ($rozdeleni as $castNazvu => $config) {
            $c = $this->clanek($castNazvu);
            $r = new Rozdelovac;
            $r->rozdelovac = $config['delit_podle'];
            $r->atributy = $config['atributy'];
            $r->rozdel($c->cesta, dirname($c->cesta));
        }
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
        if (strpos($this->text, $podretezec) === false) {
            throw new \Exception('podřetězec za který se má přesouvat nenalezen: ' . $podretezec);
        }
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
