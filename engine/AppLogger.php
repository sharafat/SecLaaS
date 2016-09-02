<?php

// Insert the path where you unpacked log4php
include(__DIR__ . '/../libs/apache-log4php-2.3.0/Logger.php');

// Tell log4php to use our configuration file.
Logger::configure(__DIR__ . '/AppLoggerConfig.xml');

// Fetch a logger, it will inherit settings from the root logger
$log = Logger::getLogger('myLogger');

function log_debug($msg) {
    global $log;

    $log->debug($msg);
}
