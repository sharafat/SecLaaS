<?php

require_once 'engine/SecLaaSLogger.php';


$logger = new SecLaaSLogger();

if (array_key_exists('createLog', $_POST)) {
    $logger->process($_POST['submitStoryInput']);
} else if (array_key_exists('createPPL', $_POST)) {
    $logger->generateProofOfPastLogs();
} else {
    echo "Invalid request!";
}
