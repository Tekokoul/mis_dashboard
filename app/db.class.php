<?php
/**
 * Created by PhpStorm.
 * User: zen
 * Date: 2/3/2017
 * Time: 12:00 μμ
 */
#[AllowDynamicProperties]
class DB extends \PDO {
    protected $DB_SERVER;

    function __construct($settings) {
        try {
            $this->DB_SERVER = new PDO(
                $settings['db_provider'] . ":host=" . $settings['db_host'] .
                ";port=" . $settings['db_port'] .
                ";dbname=" . $settings['db_database'],
                $settings['db_user'], base64_decode($settings['db_password']), [
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $this->MQ("set names utf8mb4");
            // ATTR_EMULATE_PREPARES and ATTR_STRINGIFY_FETCHES are to get number values as numbers and not strings
            return $this->DB_SERVER;
        } catch (PDOException $e) {
            db_error("DB connection query", $e->getMessage());
//            return ;
        }
    }

    function __destruct() {
        $this->DB_SERVER = null;
    }

    //TODO add DB_LOGGING functionality
    //
    // $params: when non-empty, the query MUST use ? placeholders and the values
    // are bound rather than interpolated. Binding is the only safe way to put
    // user input into SQL; it is correct even with ATTR_EMULATE_PREPARES on
    // (PDO quotes each bound value itself). Existing callers pass no $params
    // and are unchanged.
    function MQ($query, $fetch = false, $params = []) {
        try {
            if (_DB_DEBUG_MODE) {
                debug($query);
            }
            $stmt = $this->DB_SERVER->prepare($query);
            $result = empty($params) ? $stmt->execute() : $stmt->execute(array_values($params));
            switch ($fetch) {
                case "all":
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    break;
                case "one":
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    break;
                case "last":
                    $result = $this->DB_SERVER->lastInsertId();
                    break;
            }
            $stmt = null;
            return $result;
        } catch (PDOException $e) {
            db_error($query, $e->getMessage());
        }
    }

    /**
     * A transaction on the connection MQ uses. Started through PDO, not with
     * "START TRANSACTION", so PDO knows it is open: MQ exits through
     * db_error() on any failure, the connection is persistent, and an open
     * transaction must never outlive the request that began it.
     */
    function txBegin() {
        $db = $this->DB_SERVER;
        register_shutdown_function(function () use ($db) { if ($db && $db->inTransaction()) { $db->rollBack(); } });
        return $db->beginTransaction();
    }

    function txCommit() { return $this->DB_SERVER->commit(); }

    function txRollBack() { return $this->DB_SERVER->inTransaction() ? $this->DB_SERVER->rollBack() : true; }

    function ESC($variable) {
        return $this->DB_SERVER->prepare($variable);
    }
}