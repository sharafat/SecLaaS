<?php

include_once 'AppLogger.php';
include_once 'DBClient.php';
include_once 'CuckooFilter.php';


interface Accumulator {

    function getAccumulatorEntry(DatabaseLogEntry $databaseLogEntry, DateTime $date);

    function store($accumulatorEntry, DateTime $date);

    function getLatestAccumulatorEntry(DateTime $date);

}


class AccumulatorEntryService implements Accumulator {

    private $accumulator;

    public function __construct() {
//        $this->accumulator = new OneWayAccumulator();
        $this->accumulator = new CuckooFilterAccumulator();
    }

    public function getAccumulatorEntry(DatabaseLogEntry $databaseLogEntry, DateTime $date) {
        return $this->accumulator->getAccumulatorEntry($databaseLogEntry, $date);
    }

    public function getLatestAccumulatorEntry(DateTime $date) {
        return $this->accumulator->getLatestAccumulatorEntry($date);
    }

    public function store($accumulatorEntry, DateTime $date) {
        $this->accumulator->store($accumulatorEntry, $date);
    }
}


class OneWayAccumulator implements Accumulator {

    private static $P = 3;
    private static $Q = 5;
    private static $X = 7;

    const N = 3 * 5;    // P * Q

    public function getAccumulatorEntry(DatabaseLogEntry $databaseLogEntry, DateTime $date) {
        $latestAccumulatorEntry = $this->getLatestAccumulatorEntry($date);
        log_debug("Latest accumulator entry: $latestAccumulatorEntry");

        if ($latestAccumulatorEntry == null || $latestAccumulatorEntry == 0 || $latestAccumulatorEntry == 1) {
            $latestAccumulatorEntry = self::$X;
            log_debug("Latest accumulator entry: $latestAccumulatorEntry");
        }

        $numericHashOfDBLogEntry = $this->numericHash($databaseLogEntry);
        log_debug("Numeric hash of Database Log Entry: $numericHashOfDBLogEntry");

        return pow($latestAccumulatorEntry, $numericHashOfDBLogEntry) % self::N;
//        return bcpowmod($latestAccumulatorEntry, $numericHashOfDBLogEntry, self::N);
    }

    private function numericHash(DatabaseLogEntry $databaseLogEntry) {
        $hash = crc32($this->getEntryAsAString($databaseLogEntry));

        return strlen("$hash") > 1 ? (int) substr("$hash", 0, 1) : $hash;
    }

    private function getEntryAsAString(DatabaseLogEntry $databaseLogEntry) {
        return $databaseLogEntry->encryptedLogEntry . $databaseLogEntry->logChain . $databaseLogEntry->logTime->getTimestamp();
    }

    public function getLatestAccumulatorEntry(DateTime $date) {
        log_debug("Retrieving latest accumulator entry for date " . $date->format('Y-m-d') . '...');

        try {
            $connection = DBClient::getConnection();

            $query
                = "select accumulatorEntry
                      from proofAccumulator
                      where entryDate = :entryDate
                      order by id desc";
            $statement = $connection->prepare($query);

            //bind parameters
            $statement->bindParam(':entryDate', $date->format('Y-m-d'));

            //bind column names
            $statement->bindColumn('accumulatorEntry', $accumulatorEntry);

            $statement->execute();
            while ($statement->fetch(PDO::FETCH_BOUND)) {
                return $accumulatorEntry;
            }
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }

        return null;
    }

    public function store($accumulatorEntry, DateTime $date) {
        try {
            $connection = DBClient::getConnection();

            $query = "insert into proofAccumulator
                      (accumulatorEntry, entryDate)
                      values(:accumulatorEntry, :entryDate)";
            $statement = $connection->prepare($query);
            $statement->bindParam(':accumulatorEntry', $accumulatorEntry);
            $statement->bindParam(':entryDate', $date->format('Y-m-d'));

            $statement->execute();

            $id = $connection->lastInsertId();
            log_debug("ProofAccumulator entry id: $id");

            return $id;
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();

            return null;
        }
    }
}


class CuckooFilterAccumulator implements Accumulator {

    /** @var  CuckooFilter */
    private $cuckooFilter;

    public function __construct() {
        $this->cuckooFilter = new CuckooFilter();

        $data = $this->getAccumulatorEntriesFromDB(new DateTime());
        $this->cuckooFilter->initializeTable($data);
    }

    private function getAccumulatorEntriesFromDB(DateTime $date) {
        log_debug("Retrieving accumulator entries for date " . $date->format('Y-m-d') . '...');

        try {
            $connection = DBClient::getConnection();

            $query = "select aed
                      from dailyAccumulatorEntries
                      where entryDate = :entryDate";
            $statement = $connection->prepare($query);

            //bind parameters
            $statement->bindParam(':entryDate', $date->format('Y-m-d'));

            //bind column names
            $statement->bindColumn('aed', $aed);

            $statement->execute();

            while ($statement->fetch(PDO::FETCH_BOUND)) {
                return unserialize($aed);
            }
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }

        return null;
    }

    function getAccumulatorEntry(DatabaseLogEntry $databaseLogEntry, DateTime $date) {
        $this->cuckooFilter->add($this->getEntryAsAString($databaseLogEntry));
    }

    function getLatestAccumulatorEntry(DateTime $date) {
        return $this->getAccumulatorEntriesFromDB($date);
    }

    function store($accumulatorEntry, DateTime $date) {
        try {
            $connection = DBClient::getConnection();

            $query = "replace into dailyAccumulatorEntries
                      (aed, entryDate)
                      values(:aed, :entryDate)";
            $statement = $connection->prepare($query);
            $statement->bindParam(':aed', $this->cuckooFilter->getSerializedTable());
            $statement->bindParam(':entryDate', $date->format('Y-m-d'));

            $statement->execute();

            log_debug("Updated accumulator entry.");
        } catch (PDOException $e) {
            echo $e->getMessage() . "\n";
            echo $e->getTraceAsString();
        }
    }

    private function getEntryAsAString(DatabaseLogEntry $databaseLogEntry) {
        return $databaseLogEntry->encryptedLogEntry . $databaseLogEntry->logChain . $databaseLogEntry->logTime->getTimestamp();
    }
}
