<?php

ini_set('memory_limit', '2048M');
ini_set('max_execution_time', 60 * 10);

const NO_OF_LOGS = 10000000;

$dataFile = fopen("data.txt", "w");

echo "Creating data file...";

for ($i = 0; $i < NO_OF_LOGS; $i++) {
    fwrite($dataFile, substr(str_shuffle(md5(microtime())), 0, rand(1, 32)) . "\n");
}

fclose($dataFile);

echo "Done!";
