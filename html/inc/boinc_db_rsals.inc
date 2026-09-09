<?php

$d = dirname(__FILE__);
require_once("$d/db_conn.inc");
require_once("$d/util_basic.inc");

class BoincNumber {
    static function lookup_id($id) {
        $db = BoincDb::get();
        return $db->lookup_id($id, 'number', 'BoincNumber');
    }
    static function lookup($clause) {
        $db = BoincDb::get();
        return $db->lookup('number', 'BoincNumber', $clause);
    }
    static function enum($clause) {
        $db = BoincDb::get();
        return $db->enum('number', 'BoincNumber', $clause);
    }
    function update($clause) {
        $db = BoincDb::get();
        return $db->update($this, 'number', $clause);
    }
    static function insert($clause) {
        $db = BoincDb::get();
        $ret = $db->insert('number', $clause);                  
        if (!$ret) return $ret;
        return $db->insert_id();
    }
}

class BoincNumber_e {
    static function lookup_id($id) {
        $db = BoincDb::get();
        return $db->lookup_id($id, 'number_e', 'BoincNumber_e');
    }
    static function lookup($clause) {
        $db = BoincDb::get();
        return $db->lookup('number_e', 'BoincNumber_e', $clause);
    }
    static function enum($clause) {
        $db = BoincDb::get();
        return $db->enum('number_e', 'BoincNumber_e', $clause);
    }
    function update($clause) {
        $db = BoincDb::get();
        return $db->update($this, 'number_e', $clause);
    }
    static function insert($clause) {
        $db = BoincDb::get();
        $ret = $db->insert('number_e', $clause);                  
        if (!$ret) return $ret;
        return $db->insert_id();
    }
}

class BoincNumber_es {
    static function lookup_id($id) {
        $db = BoincDb::get();
        return $db->lookup_id($id, 'number_es', 'BoincNumber_es');
    }
    static function lookup($clause) {
        $db = BoincDb::get();
        return $db->lookup('number_es', 'BoincNumber_es', $clause);
    }
    static function enum($clause) {
        $db = BoincDb::get();
        return $db->enum('number_es', 'BoincNumber_es', $clause);
    }
    function update($clause) {
        $db = BoincDb::get();
        return $db->update($this, 'number_es', $clause);
    }
    static function insert($clause) {
        $db = BoincDb::get();
        $ret = $db->insert('number_es', $clause);                  
        if (!$ret) return $ret;
        return $db->insert_id();
    }
}

class BoincNumber_fs {
    static function lookup_id($id) {
        $db = BoincDb::get();
        return $db->lookup_id($id, 'number_fs', 'BoincNumber_fs');
    }
    static function lookup($clause) {
        $db = BoincDb::get();
        return $db->lookup('number_fs', 'BoincNumber_fs', $clause);
    }
    static function enum($clause) {
        $db = BoincDb::get();
        return $db->enum('number_fs', 'BoincNumber_fs', $clause);
    }
    function update($clause) {
        $db = BoincDb::get();
        return $db->update($this, 'number_fs', $clause);
    }
    static function insert($clause) {
        $db = BoincDb::get();
        $ret = $db->insert('number_fs', $clause);                  
        if (!$ret) return $ret;
        return $db->insert_id();
    }
}

class BoincNumber_f {
    static function lookup_id($id) {
        $db = BoincDb::get();
        return $db->lookup_id($id, 'number_f', 'BoincNumber_f');
    }
    static function lookup($clause) {
        $db = BoincDb::get();
        return $db->lookup('number_f', 'BoincNumber_f', $clause);
    }
    static function enum($clause) {
        $db = BoincDb::get();
        return $db->enum('number_f', 'BoincNumber_f', $clause);
    }
    function update($clause) {
        $db = BoincDb::get();
        return $db->update($this, 'number_f', $clause);
    }
    static function insert($clause) {
        $db = BoincDb::get();
        $ret = $db->insert('number_f', $clause);                  
        if (!$ret) return $ret;
        return $db->insert_id();
    }
}

class BoincNumber_cs {
    static function lookup_id($id) {
        $db = BoincDb::get();
        return $db->lookup_id($id, 'number_cs', 'BoincNumber_cs');
    }
    static function lookup($clause) {
        $db = BoincDb::get();
        return $db->lookup('number_cs', 'BoincNumber_cs', $clause);
    }
    static function enum($clause) {
        $db = BoincDb::get();
        return $db->enum('number_cs', 'BoincNumber_cs', $clause);
    }
    function update($clause) {
        $db = BoincDb::get();
        return $db->update($this, 'number_cs', $clause);
    }
    static function insert($clause) {
        $db = BoincDb::get();
        $ret = $db->insert('number_cs', $clause);                  
        if (!$ret) return $ret;
        return $db->insert_id();
    }
}

?>
