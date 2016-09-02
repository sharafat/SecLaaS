<?php

include_once 'AppLogger.php';
include_once 'DBClient.php';
include_once 'VerifiableLogEntry.php';
include_once 'AccumulatorEntryService.php';


class LogVerifierService {

    private $accumulatorEntryService;
    private $cuckooFilter;

    public function __construct() {
        $this->accumulatorEntryService = new AccumulatorEntryService();
        $this->cuckooFilter = new CuckooFilter();
    }

    public function listLogs() {
        try {
            $connection = DBClient::getConnection();

//            $query = "SELECT logStorage.id AS id, logTime, encryptedLogEntry, logChain,
//                        proofAccumulator.accumulatorEntry AS accumulatorEntry, signature, ppl.accumulatorEntry AS aed
//                      FROM (logStorage, proofAccumulator) LEFT JOIN ppl
//                        ON proofAccumulator.entryDate = ppl.entryDate
//                      WHERE logStorage.accumulatorId = proofAccumulator.id
//                      ORDER BY id";
            $query = "SELECT logStorage.id AS id, logTime, encryptedLogEntry, logChain, 
                        dailyAccumulatorEntries.aed AS accumulatorEntry, signature, ppl.accumulatorEntry AS aed
                      FROM (logStorage, dailyAccumulatorEntries) LEFT JOIN ppl
                        ON dailyAccumulatorEntries.entryDate = ppl.entryDate
                      ORDER BY id";
            $statement = $connection->prepare($query);

            //bind column names
            $statement->bindColumn('id', $id);
            $statement->bindColumn('logTime', $logTime);
            $statement->bindColumn('encryptedLogEntry', $encryptedLogEntry);
            $statement->bindColumn('logChain', $logChain);
            $statement->bindColumn('accumulatorEntry', $accumulatorEntry);
            $statement->bindColumn('signature', $signature);
            $statement->bindColumn('aed', $aed);

            $statement->execute();

            $entries = [];
            while ($statement->fetch(PDO::FETCH_BOUND)) {
                $logEntry = new VerifiableLogEntry();

                $logEntry->id = $id;
                $logEntry->logTime = $logTime;
                $logEntry->encryptedLogEntry = $encryptedLogEntry;
                $logEntry->logChain = $logChain;
                $logEntry->accumulatorEntry = $accumulatorEntry;
                $logEntry->signature = $signature;
                $logEntry->accumulatorEntryD = $aed;

                $entries[] = $logEntry;
            }

            return $entries;
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }

        return null;
    }

    public function findLogById($id) {
        try {
            $connection = DBClient::getConnection();

//            $query = "SELECT logStorage.id AS id, logTime, encryptedLogEntry, logChain,
//                        proofAccumulator.accumulatorEntry AS accumulatorEntry, signature, ppl.accumulatorEntry AS aed
//                      FROM (logStorage, proofAccumulator) LEFT JOIN ppl
//                        ON proofAccumulator.entryDate = ppl.entryDate
//                      WHERE logStorage.accumulatorId = proofAccumulator.id
//                        AND logStorage.id = :id
//                      ORDER BY id";
            $query = "SELECT logStorage.id AS id, logTime, encryptedLogEntry, logChain, 
                        dailyAccumulatorEntries.aed AS accumulatorEntry, signature, ppl.accumulatorEntry AS aed
                      FROM (logStorage, dailyAccumulatorEntries) LEFT JOIN ppl
                        ON dailyAccumulatorEntries.entryDate = ppl.entryDate
                      WHERE logStorage.id = :id
                      ORDER BY id";
            $statement = $connection->prepare($query);

            //bind parameters
            $statement->bindParam(':id', $id);

            //bind column names
            $statement->bindColumn('id', $id);
            $statement->bindColumn('logTime', $logTime);
            $statement->bindColumn('encryptedLogEntry', $encryptedLogEntry);
            $statement->bindColumn('logChain', $logChain);
            $statement->bindColumn('accumulatorEntry', $accumulatorEntry);
            $statement->bindColumn('signature', $signature);
            $statement->bindColumn('aed', $aed);

            $statement->execute();

            while ($statement->fetch(PDO::FETCH_BOUND)) {
                $logEntry = new VerifiableLogEntry();

                $logEntry->id = $id;
                $logEntry->logTime = $logTime;
                $logEntry->encryptedLogEntry = $encryptedLogEntry;
                $logEntry->logChain = $logChain;
                $logEntry->accumulatorEntry = $accumulatorEntry;
                $logEntry->signature = $signature;
                $logEntry->accumulatorEntryD = $aed;

                return $logEntry;
            }
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }

        return null;
    }

    public function verifyLog(VerifiableLogEntry $logEntry) {
        log_debug("Verifying PPL...");

        if (!$this->verify($logEntry->accumulatorEntry, new DateTime($logEntry->logTime), $logEntry->signature)) {
            log_debug("PPL signature verification failed. Aborting...");

            return;
        }

        log_debug("PPL verification successful.");

        log_debug("Verifying Integrity of log...");

        if (!$this->verifyIntegrity($logEntry, new DateTime($logEntry->logTime))) {
            log_debug("Log integrity verification failed. Aborting...");

            return;
        }

        log_debug("Log integrity verification successful.");

        log_debug("Verifying sequence...");

        $logChain0 = $logEntry->logChain;
        $otherLogEntry = $this->findLogById($logEntry->id + 1);
        if (empty($otherLogEntry)) {
            $otherLogEntry = $this->findLogById($logEntry->id - 1);
            $encryptedLogEntry = $logEntry->encryptedLogEntry;
            if ($this->hash($encryptedLogEntry, $otherLogEntry->logChain) != $logEntry->logChain) {
                log_debug("Sequence verification failed. Aborting...");

                return;
            }
        } else {
            $encryptedLogEntry1 = $otherLogEntry->encryptedLogEntry;
            if ($this->hash($encryptedLogEntry1, $logChain0) != $otherLogEntry->logChain) {
                log_debug("Sequence verification failed. Aborting...");

                return;
            }
        }

        log_debug("Sequence verification successful.");

        log_debug("Log is authentic!");
    }

    public function sign($accumulatorEntry, DateTime $now) {
        $plainText = $accumulatorEntry . " " . $now->format('Y-m-d');

        $rsa = new Crypt_RSA();
        $rsa->loadKey(file_get_contents(__DIR__ . '/../keys/csp/private.key'));
        $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);

        return $rsa->sign($plainText);
    }

    private function verify($accumulatorEntryD, DateTime $date, $signature) {
        $plainText = $accumulatorEntryD . " " . $date->format('Y-m-d');

        $rsa = new Crypt_RSA();
        $rsa->loadKey(file_get_contents(__DIR__ . '/../keys/csp/public.key'));
        $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);

        return $rsa->verify($plainText, $signature);
    }

    public function hash($encryptedLogEntry, $logChain) {
        log_debug("Hashing log chain...");

        return md5($encryptedLogEntry . $logChain);
    }

    private function verifyIntegrity($verifiableLogEntry, DateTime $date) {
        $this->cuckooFilter->initializeTable($this->accumulatorEntryService->getLatestAccumulatorEntry($date));

        return $this->cuckooFilter->contains($this->getEntryAsAString($verifiableLogEntry));
    }

    private function getEntryAsAString(VerifiableLogEntry $verifiableLogEntry) {
        $logEntry = new DatabaseLogEntry();
        $logEntry->encryptedLogEntry = $verifiableLogEntry->encryptedLogEntry;
        $logEntry->logChain = $verifiableLogEntry->logChain;
        $logEntry->logTime = new DateTime($verifiableLogEntry->logTime);

        return $logEntry->encryptedLogEntry . $logEntry->logChain . $logEntry->logTime->getTimestamp();
    }
}
