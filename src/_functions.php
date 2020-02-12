<?php

function logger(...$args) {
    fwrite(STDERR, sprintf(...$args) . PHP_EOL);
}

function logger_warning(...$args) {
    logger('Varování: ' . $args[0], ...array_slice($args, 1));
}

function str_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}

function str_startswith($haystack, $needle) {
    return strpos($haystack, $needle) === 0;
}

/**
 * substr v python slice stylu
 */
function str_slice($str, $start, $end) {
    return substr($str, $start, $end - $start);
}

function slugify($r) {
    $sDia = "ÁÄČÇĎÉĚËÍŇĽÓÖŘŠŤÚŮÜÝŽáäčçďéěëíľňóöřšťúůüýž";
    $bezDia = "aaccdeeeinloorstuuuyzaaccdeeeilnoorstuuuyz";
    $ilegalni = [html_entity_decode('&shy;')];

    $r = strtr($r, array_fill_keys($ilegalni, ''));
    $r = iconv('utf-8', 'Windows-1250//IGNORE', $r);
    $r = strtr($r, iconv('utf-8', 'Windows-1250', $sDia), $bezDia);
    $r = preg_replace('@[^a-zA-Z0-9\-]+@', '-', $r);
    $r = trim($r, '-');
    $r = strtolower($r);

    return $r;
}
