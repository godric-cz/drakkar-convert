<?php

function logger(...$args) {
    fwrite(STDERR, sprintf(...$args) . PHP_EOL);
}

function str_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}
