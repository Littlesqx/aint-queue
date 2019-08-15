<?php

/*
 * This file is part of the littlesqx/aint-queue.
 *
 * (c) littlesqx <littlesqx@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled.
 */

$latestLine = '';
$fp = @fopen('/tmp/anit-queue.log', 'r');
if ($fp) {
    $i = -2;
    while (1) {
        $r = fseek($fp, $i--, SEEK_END);

        if (-1 === $r) {
            fseek($fp, $i + 2, SEEK_END);
            break;
        }

        $tmp = fgetc($fp);
        if ("\n" === $tmp) {
            break;
        }
    }
    $latestLine = fgets($fp);
}

var_dump($latestLine);
