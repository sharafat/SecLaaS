<?php

require_once 'AppLogger.php';
require_once 'AccumulatorEntryService.php';
require_once 'LogVerifierService.php';
require_once 'DatabaseLogEntry.php';
require_once 'DBClient.php';
require_once __DIR__ . '/../libs/phpseclib-1.0.2/Crypt/RSA.php';


class SecLaaSLogger {

//    const CSP_PRIVATE_KEY = "MIIBPAIBAAJBANXMl7mdW5kmaN3g1eABkEzXcdKtE+Gd3fXwsOt4mEtJ4AX9tecZ/XnC/AHu+OVDzNLn0PGlToS9NloNqS3r2x8CAwEAAQJBAM73zw1Vwr2AjFX2eLTwbpOkoMB04mEv5RZX2b0psix2Y2UtHDgVl5MstZ4kFUmWPZmohT15ciduxZtSub0CmkECIQDxZVwlLgHX8CcxdT2Ep0syTEvxXangUvriPESzbb/xoQIhAOK702W+g6XIWG4GYaZZ/BwARW//vcJa9kS53K3pFhS/AiEAjGI2+URNPChkkqWs9hVYbNLkI2UmItf/IUyNY4/C3aECIQC/7SaBVceyqejRGe3HFxzlxwUATYef4cfdXUeEn23lmQIgCXZHx1xXFXMOYpSyBe4Ml6h6262u7w024UQVAR+SVgc=";
//    const CSP_PUBLIC_KEY = "MFwwDQYJKoZIhvcNAQEBBQADSwAwSAJBANXMl7mdW5kmaN3g1eABkEzXcdKtE+Gd3fXwsOt4mEtJ4AX9tecZ/XnC/AHu+OVDzNLn0PGlToS9NloNqS3r2x8CAwEAAQ==";


    /** @var AccumulatorEntryService */
    private $accumulatorEntryService;

    /** @var  LogVerifierService */
    private $logVerifierService;

    public function __construct() {
        $this->accumulatorEntryService = new AccumulatorEntryService();
        $this->logVerifierService = new LogVerifierService();
    }

    public function process($logEntry) {
        log_debug("Start processing log entry...");
        log_debug("Log Entry: $logEntry");

        $logTime = $this->getCurrentTime();
        log_debug("Log Time: " . $logTime->format("Y-m-d H:i:s.u"));

        log_debug("Encrypting log entry...");
        $encryptedLogEntry = $this->encryptLogEntry($logEntry);

        log_debug("Creating log chain...");
        $logChain = $this->logVerifierService->hash($encryptedLogEntry, $this->getPreviousLogChain());
        log_debug("New log chain: $logChain");

        $databaseLogEntry = $this->generateDatabaseLogEntry($encryptedLogEntry, $logTime, $logChain);

        $this->addEntryToLogStorage($databaseLogEntry);
//        $this->addEntryToProofAccumulator($databaseLogEntry, $logTime);
        $this->updateProofAccumulator($databaseLogEntry, $logTime);

        log_debug("Finished processing log entry.\n");
    }

    private function getCurrentTime() {
        return new DateTime();
    }

    private function encryptLogEntry($logEntry) {
        //TODO: Really encrypt if wished
        return $logEntry;
    }

    private function getPreviousLogChain() {
        $previousLogChain = "";

        try {
            $connection = DBClient::getConnection();

            $query = "SELECT logChain
                      FROM logStorage
                      ORDER BY id DESC
                      LIMIT 1";
            $statement = $connection->prepare($query);

            //bind column names
            $statement->bindColumn('logChain', $logChain);

            $statement->execute();
            while ($statement->fetch(PDO::FETCH_BOUND)) {
                $previousLogChain = $logChain;
                break;
            }
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }

        log_debug("Previous log chain: $previousLogChain");

        return $previousLogChain;
    }

    private function generateDatabaseLogEntry($encryptedLogEntry, $logTime, $logChain) {
        $databaseLogEntry = new DatabaseLogEntry();
        $databaseLogEntry->encryptedLogEntry = $encryptedLogEntry;
        $databaseLogEntry->logTime = $logTime;
        $databaseLogEntry->logChain = $logChain;

        return $databaseLogEntry;
    }

    private function addEntryToLogStorage(DatabaseLogEntry $databaseLogEntry) {
        log_debug("Adding entry to log storage...");

        try {
            $connection = DBClient::getConnection();

            $query = "insert into logStorage
                      (logTime, encryptedLogEntry, logChain)
                      values(:logTime, :encryptedLogEntry, :logChain)";
            $statement = $connection->prepare($query);

            $statement->bindParam(':logTime', $databaseLogEntry->logTime->format('Y-m-d H:i:s'));
            $statement->bindParam(':encryptedLogEntry', $databaseLogEntry->encryptedLogEntry);
            $statement->bindParam(':logChain', $databaseLogEntry->logChain);

            $statement->execute();

            $id = $connection->lastInsertId();
            log_debug("LogStorage entry id: $id");

            $databaseLogEntry->id = $id;
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }
    }

    private function addEntryToProofAccumulator(DatabaseLogEntry $databaseLogEntry, $logTime) {
        log_debug("Creating accumulator entry...");

        $accumulatorEntry = $this->accumulatorEntryService->getAccumulatorEntry($databaseLogEntry, $logTime);
        log_debug("Accumulator entry created: $accumulatorEntry");

        log_debug("Adding entry to proof accumulator...");
        $accumulatorEntryId = $this->accumulatorEntryService->store($accumulatorEntry, $logTime);

        log_debug("Updating LogStorage entry with accumulatorEntryId: $accumulatorEntryId");
        $databaseLogEntry->accumulatorId = $accumulatorEntryId;
        $this->updateDatabaseLogEntry($databaseLogEntry);
    }

    private function updateDatabaseLogEntry(DatabaseLogEntry $databaseLogEntry) {
        try {
            $connection = DBClient::getConnection();

            $query = "update logStorage
                      set accumulatorId = :accumulatorId
                      where id = :id";
            $statement = $connection->prepare($query);

            $statement->bindParam(':accumulatorId', $databaseLogEntry->accumulatorId);
            $statement->bindParam(':id', $databaseLogEntry->id);

            $statement->execute();
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }
    }

    private function updateProofAccumulator(DatabaseLogEntry $databaseLogEntry, $logTime) {
        log_debug("Updating accumulator entry...");

        $this->accumulatorEntryService->getAccumulatorEntry($databaseLogEntry, $logTime);
        $accumulatorEntry = serialize($this->accumulatorEntryService->getLatestAccumulatorEntry($logTime));
        $this->accumulatorEntryService->store($accumulatorEntry, $logTime);
        log_debug("Accumulator entry updated.");
    }

    public function generateProofOfPastLogs() {
        $now = $this->getCurrentTime();
        $latestAE = $this->accumulatorEntryService->getLatestAccumulatorEntry($now);
//        $accumulatorEntry = $latestAE;
        $accumulatorEntry = serialize($latestAE);
        $signature = $this->logVerifierService->sign($accumulatorEntry, $now);

        log_debug("Generating PPL. Date: {$now->format('Y-m-d')}, AE: $accumulatorEntry");

        $this->publishPPL($now->format('Y-m-d'), $accumulatorEntry, $signature);
    }

    private function publishPPL($date, $accumulatorEntry, $signature) {
        log_debug("Publishing PPL...");

        try {
            $connection = DBClient::getConnection();

            $query = "insert into ppl
                      (accumulatorEntry, entryDate, signature)
                      values(:accumulatorEntry, :entryDate, :signature)";
            $statement = $connection->prepare($query);

            $statement->bindParam(':accumulatorEntry', $accumulatorEntry);
            $statement->bindParam(':entryDate', $date);
            $statement->bindParam(':signature', $signature);

            $statement->execute();
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }
    }

    public function listLogs() {
        return $this->logVerifierService->listLogs();
    }

    public function verify($logId, $msg) {
        log_debug("Start verifying log entry for logId: $logId...");

        $logEntry = $this->logVerifierService->findLogById($logId);

        if (empty($logEntry)) {
            log_debug("No log entry found with ID: $logId. Aborting...");

            return;
        }

        $logEntry->encryptedLogEntry = $this->encryptLogEntry($msg);
        $this->logVerifierService->verifyLog($logEntry);
    }
}
