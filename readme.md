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
- __-k__ – zobrazí mustr pro _korekce_

## Korekce

Výstup ze sázecího systému přiloží všechny obrázky až na konec článku. Pomocí korekcí lze přesouvat obrázky v textu kam patří automatizovaně už při generování. Stačí vytvořit v složce `in` soubor php s názvem odpovídajícím původnímu html, např. `drakkar_2015_51_srpen.php`. Obsah může vypadat následovně:

```php
$c = $v->clanek('vzducholode');
$c->obrazek('pyramids')->presunZa('tedy bylo žádoucí.');
$c->obrazek('hindenburg')->presunZa('navzájem narážejí lokty.');

$c = $v->clanek('prvni-ceta');
$c->obrazek('silhouettes')->presunZa('chráněných pancířem.');

// ...
```
