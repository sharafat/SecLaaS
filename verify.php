<?php

require_once 'engine/SecLaaSLogger.php';


$logger = new SecLaaSLogger();

$id = $_POST['logId'];
$msg = $_POST["msg$id"];

if (empty($id)) {
    echo "Invalid request!";
} elseif (empty($msg)) {
    echo "There must be some text as log message!";
} else {
    $logger->verify($id, $msg);
}
