<?php

class DBClient {

    private static $connection;
    
    private function __construct() {}

    public static function getConnection() {
        if (self::$connection != null) {
            return self::$connection;
        }
        
        $dbtype = "mysql";
        $dbhost = "localhost";
        $dbname = "seclaas";
        $dbuser = "root";
        $dbpass = "";

        $conn = new PDO("$dbtype:host=$dbhost;dbname=$dbname",$dbuser,$dbpass, array(PDO::ATTR_PERSISTENT => true));
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        self::$connection = $conn;

        return self::$connection;
    }
}

///**
// * Example of INSERT with transaction
// */
//try {
//    $pdo = PDOWrapper::getPDOConnection();
//    $pdo->beginTransaction();
//
//    $query = "insert into users
//              (name, email)
//              values(:name, :email)";
//    $statement = $pdo->prepare($query);
//    $statement->bindParam(':name', $name);
//    $statement->bindParam(':email', $email);
//
//    //assign values for bound parameters and insert new row
//    $name = "Name 1";
//    $email = "name1@domain.com";
//    $statement->execute();
//    $userId = $pdo->lastInsertId();
//
//    //assign different values for bound parameters and insert new row
//    $name = "Name 2";
//    $email = "name2@domain.com";
//    $statement->execute();
//    $userId = $pdo->lastInsertId();
//
//    //commit
//    $pdo->commit();
//} catch(PDOException $e) {
//    $pdo->rollBack();
//    echo $e->getMessage()."\n";
//    echo $e->getTraceAsString();
//}
//
//
///**
// * Example of SELECT without parameter binding.
// * DO NOT USE THIS.
// */
////try {
////    $pdo = PDOWrapper::getPDOConnection();
////
////    $userId = 1;
////    $query = "select name, email
////              from users
////              where user_id = $userId";
////    $statement = $pdo->prepare($query);
////
////    //bind column names
////    $statement->bindColumn('name', $name);
////    $statement->bindColumn('email', $email);
////
////    $statement->execute();
////    $noOfRows = $statement->rowCount();
////
////    while ($statement->fetch(PDO::FETCH_BOUND)) {
////        echo "$name = $email<br />";
////    }
////} catch(PDOException $e) {
////    echo $e->getMessage()."\n";
////    echo $e->getTraceAsString();
////}
//
//
///**
// * Example of SELECT with parameter binding.
// */
//try {
//    $pdo = PDOWrapper::getPDOConnection();
//
//    $query = "select name, email
//              from users
//              where user_id = :userId";
//    $statement = $pdo->prepare($query);
//
//    //bind parameters
//    $statement->bindParam(':userId', $userId);
//
//    //bind column names
//    $statement->bindColumn('name', $name);
//    $statement->bindColumn('email', $email);
//
//    $userId = 1;
//    $statement->execute();
//    while ($statement->fetch(PDO::FETCH_BOUND)) {
//        echo "$name = $email<br />";
//    }
//
//    $userId = 2;
//    $statement->execute();
//    while ($statement->fetch(PDO::FETCH_BOUND)) {
//        echo "$name = $email<br />";
//    }
//} catch(PDOException $e) {
//    echo $e->getMessage()."\n";
//    echo $e->getTraceAsString();
//}
//
//
///**
// * Example of UPDATE
// */
//try {
//    $pdo = PDOWrapper::getPDOConnection();
//    $query = "update users
//              set name = :name, email = :email
//              where user_id = :userId";
//    $statement = $pdo->prepare($query);
//    $statement->bindParam(':name', $name);
//    $statement->bindParam(':email', $email);
//    $statement->bindParam(':userId', $userId);
//
//    //assign values for bound parameters and update row
//    $name = "User 1";
//    $email = "user1@domain.com";
//    $userId = 1;
//    $statement->execute();
//    $noOfRowsUpdated = $statement->rowCount();
//
//    //assign different values for bound parameters and update new row
//    $name = "User 2";
//    $email = "user2@domain.com";
//    $userId = 2;
//    $statement->execute();
//    $noOfRowsUpdated = $statement->rowCount();
//} catch(PDOException $e) {
//    echo $e->getMessage()."\n";
//    echo $e->getTraceAsString();
//}
//
//
///**
// * Example of DELETE
// */
//try {
//    $pdo = PDOWrapper::getPDOConnection();
//    $query = "delete from users
//              where user_id = :userId";
//    $statement = $pdo->prepare($query);
//    $statement->bindParam(':userId', $userId);
//
//    //assign values for bound parameters and delete row
//    $userId = 1;
//    $statement->execute();
//    $noOfRowsDeleted = $statement->rowCount();
//
//    //assign different values for bound parameters and delete another row
//    $userId = 2;
//    $statement->execute();
//    $noOfRowsDeleted = $statement->rowCount();
//} catch(PDOException $e) {
//    echo $e->getMessage()."\n";
//    echo $e->getTraceAsString();
//}
