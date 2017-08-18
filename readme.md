# Konvertor Drakkaru na online verzi

Jednoduchý převodník pro časopis [Drakkar](http://drakkar.sk/).

- vstup: html (generované sázecím systémem)
- výstup: yaml + md (jako zdroják pro online verzi generovanou pomocí [oar](https://github.com/casopisdrakkar/oar))

## Instalace

```bash
composer install
```

## Použití

```bash
php convert.php
```

Ve výchozím nastavení je potřeba zdrojové html + resources nahrát do složky `in` a výstup se uloží do složky `out`. Součástí výstupu jsou zmenšené obrázky.

## Parametry

- __-v číslo__ – konvertovat pouze jedno konkrétní vydání
- __-d__ – debug: zobrazit mezivýsledek na stdout
- __-t__ – zachovat neznámé html tagy ve výstupu
- __-o složka__ – output: uložit výstup do určité složky (uvnitř ní se vždy vytvoří podsložky pro jednotlivá čísla. Ideální pro složku `content` z repa [casopisdrakkar/clanky](https://github.com/casopisdrakkar/clanky).)
- __-b__ – nevytváří obrázky, pouze texty

## Korekce

Výstup ze sázecího systému přiloží všechny obrázky až na konec článku. Pomocí korekcí lze přesouvat obrázky v textu kam patří automatizovaně už při generování. Stačí vytvořit v složce `in` soubor yaml s názvem odpovídajícím číslu, např. `62.yaml`. Obsah může vypadat následovně:

```yaml
obrfix:
  veda:
    - aktivní jádro.
    - neutronových hvězd.
    - Hawkingovu záření.
  pet-setkani:
    - vždycky žili.
    - chtějí se domluvit.
    - vše popíše.
```

Obrfix jen říká, že se budou přesouvat obrázky, vnořené názvy (veda) jsou části názvů článků a položky (- aktivní jádro.) jsou konce odstavců, za které se mají obrázky postupně zařadit.
