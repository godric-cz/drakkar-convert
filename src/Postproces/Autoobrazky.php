<?php

namespace Drakkar\Postproces;

use Exception;

/**
 * Rozloží obrázky +- rovnoměrně do textu.
 */
class Autoobrazky {
    /**
     * @param string $text
     * @param string[] $obrazky
     * @return string upravený text
     */
    function zarovnej($text, $obrazky) {
        $textPole = explode("\n\n", $text);

        // spočítat cílové (optimální) pozice obrázků v textu
        $obrazkySPozici = [];

        foreach ($obrazky as $i => $obrazek) {
            $pomernaPozice = ($i + 1) / (count($obrazky) + 1);
            $cilovaPozice = strlen($text) * $pomernaPozice;

            $obrazkySPozici[] = [$obrazek, $cilovaPozice];
        }

        $obrazkySPozici = array_reverse($obrazkySPozici);

        // skombinovat obrázky s textem
        $soucasnaPozice = 0;
        $vystupniPole = [];
        [$obrazek, $cilovaPoziceObrazku] = array_pop($obrazkySPozici);

        foreach ($textPole as $kus) {
            $vystupniPole[] = $kus;
            $soucasnaPozice += strlen($kus);

            if ($obrazek && $soucasnaPozice >= $cilovaPoziceObrazku) {
                $vystupniPole[] = $obrazek;

                [$obrazek, $cilovaPoziceObrazku] = array_pop($obrazkySPozici);
            }
        }

        // kontroly a výstup
        if ($obrazek) {
            // nepodařilo se zarovnat všechny obrázky
            throw new Nezarovnano;
        }

        return implode("\n\n", $vystupniPole);
    }
}

class Nezarovnano extends Exception {
}
