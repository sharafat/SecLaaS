<?php


class CuckooFilter {

    const MAX_KEYS = 100000;
    const NO_OF_BUCKETS = self::MAX_KEYS / 4;
    const MAX_CUCKOO_COUNT = 500;

    private $table = [];

    public function __construct() {
        for ($i = 0; $i < self::MAX_KEYS; $i++) {
            $this->table[$i] = null;
        }
    }

    public function initializeTable($data) {
        if (empty($data)) {
            return;
        }

        foreach ($data as $key => $value) {
            $this->table[$key] = $value;
        }
    }

    public function getSerializedTable() {
        return serialize($this->table);
    }

    public function printTable() {
        var_dump($this->table);
    }

    public function add($item) {
        list($index, $tag) = $this->generateIndexTagHash($item);

        $cuckooCount = 0;
        do {
            $existingBucketItem = $this->table[$index];

            if (empty($existingBucketItem)) {
                $this->table[$index] = $tag;

                return;
            }

            $index = $this->alternateIndex($index, $tag);

            $existingBucketItem = $this->table[$index];

            if (empty($existingBucketItem)) {
                $this->table[$index] = $tag;

                return;
            }

            $this->table[$index] = $tag;
            $tag = $existingBucketItem;

            $cuckooCount++;
        } while ($cuckooCount < self::MAX_CUCKOO_COUNT);

//        throw new RuntimeException("Cannot add item to cuckoo filter. Tried " . self::MAX_CUCKOO_COUNT . " times.");
    }

    public function contains($item) {
        list($index, $tag) = $this->generateIndexTagHash($item);
        $altIndex = $this->alternateIndex($index, $tag);

        return $this->table[$index] == $tag || $this->table[$altIndex] == $tag;
    }

    private function generateIndexTagHash($item) {
        $hash = $this->hash($item);

        return [$this->indexHash($hash), $this->tagHash($hash & 0xFFFFFFFF)];
    }

    private function hash($item) {
        return $this->intVal(sha1($item));
    }

    private function intVal($str) {
        $val = 0;
        foreach (str_split($str) as $char) {
            $val += ord($char);
        }

        return $val;
    }

    private function indexHash($val) {
        return $val % self::NO_OF_BUCKETS;
    }

    private function tagHash($val) {
        return $val;
    }

    private function alternateIndex($index, $tag) {
        // Note from https://github.com/efficient/cuckoofilter/blob/master/src/cuckoofilter.h:
        // NOTE(binfan): originally we use:
        // index ^ HashUtil::BobHash((const void*) (&tag), 4)) & table_->INDEXMASK;
        // now doing a quick-n-dirty way:
        // 0x5bd1e995 is the hash constant from MurmurHash2
        return $this->indexHash($index ^ ($tag * 0x5bd1e995));
    }
}
