<?php

require_once 'engine/CuckooFilter.php';
require_once 'engine/BloomFilter32.php';

ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 60 * 10);

const NO_OF_LOGS = 100000;
const M = 8;
const K = 2;

function add($algo) {
    $cuckooFilter = new CuckooFilter();
    $bloomFilter = new BloomFilter32(M, K);

    $startTime = microtime(true);

    $dataFile = fopen("data.txt", "r");

    for ($i = 0; $i < NO_OF_LOGS; $i++) {
        $line = fgets($dataFile);
        if (!empty($line)) {
            $$algo->add($line);
        }
    }

    fclose($dataFile);

    $endTime = microtime(true);

    echo "$algo Add: " . ($endTime - $startTime) . " seconds<br/>";
}

function containsBestCase($algo) {
    $cuckooFilter = new CuckooFilter();
    $bloomFilter = new BloomFilter32(M, K);

    $startTime = microtime(true);

    $dataFile = fopen("data.txt", "r");

    for ($i = 0; $i < NO_OF_LOGS; $i++) {
        $line = fgets($dataFile);
        if (!empty($line)) {
            $$algo->contains($line);
        }
    }

    fclose($dataFile);

    $endTime = microtime(true);

    echo "$algo Contains Best Case: " . ($endTime - $startTime) . " seconds<br/>";
}

function containsWorstCase($algo) {
    $cuckooFilter = new CuckooFilter();
    $bloomFilter = new BloomFilter32(M, K);

    $startTime = microtime(true);

    $dataFile = fopen("data.txt", "r");

    for ($i = 0; $i < NO_OF_LOGS; $i++) {
        $line = fgets($dataFile);
        if (!empty($line)) {
            $$algo->contains($line . 'xyz');
        }
    }

    fclose($dataFile);

    $endTime = microtime(true);

    echo "$algo Contains Worst Case: " . ($endTime - $startTime) . " seconds<br/>";
}


$algo = 'cuckooFilter';
//$algo = 'bloomFilter';
add($algo);
containsBestCase($algo);
containsWorstCase($algo);
