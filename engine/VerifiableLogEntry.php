<?php

class VerifiableLogEntry {

    public $id;
    /** @var  DateTime */
    public $logTime;
    public $encryptedLogEntry;
    public $logChain;
    public $accumulatorEntry;
    public $accumulatorEntryD;
    public $signature;
}
