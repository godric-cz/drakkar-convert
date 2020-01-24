<?php

function logger(...$args) {
    fwrite(STDERR, sprintf(...$args) . PHP_EOL);
}
