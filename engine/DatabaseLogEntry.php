<?php

class DatabaseLogEntry {

    public $id;
    /** @var  DateTime */
    public $logTime;
    public $encryptedLogEntry;
    public $logChain;
    public $accumulatorId;

}
