<?php

function logger(...$args) {
    fwrite(STDERR, sprintf(...$args) . PHP_EOL);
}

function str_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}

function slugify($r) {
    $sDia = "ÁÄČÇĎÉĚËÍŇÓÖŘŠŤÚŮÜÝŽáäčçďéěëíňóöřšťúůüýž";
    $bezDia = "aaccdeeeinoorstuuuyzaaccdeeeinoorstuuuyz";
    $ilegalni = [html_entity_decode('&shy;')];

    $r = strtr($r, array_fill_keys($ilegalni, ''));
    $r = iconv('utf-8', 'Windows-1250//IGNORE', $r);
    $r = strtr($r, iconv('utf-8', 'Windows-1250', $sDia), $bezDia);
    $r = preg_replace('@[^a-zA-Z0-9\-]+@', '-', $r);
    $r = trim($r, '-');
    $r = strtolower($r);

    return $r;
}
