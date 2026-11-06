<?php
/*
 * MongoDB database layer for Workonnect.
 *
 * The application was written against MySQL through a PDO object called $pdo (prepare/execute/fetch...).
 * Instead of rewriting ~250 queries in 67 files, this file provides a drop-in replacement for that object:
 *
 *     $pdo = new MongoPDO(new MongoStore($uri, $dbName));
 *
 * It parses the SQL the pages already use (SELECT with JOIN / GROUP BY / aggregates / sub-queries,
 * INSERT, REPLACE, UPDATE, DELETE), runs it against MongoDB collections (one collection per old table,
 * schema in mongo_schema.php) and returns rows exactly like PDO did.
 *
 *  - Reads: simple "column = value" conditions are sent to MongoDB; joins, grouping and expressions are
 *    evaluated in PHP.
 *  - Writes: every row is written back with an atomic updateOne() whose filter also contains the values the
 *    WHERE clause relied on (so "UPDATE ... WHERE paid=0" still cannot run twice). "x = x + n" becomes $inc.
 *  - AUTO_INCREMENT ids come from a counters collection; UNIQUE keys are unique indexes.
 *  - beginTransaction()/rollBack(): standalone MongoDB servers (XAMPP) have no multi-document transactions,
 *    so changes are recorded and undone on rollBack() (no isolation between concurrent users).
 *
 * Requires the PHP "mongodb" extension (php_mongodb.dll / mongodb.so). No Composer packages needed.
 */

class MongoSqlError extends Exception {}
class MongoDupKey extends MongoSqlError {}

/* ===================================================================================================
 *  Storage back-ends. The SQL engine only talks to this small interface.
 * ================================================================================================= */
interface MongoStoreInterface
{
    public function schema(); // table definitions (mongo_schema.php)
    public function load($table, array $filter); // rows (assoc, with '_id'); filter: col => value | ['$in'=>[..]]
    public function insert($table, array $row, $id = null); // returns _id; throws MongoDupKey
    public function update($table, $id, array $set, array $inc, array $guard); // returns number of matched documents (0/1)
    public function delete($table, array $ids);
    public function nextId($table);
}

/** Real MongoDB through the low level PHP driver (ext-mongodb). */
class MongoStore implements MongoStoreInterface
{
    private $m, $db, $sch;
    const TYPEMAP = [
        "root" => "array",
        "document" => "array",
        "array" => "array",
    ];

    public function __construct($uri, $db, $schema = null)
    {
        if (!class_exists("MongoDB\\Driver\\Manager")) {
            throw new MongoSqlError(
                'The PHP "mongodb" extension is not installed. Install it (XAMPP: download php_mongodb.dll, add extension=mongodb to php.ini, restart Apache) - see README.txt.',
            );
        }
        $this->m = new MongoDB\Driver\Manager($uri, [
            "serverSelectionTimeoutMS" => 5000,
            "connectTimeoutMS" => 5000,
        ]);
        $this->db = $db;
        $this->sch = $schema ?: require __DIR__ . "/mongo_schema.php";
    }
    public function schema()
    {
        return $this->sch;
    }

    private function read($table, array $filter, array $opts = [])
    {
        $q = new MongoDB\Driver\Query($filter, $opts);
        $cur = $this->m->executeQuery($this->db . "." . $table, $q);
        $cur->setTypeMap(self::TYPEMAP);
        return $cur->toArray();
    }
    private function cmd(array $c)
    {
        $cur = $this->m->executeCommand(
            $this->db,
            new MongoDB\Driver\Command($c),
        );
        $cur->setTypeMap(self::TYPEMAP);
        return $cur->toArray();
    }
    private function write($table, MongoDB\Driver\BulkWrite $bw)
    {
        try {
            return $this->m->executeBulkWrite($this->db . "." . $table, $bw);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            if (strpos($e->getMessage(), "E11000") !== false) {
                throw new MongoDupKey(
                    "Duplicate entry (" . $table . "): " . $e->getMessage(),
                );
            }
            throw new MongoSqlError($e->getMessage());
        }
    }

    public function load($table, array $filter)
    {
        $opts = [];
        // MySQL compares text case-insensitively; use a case-insensitive collation when we filter on text
        foreach ($filter as $v) {
            $vals = is_array($v) ? $v['$in'] ?? [] : [$v];
            foreach ($vals as $x) {
                if (is_string($x)) {
                    $opts["collation"] = ["locale" => "en", "strength" => 2];
                    break 2;
                }
            }
        }
        return $this->read($table, $filter, $opts);
    }
    public function insert($table, array $row, $id = null)
    {
        $t = $this->sch[$table];
        if ($id !== null) {
            $row["_id"] = $id;
        } elseif ($t["ai"] && isset($row[$t["ai"]])) {
            $row["_id"] = $row[$t["ai"]];
        } elseif ($t["pk"] && isset($row[$t["pk"]])) {
            $row["_id"] = $row[$t["pk"]];
        }
        $bw = new MongoDB\Driver\BulkWrite();
        $newId = $bw->insert($row);
        $this->write($table, $bw);
        return $row["_id"] ?? $newId;
    }
    public function update($table, $id, array $set, array $inc, array $guard)
    {
        $upd = [];
        if ($set) {
            $upd['$set'] = $set;
        }
        if ($inc) {
            $upd['$inc'] = $inc;
        }
        if (!$upd) {
            return 1;
        }
        $bw = new MongoDB\Driver\BulkWrite();
        $bw->update(["_id" => $id] + $guard, $upd, [
            "multi" => false,
            "upsert" => false,
        ]);
        return $this->write($table, $bw)->getMatchedCount();
    }
    public function delete($table, array $ids)
    {
        if (!$ids) {
            return 0;
        }
        $bw = new MongoDB\Driver\BulkWrite();
        $bw->delete(["_id" => ['$in' => array_values($ids)]], ["limit" => 0]);
        return $this->write($table, $bw)->getDeletedCount();
    }
    public function nextId($table)
    {
        for ($try = 0; $try < 3; $try++) {
            try {
                $r = $this->cmd([
                    "findAndModify" => "_counters",
                    "query" => ["_id" => $table],
                    "update" => ['$inc' => ["seq" => 1]],
                    "new" => true,
                    "upsert" => true,
                ]);
                return (int) $r[0]["value"]["seq"];
            } catch (MongoDB\Driver\Exception\Exception $e) {
                if ($try == 2) {
                    throw new MongoSqlError($e->getMessage());
                }
            } // two first-ever inserts racing on the upsert
        }
    }

    /** Create collections' indexes, seed default categories. Safe to run any number of times. */
    public function ensureSchema()
    {
        $ci = ["locale" => "en", "strength" => 2];
        foreach ($this->sch as $table => $t) {
            $ix = [];
            $covered = [];
            foreach ($t["unique"] as $cols) {
                $key = [];
                foreach ($cols as $c) {
                    $key[$c] = 1;
                }
                $covered[$cols[0]] = 1;
                $ix[] = [
                    "key" => $key,
                    "name" => "u_" . implode("_", $cols),
                    "unique" => true,
                    "collation" => $ci,
                ];
            }
            foreach ($t["cols"] as $c => $d) {
                if (
                    $c != $t["ai"] &&
                    $c != $t["pk"] &&
                    !isset($covered[$c]) &&
                    (substr($c, -3) == "_id" ||
                        in_array($c, [
                            "role",
                            "status",
                            "category",
                            "ip",
                            "token",
                            "share_token",
                        ]))
                ) {
                    $ix[] = ["key" => [$c => 1], "name" => "i_" . $c];
                }
            }
            try {
                if ($ix) {
                    $this->cmd(["createIndexes" => $table, "indexes" => $ix]);
                }
            } catch (MongoDB\Driver\Exception\Exception $e) {
                throw new MongoSqlError(
                    'Could not create indexes on "' .
                        $table .
                        '": ' .
                        $e->getMessage(),
                );
            }
        }
        // default service categories, only the very first time (an admin may delete them later)
        if (
            !$this->read(
                "_counters",
                ["_id" => "categories"],
                ["limit" => 1],
            ) &&
            !$this->read("categories", [], ["limit" => 1])
        ) {
            foreach (
                [
                    "Electrician",
                    "Plumber",
                    "Carpenter",
                    "Painter",
                    "Cleaner",
                    "Driver",
                    "Mechanic",
                    "AC Technician",
                ]
                as $c
            ) {
                try {
                    $this->insert("categories", [
                        "id" => $this->nextId("categories"),
                        "name" => $c,
                    ]);
                } catch (MongoDupKey $e) {
                    /* another visitor seeded it at the same moment */
                }
            }
        }
    }
    /** After importing rows with explicit ids: make the counter continue after the highest id. */
    public function syncCounter($table)
    {
        $ai = $this->sch[$table]["ai"];
        if (!$ai) {
            return;
        }
        $r = $this->read($table, [], ["sort" => [$ai => -1], "limit" => 1]);
        $max = $r ? (int) $r[0][$ai] : 0;
        $this->cmd([
            "findAndModify" => "_counters",
            "query" => ["_id" => $table],
            "update" => ['$set' => ["seq" => $max]],
            "upsert" => true,
        ]);
    }
}

/* ===================================================================================================
 *  SQL tokenizer + parser  ->  small array based syntax tree
 * ================================================================================================= */
class MongoSqlParser
{
    private $sql,
        $tk = [],
        $i = 0,
        $np = 0;
    private static $cache = [];
    const RESERVED = [
        "FROM",
        "WHERE",
        "GROUP",
        "ORDER",
        "HAVING",
        "LIMIT",
        "UNION",
        "FOR",
        "JOIN",
        "LEFT",
        "RIGHT",
        "INNER",
        "OUTER",
        "CROSS",
        "ON",
        "AND",
        "OR",
        "NOT",
        "SET",
        "VALUES",
        "USING",
        "INTO",
        "IS",
        "IN",
        "LIKE",
        "BETWEEN",
        "END",
        "THEN",
        "ELSE",
        "WHEN",
        "ASC",
        "DESC",
        "OFFSET",
        "XOR",
        "DIV",
        "MOD",
    ];

    public static function parse($sql)
    {
        if (!isset(self::$cache[$sql])) {
            $p = new self($sql);
            self::$cache[$sql] = $p->statement();
        }
        return self::$cache[$sql];
    }
    private function __construct($sql)
    {
        $this->sql = $sql;
        $this->lex($sql);
    }
    private function err($m)
    {
        throw new MongoSqlError(
            "SQL parse error: " .
                $m .
                ' near "' .
                substr($this->sql, $this->tk[$this->i][3] ?? 0, 40) .
                '" in: ' .
                $this->sql,
        );
    }

    /* ---- lexer ---- */
    private function lex($s)
    {
        $n = strlen($s);
        $i = 0;
        while ($i < $n) {
            $c = $s[$i];
            if (ctype_space($c)) {
                $i++;
                continue;
            }
            if ($c === "/" && ($s[$i + 1] ?? "") === "*") {
                $e = strpos($s, "*/", $i + 2);
                $i = $e === false ? $n : $e + 2;
                continue;
            }
            if ($c === "'" || $c === '"') {
                $q = $c;
                $j = $i + 1;
                $v = "";
                while (true) {
                    if ($j >= $n) {
                        $this->err("unterminated string");
                    }
                    $d = $s[$j];
                    if ($d === "\\" && $j + 1 < $n) {
                        $x = $s[$j + 1];
                        $v .=
                            [
                                "n" => "\n",
                                "t" => "\t",
                                "r" => "\r",
                                "0" => "\0",
                            ][$x] ?? $x;
                        $j += 2;
                        continue;
                    }
                    if ($d === $q) {
                        if (($s[$j + 1] ?? "") === $q) {
                            $v .= $q;
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $v .= $d;
                    $j++;
                }
                $this->tk[] = ["s", $v, "", $i, $j + 1];
                $i = $j + 1;
                continue;
            }
            if ($c === "`") {
                $e = strpos($s, "`", $i + 1);
                if ($e === false) {
                    $this->err("bad quote");
                }
                $v = substr($s, $i + 1, $e - $i - 1);
                $this->tk[] = ["w", $v, "`", $i, $e + 1];
                $i = $e + 1;
                continue;
            }
            if (
                ctype_digit($c) ||
                ($c === "." && ctype_digit($s[$i + 1] ?? ""))
            ) {
                preg_match(
                    "/\G(\d+\.?\d*(?:[eE][+-]?\d+)?|\.\d+)/",
                    $s,
                    $m,
                    0,
                    $i,
                );
                $v = $m[1];
                $this->tk[] = [
                    "n",
                    strpbrk($v, ".eE") === false ? (int) $v : (float) $v,
                    "",
                    $i,
                    $i + strlen($v),
                ];
                $i += strlen($v);
                continue;
            }
            if (ctype_alpha($c) || $c === "_" || $c === '$' || ord($c) > 127) {
                preg_match('/\G[A-Za-z0-9_$\x80-\xff]+/', $s, $m, 0, $i);
                $this->tk[] = [
                    "w",
                    $m[0],
                    strtoupper($m[0]),
                    $i,
                    $i + strlen($m[0]),
                ];
                $i += strlen($m[0]);
                continue;
            }
            if ($c === "?") {
                $this->tk[] = ["p", $this->np++, "", $i, $i + 1];
                $i++;
                continue;
            }
            if ($c === ":" && ctype_alpha($s[$i + 1] ?? "")) {
                preg_match("/\G:(\w+)/", $s, $m, 0, $i);
                $this->tk[] = ["p", $m[1], "", $i, $i + strlen($m[0])];
                $i += strlen($m[0]);
                continue;
            }
            $three = substr($s, $i, 3);
            $two = substr($s, $i, 2);
            if ($three === "<=>") {
                $this->tk[] = ["o", "<=>", "", $i, $i + 3];
                $i += 3;
                continue;
            }
            if (in_array($two, ["<=", ">=", "<>", "!=", "||", "&&"], true)) {
                $this->tk[] = ["o", $two, "", $i, $i + 2];
                $i += 2;
                continue;
            }
            if (strpos("(),.=<>+-*/%;", $c) !== false) {
                $this->tk[] = ["o", $c, "", $i, $i + 1];
                $i++;
                continue;
            }
            $this->err("unexpected character " . $c);
        }
        $this->tk[] = ["e", null, "", $n, $n];
    }

    /* ---- token helpers ---- */
    private function pk($o = 0)
    {
        return $this->tk[$this->i + $o] ?? $this->tk[count($this->tk) - 1];
    }
    private function isKw($k, $o = 0)
    {
        $t = $this->pk($o);
        return $t[0] === "w" && $t[2] === $k;
    }
    private function isOp($v, $o = 0)
    {
        $t = $this->pk($o);
        return $t[0] === "o" && $t[1] === $v;
    }
    private function eatKw($k)
    {
        if ($this->isKw($k)) {
            $this->i++;
            return true;
        }
        return false;
    }
    private function eatOp($v)
    {
        if ($this->isOp($v)) {
            $this->i++;
            return true;
        }
        return false;
    }
    private function needKw($k)
    {
        if (!$this->eatKw($k)) {
            $this->err("expected $k");
        }
    }
    private function needOp($v)
    {
        if (!$this->eatOp($v)) {
            $this->err("expected $v");
        }
    }
    private function word()
    {
        $t = $this->pk();
        if ($t[0] !== "w") {
            $this->err("expected a name");
        }
        $this->i++;
        return $t[1];
    }

    /* ---- statements ---- */
    public function statement()
    {
        if ($this->isKw("SELECT")) {
            $r = $this->select();
        } elseif ($this->isKw("INSERT") || $this->isKw("REPLACE")) {
            $r = $this->insert();
        } elseif ($this->isKw("UPDATE")) {
            $r = $this->update();
        } elseif ($this->isKw("DELETE")) {
            $r = $this->delete();
        } else {
            $this->err("unsupported statement");
        }
        $this->eatOp(";");
        if ($this->pk()[0] !== "e") {
            $this->err("unexpected trailing text");
        }
        return $r;
    }
    private function select()
    {
        $this->needKw("SELECT");
        $q = [
            "type" => "select",
            "distinct" => false,
            "items" => [],
            "from" => [],
            "where" => null,
            "group" => [],
            "having" => null,
            "order" => [],
            "limit" => null,
            "offset" => null,
        ];
        if ($this->eatKw("DISTINCT")) {
            $q["distinct"] = true;
        } else {
            $this->eatKw("ALL");
        }
        do {
            $q["items"][] = $this->selItem();
        } while ($this->eatOp(","));
        if ($this->eatKw("FROM")) {
            $q["from"] = $this->fromList();
        }
        if ($this->eatKw("WHERE")) {
            $q["where"] = $this->expr();
        }
        if ($this->isKw("GROUP")) {
            $this->i++;
            $this->needKw("BY");
            do {
                $q["group"][] = $this->expr();
            } while ($this->eatOp(","));
        }
        if ($this->eatKw("HAVING")) {
            $q["having"] = $this->expr();
        }
        if ($this->isKw("ORDER")) {
            $this->i++;
            $this->needKw("BY");
            $q["order"] = $this->orderList();
        }
        if ($this->eatKw("LIMIT")) {
            $a = $this->expr();
            if ($this->eatOp(",")) {
                $q["offset"] = $a;
                $q["limit"] = $this->expr();
            } else {
                $q["limit"] = $a;
                if ($this->eatKw("OFFSET")) {
                    $q["offset"] = $this->expr();
                }
            }
        }
        if ($this->isKw("FOR")) {
            $this->i += 2;
        }
        // FOR UPDATE: no row locks in MongoDB
        elseif ($this->isKw("LOCK")) {
            $this->i += 4;
        } // LOCK IN SHARE MODE
        return $q;
    }
    private function orderList()
    {
        $o = [];
        do {
            $e = $this->expr();
            $d = false;
            if ($this->eatKw("DESC")) {
                $d = true;
            } else {
                $this->eatKw("ASC");
            }
            $o[] = [$e, $d];
        } while ($this->eatOp(","));
        return $o;
    }
    private function selItem()
    {
        if ($this->isOp("*")) {
            $this->i++;
            return ["star", null];
        }
        if (
            $this->pk()[0] === "w" &&
            $this->isOp(".", 1) &&
            $this->isOp("*", 2)
        ) {
            $a = $this->word();
            $this->i += 2;
            return ["star", $a];
        }
        $start = $this->pk()[3];
        $e = $this->expr();
        $end = $this->tk[$this->i - 1][4];
        $text = trim(substr($this->sql, $start, $end - $start));
        $alias = null;
        if ($this->eatKw("AS")) {
            $t = $this->pk();
            if ($t[0] !== "w" && $t[0] !== "s") {
                $this->err("alias expected");
            }
            $this->i++;
            $alias = $t[1];
        } else {
            $t = $this->pk();
            if (
                ($t[0] === "w" &&
                    ($t[2] === "`" ||
                        !in_array($t[2], self::RESERVED, true))) ||
                $t[0] === "s"
            ) {
                $this->i++;
                $alias = $t[1];
            }
        }
        if ($alias === null) {
            $alias = $e[0] === "c" ? $e[2] : $text;
        }
        return ["expr", $e, $alias];
    }
    private function fromList()
    {
        $f = [$this->factor("base")];
        while (true) {
            if ($this->eatOp(",")) {
                $f[] = $this->factor("cross");
                continue;
            }
            $type = null;
            if ($this->isKw("LEFT")) {
                $this->i++;
                $this->eatKw("OUTER");
                $type = "left";
            } elseif ($this->isKw("RIGHT")) {
                $this->err("RIGHT JOIN is not supported");
            } elseif ($this->isKw("INNER") || $this->isKw("CROSS")) {
                $this->i++;
                $type = "inner";
            }
            if ($type !== null || $this->isKw("JOIN")) {
                $this->needKw("JOIN");
                $x = $this->factor($type ?: "inner");
                if ($this->eatKw("ON")) {
                    $x["on"] = $this->expr();
                }
                $f[] = $x;
                continue;
            }
            return $f;
        }
    }
    private function factor($join)
    {
        $x = [
            "join" => $join,
            "on" => null,
            "table" => null,
            "sub" => null,
            "alias" => null,
        ];
        if ($this->eatOp("(")) {
            $x["sub"] = $this->select();
            $this->needOp(")");
        } else {
            $x["table"] = $this->word();
        }
        if ($this->eatKw("AS")) {
            $x["alias"] = $this->word();
        } else {
            $t = $this->pk();
            if (
                $t[0] === "w" &&
                ($t[2] === "`" || !in_array($t[2], self::RESERVED, true))
            ) {
                $this->i++;
                $x["alias"] = $t[1];
            }
        }
        if ($x["alias"] === null) {
            $x["alias"] = $x["table"];
        }
        return $x;
    }
    private function insert()
    {
        $mode = "insert";
        if ($this->eatKw("REPLACE")) {
            $mode = "replace";
        } else {
            $this->needKw("INSERT");
            if ($this->eatKw("IGNORE")) {
                $mode = "ignore";
            }
        }
        $this->eatKw("INTO");
        $table = $this->word();
        $cols = null;
        if ($this->eatOp("(")) {
            $cols = [];
            do {
                $cols[] = $this->word();
            } while ($this->eatOp(","));
            $this->needOp(")");
        }
        if (!$this->eatKw("VALUES") && !$this->eatKw("VALUE")) {
            $this->err("only INSERT ... VALUES is supported");
        }
        $rows = [];
        do {
            $this->needOp("(");
            $r = [];
            do {
                $r[] = $this->expr();
            } while ($this->eatOp(","));
            $this->needOp(")");
            $rows[] = $r;
        } while ($this->eatOp(","));
        $dup = null;
        if ($this->isKw("ON")) {
            $this->i += 3;
            $dup = $this->assigns();
        }
        return [
            "type" => "insert",
            "mode" => $mode,
            "table" => $table,
            "cols" => $cols,
            "rows" => $rows,
            "dup" => $dup,
        ];
    }
    private function assigns()
    {
        $a = [];
        do {
            $c = $this->word();
            if ($this->eatOp(".")) {
                $c = $this->word();
            }
            $this->needOp("=");
            $a[] = [$c, $this->expr()];
        } while ($this->eatOp(","));
        return $a;
    }
    private function update()
    {
        $this->needKw("UPDATE");
        $table = $this->word();
        $alias = $table;
        if (!$this->isKw("SET")) {
            $this->eatKw("AS");
            $alias = $this->word();
        }
        $this->needKw("SET");
        $set = $this->assigns();
        $q = [
            "type" => "update",
            "table" => $table,
            "alias" => $alias,
            "set" => $set,
            "where" => null,
            "order" => [],
            "limit" => null,
        ];
        if ($this->eatKw("WHERE")) {
            $q["where"] = $this->expr();
        }
        if ($this->isKw("ORDER")) {
            $this->i++;
            $this->needKw("BY");
            $q["order"] = $this->orderList();
        }
        if ($this->eatKw("LIMIT")) {
            $q["limit"] = $this->expr();
        }
        return $q;
    }
    private function delete()
    {
        $this->needKw("DELETE");
        $this->needKw("FROM");
        $table = $this->word();
        $alias = $table;
        if (
            !$this->isKw("WHERE") &&
            !$this->isKw("ORDER") &&
            !$this->isKw("LIMIT") &&
            $this->pk()[0] === "w"
        ) {
            $this->eatKw("AS");
            $alias = $this->word();
        }
        $q = [
            "type" => "delete",
            "table" => $table,
            "alias" => $alias,
            "where" => null,
            "order" => [],
            "limit" => null,
        ];
        if ($this->eatKw("WHERE")) {
            $q["where"] = $this->expr();
        }
        if ($this->isKw("ORDER")) {
            $this->i++;
            $this->needKw("BY");
            $q["order"] = $this->orderList();
        }
        if ($this->eatKw("LIMIT")) {
            $q["limit"] = $this->expr();
        }
        return $q;
    }

    /* ---- expressions (lowest to highest precedence) ----
     * nodes: ['n',v] ['p',idx] ['c',alias,name] ['f',NAME,args,distinct] ['b',op,l,r] ['not',x] ['neg',x] ['isnull',x,neg]
     *        ['in',x,list|['sub',q],neg] ['between',x,lo,hi,neg] ['like',x,pat,neg] ['case',operand,whens,else]
     *        ['sub',q] ['exists',q,neg] ['iv',expr,unit] ['star'] */
    private function expr()
    {
        return $this->orE();
    }
    private function orE()
    {
        $l = $this->andE();
        while ($this->isKw("OR") || $this->isOp("||")) {
            $this->i++;
            $l = ["b", "OR", $l, $this->andE()];
        }
        return $l;
    }
    private function andE()
    {
        $l = $this->notE();
        while ($this->isKw("AND") || $this->isOp("&&")) {
            $this->i++;
            $l = ["b", "AND", $l, $this->notE()];
        }
        return $l;
    }
    private function notE()
    {
        if ($this->isKw("NOT") && !$this->isKw("EXISTS", 1)) {
            $this->i++;
            return ["not", $this->notE()];
        }
        return $this->cmpE();
    }
    private function cmpE()
    {
        $l = $this->addE();
        while (true) {
            $t = $this->pk();
            if (
                $t[0] === "o" &&
                in_array(
                    $t[1],
                    ["=", "<>", "!=", "<", ">", "<=", ">=", "<=>"],
                    true,
                )
            ) {
                $this->i++;
                $l = ["b", $t[1] === "!=" ? "<>" : $t[1], $l, $this->addE()];
                continue;
            }
            if ($this->isKw("IS")) {
                $this->i++;
                $neg = $this->eatKw("NOT");
                if ($this->eatKw("NULL")) {
                    $l = ["isnull", $l, $neg];
                    continue;
                }
                if ($this->eatKw("TRUE")) {
                    $l = $neg
                        ? ["not", ["b", "=", $l, ["n", 1]]]
                        : ["b", "=", $l, ["n", 1]];
                    continue;
                }
                if ($this->eatKw("FALSE")) {
                    $l = $neg
                        ? ["not", ["b", "=", $l, ["n", 0]]]
                        : ["b", "=", $l, ["n", 0]];
                    continue;
                }
                $this->err("IS ...");
            }
            $neg = false;
            if (
                $this->isKw("NOT") &&
                ($this->isKw("IN", 1) ||
                    $this->isKw("LIKE", 1) ||
                    $this->isKw("BETWEEN", 1))
            ) {
                $this->i++;
                $neg = true;
            }
            if ($this->eatKw("IN")) {
                $this->needOp("(");
                if ($this->isKw("SELECT")) {
                    $s = $this->select();
                    $this->needOp(")");
                    $l = ["in", $l, ["sub", $s], $neg];
                    continue;
                }
                $list = [];
                do {
                    $list[] = $this->expr();
                } while ($this->eatOp(","));
                $this->needOp(")");
                $l = ["in", $l, $list, $neg];
                continue;
            }
            if ($this->eatKw("LIKE")) {
                $l = ["like", $l, $this->addE(), $neg];
                continue;
            }
            if ($this->eatKw("BETWEEN")) {
                $lo = $this->addE();
                $this->needKw("AND");
                $hi = $this->addE();
                $l = ["between", $l, $lo, $hi, $neg];
                continue;
            }
            return $l;
        }
    }
    private function addE()
    {
        $l = $this->mulE();
        while ($this->isOp("+") || $this->isOp("-")) {
            $op = $this->pk()[1];
            $this->i++;
            $l = ["b", $op, $l, $this->mulE()];
        }
        return $l;
    }
    private function mulE()
    {
        $l = $this->unE();
        while (true) {
            if ($this->isOp("*") || $this->isOp("/") || $this->isOp("%")) {
                $op = $this->pk()[1];
                $this->i++;
                $l = ["b", $op, $l, $this->unE()];
            } elseif ($this->isKw("DIV")) {
                $this->i++;
                $l = ["b", "DIV", $l, $this->unE()];
            } elseif ($this->isKw("MOD")) {
                $this->i++;
                $l = ["b", "%", $l, $this->unE()];
            } else {
                return $l;
            }
        }
    }
    private function unE()
    {
        if ($this->eatOp("-")) {
            return ["neg", $this->unE()];
        }
        if ($this->eatOp("+")) {
            return $this->unE();
        }
        return $this->prim();
    }
    private function prim()
    {
        $t = $this->pk();
        switch ($t[0]) {
            case "n":
                $this->i++;
                return ["n", $t[1]];
            case "s":
                $this->i++;
                return ["n", $t[1]];
            case "p":
                $this->i++;
                return ["p", $t[1]];
            case "o":
                if ($t[1] === "(") {
                    $this->i++;
                    if ($this->isKw("SELECT")) {
                        $s = $this->select();
                        $this->needOp(")");
                        return ["sub", $s];
                    }
                    $e = $this->expr();
                    $this->needOp(")");
                    return $e;
                }
                $this->err("unexpected " . $t[1]);
            case "w":
                $u = $t[2];
                if ($u !== "`") {
                    if ($u === "NULL") {
                        $this->i++;
                        return ["n", null];
                    }
                    if ($u === "TRUE") {
                        $this->i++;
                        return ["n", 1];
                    }
                    if ($u === "FALSE") {
                        $this->i++;
                        return ["n", 0];
                    }
                    if ($u === "CASE") {
                        return $this->caseE();
                    }
                    if (
                        $u === "EXISTS" ||
                        ($u === "NOT" && $this->isKw("EXISTS", 1))
                    ) {
                        $neg = $u === "NOT";
                        $this->i += $neg ? 2 : 1;
                        $this->needOp("(");
                        $s = $this->select();
                        $this->needOp(")");
                        return ["exists", $s, $neg];
                    }
                    if ($u === "INTERVAL") {
                        $this->i++;
                        $e = $this->unE();
                        return ["iv", $e, strtoupper($this->word())];
                    }
                    if (
                        in_array(
                            $u,
                            [
                                "CURRENT_TIMESTAMP",
                                "CURRENT_DATE",
                                "LOCALTIME",
                                "LOCALTIMESTAMP",
                            ],
                            true,
                        ) &&
                        !$this->isOp("(", 1)
                    ) {
                        $this->i++;
                        return [
                            "f",
                            $u === "CURRENT_DATE" ? "CURDATE" : "NOW",
                            [],
                            false,
                        ];
                    }
                }
                $this->i++;
                if ($this->isOp("(") && $u !== "`") {
                    $this->i++;
                    $args = [];
                    $distinct = false;
                    if (!$this->isOp(")")) {
                        if ($this->eatKw("DISTINCT")) {
                            $distinct = true;
                        }
                        do {
                            if ($this->eatOp("*")) {
                                $args[] = ["star"];
                            } else {
                                $args[] = $this->expr();
                            }
                        } while ($this->eatOp(","));
                    }
                    $this->needOp(")");
                    return ["f", $u, $args, $distinct];
                }
                if ($this->isOp(".") && $this->pk(1)[0] === "w") {
                    $this->i++;
                    $c = $this->word();
                    return ["c", $t[1], $c];
                }
                return ["c", null, $t[1]];
        }
        $this->err("unexpected end");
    }
    private function caseE()
    {
        $this->needKw("CASE");
        $operand = null;
        if (!$this->isKw("WHEN")) {
            $operand = $this->expr();
        }
        $whens = [];
        while ($this->eatKw("WHEN")) {
            $c = $this->expr();
            $this->needKw("THEN");
            $whens[] = [$c, $this->expr()];
        }
        $else = null;
        if ($this->eatKw("ELSE")) {
            $else = $this->expr();
        }
        $this->needKw("END");
        return ["case", $operand, $whens, $else];
    }
}

/* ===================================================================================================
 *  SQL executor
 * ================================================================================================= */
class MongoSqlEngine
{
    public $store,
        $sch,
        $lastId = 0;
    private $P = [],
        $now = null,
        $cache = [],
        $undo = null;
    const AGG = ["COUNT", "SUM", "AVG", "MIN", "MAX", "GROUP_CONCAT"];

    public function __construct(MongoStoreInterface $store)
    {
        $this->store = $store;
        $this->sch = $store->schema();
    }

    /* ---- transactions (undo log; see header comment) ---- */
    public function begin()
    {
        $this->undo = [];
    }
    public function commit()
    {
        $this->undo = null;
    }
    public function inTx()
    {
        return $this->undo !== null;
    }
    public function rollback()
    {
        $log = array_reverse($this->undo ?? []);
        $this->undo = null;
        foreach ($log as $u) {
            if ($u[0] === "ins") {
                $this->store->delete($u[1], [$u[2]]);
            } elseif ($u[0] === "upd") {
                $this->store->update($u[1], $u[2], $u[3], $u[4], []);
            } elseif ($u[0] === "del") {
                $this->store->insert($u[1], $u[3], $u[2]);
            }
        }
        $this->cache = [];
    }
    private function log($e)
    {
        if ($this->undo !== null) {
            $this->undo[] = $e;
        }
    }

    /* ---- entry point: returns ['rows'=>[...], 'count'=>n] ---- */
    public function run($sql, array $params)
    {
        $ast = MongoSqlParser::parse($sql);
        $this->P = $params;
        $this->now = date("Y-m-d H:i:s");
        $this->cache = [];
        switch ($ast["type"]) {
            case "select":
                $rows = $this->select($ast, []);
                return ["rows" => $rows, "count" => count($rows)];
            case "insert":
                return ["rows" => [], "count" => $this->insertQ($ast)];
            case "update":
                return ["rows" => [], "count" => $this->updateQ($ast)];
            case "delete":
                return ["rows" => [], "count" => $this->deleteQ($ast)];
        }
    }

    /* ---- table access ---- */
    private function def($table)
    {
        if (!isset($this->sch[$table])) {
            throw new MongoSqlError("Table '$table' doesn't exist");
        }
        return $this->sch[$table];
    }
    private function shape($table, array $doc)
    {
        // MongoDB document -> row in column order, '_id' kept at the end
        $d = $this->def($table);
        $row = [];
        foreach ($d["cols"] as $c => $def) {
            $row[$c] = array_key_exists($c, $doc)
                ? $doc[$c]
                : ($def[1] === "@now"
                    ? null
                    : $def[1]);
        }
        $row["_id"] = $doc["_id"] ?? null;
        return $row;
    }
    private function table($table, array $filter = [])
    {
        $key = $table . "|" . json_encode($filter);
        if (!isset($this->cache[$key])) {
            $rows = [];
            foreach ($this->store->load($table, $filter) as $doc) {
                $rows[] = $this->shape($table, $doc);
            }
            $this->cache[$key] = $rows;
        }
        return $this->cache[$key];
    }
    private function cols($table)
    {
        return array_keys($this->def($table)["cols"]);
    }

    /* ---- value helpers ---- */
    private function coerce($cls, $v)
    {
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            $v = (int) $v;
        }
        switch ($cls) {
            case "int":
                if (is_int($v)) {
                    return $v;
                }
                if (is_float($v)) {
                    return (int) round($v);
                }
                if (is_numeric($v)) {
                    return strpbrk($v, ".eE") === false
                        ? (int) $v
                        : (int) round((float) $v);
                }
                return (int) $this->numv($v);
            case "float":
                if (is_string($v) && trim($v) === "") {
                    return null;
                }
                return (float) $this->numv($v);
            case "dt":
                $v = (string) $v;
                if (trim($v) === "") {
                    return null;
                }
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
                    return $v . " 00:00:00";
                }
                if (
                    preg_match(
                        "/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?/",
                        $v,
                        $m,
                    )
                ) {
                    return $m[1] . " " . $m[2] . ($m[3] ?? ":00");
                }
                $t = strtotime($v);
                return $t === false ? $v : date("Y-m-d H:i:s", $t);
            case "time":
                $v = (string) $v;
                if (trim($v) === "") {
                    return null;
                }
                if (preg_match('/^(\d{1,2}):(\d{2})(:\d{2})?$/', $v, $m)) {
                    return sprintf("%02d", $m[1]) .
                        ":" .
                        $m[2] .
                        ($m[3] ?? ":00");
                }
                return $v;
            default:
                return is_array($v) ? json_encode($v) : (string) $v;
        }
    }
    private function numv($v)
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if (is_bool($v)) {
            return (int) $v;
        }
        if (is_array($v)) {
            return 0;
        }
        if (is_numeric($v)) {
            return $v + 0;
        }
        if (
            preg_match(
                "/^\s*[-+]?(\d+\.?\d*|\.\d+)([eE][-+]?\d+)?/",
                (string) $v,
                $m,
            )
        ) {
            return $m[0] + 0;
        }
        return 0;
    }
    private function truthy($v)
    {
        return $v !== null && $this->numv($v) != 0;
    }
    private function isDec($s)
    {
        return is_string($s) && preg_match('/^-?\d+\.\d+$/', $s);
    }
    private function cmpv($a, $b)
    {
        // neither is null
        if (
            is_string($a) &&
            is_string($b) &&
            !($this->isDec($a) && $this->isDec($b))
        ) {
            return strcmp(
                mb_strtolower($a, "UTF-8"),
                mb_strtolower($b, "UTF-8"),
            ) <=> 0;
        }
        $x = $this->numv($a);
        $y = $this->numv($b);
        return $x <=> $y;
    }
    private function cmpOrder($a, $b)
    {
        if ($a === null || $b === null) {
            return ($a === null ? 0 : 1) <=> ($b === null ? 0 : 1);
        }
        return $this->cmpv($a, $b);
    }
    private function hk($v)
    {
        // hash key with MySQL-like equality (case-insensitive text, numeric strings = numbers)
        if (
            is_int($v) ||
            is_float($v) ||
            (is_string($v) &&
                preg_match('/^-?\d+(\.\d+)?$/', $v) &&
                strlen($v) < 16 &&
                ($v === "0" || $v[0] !== "0" || strpos($v, ".") === 1))
        ) {
            return "n" . (0 + $v);
        }
        return "s" . mb_strtolower((string) $v, "UTF-8");
    }
    private function ts($v)
    {
        if ($v === null) {
            return null;
        }
        $t = strtotime((string) $v);
        return $t === false ? null : $t;
    }
    private function like($s, $p)
    {
        $p = (string) $p;
        $re = "";
        $n = strlen($p);
        for ($i = 0; $i < $n; $i++) {
            $c = $p[$i];
            if ($c === "\\" && $i + 1 < $n) {
                $re .= preg_quote($p[++$i], "/");
            } elseif ($c === "%") {
                $re .= ".*";
            } elseif ($c === "_") {
                $re .= ".";
            } else {
                $re .= preg_quote($c, "/");
            }
        }
        return preg_match("/^" . $re . '$/isu', (string) $s) === 1;
    }

    /* ---- expression evaluation ---- */
    private function colv($env, $alias, $name)
    {
        if ($alias !== null) {
            return isset($env[$alias]) && array_key_exists($name, $env[$alias])
                ? $env[$alias][$name]
                : null;
        }
        foreach ($env as $row) {
            if ($row !== null && array_key_exists($name, $row)) {
                return $row[$name];
            }
        }
        return null;
    }
    private function hasAgg($n)
    {
        if (!is_array($n)) {
            return false;
        }
        if (isset($n[0]) && $n[0] === "f" && in_array($n[1], self::AGG, true)) {
            return true;
        }
        if (isset($n["type"])) {
            return false;
        } // sub-select has its own scope
        if (isset($n[0]) && $n[0] === "sub") {
            return false;
        }
        foreach ($n as $c) {
            if (is_array($c) && $this->hasAgg($c)) {
                return true;
            }
        }
        return false;
    }
    private function ev($n, $env, $g = null)
    {
        switch ($n[0]) {
            case "n":
                return $n[1];
            case "p":
                $k = $n[1];
                if (array_key_exists($k, $this->P)) {
                    $v = $this->P[$k];
                } elseif (array_key_exists(":" . $k, $this->P)) {
                    $v = $this->P[":" . $k];
                } else {
                    throw new MongoSqlError(
                        "Missing value for SQL parameter " . $k,
                    );
                }
                return is_bool($v) ? (int) $v : $v;
            case "c":
                return $this->colv($env, $n[1], $n[2]);
            case "neg":
                $v = $this->numv($this->ev($n[1], $env, $g));
                return $v === null ? null : -$v;
            case "not":
                $v = $this->ev($n[1], $env, $g);
                return $v === null ? null : ($this->truthy($v) ? 0 : 1);
            case "isnull":
                $v = $this->ev($n[1], $env, $g);
                return ($v === null) !== (bool) $n[2] ? 1 : 0;
            case "b":
                return $this->bin($n, $env, $g);
            case "in":
                return $this->inE($n, $env, $g);
            case "between":
                $v = $this->ev($n[1], $env, $g);
                $lo = $this->ev($n[2], $env, $g);
                $hi = $this->ev($n[3], $env, $g);
                if ($v === null || $lo === null || $hi === null) {
                    return null;
                }
                $r = $this->cmpv($v, $lo) >= 0 && $this->cmpv($v, $hi) <= 0;
                return $r !== (bool) $n[4] ? 1 : 0;
            case "like":
                $v = $this->ev($n[1], $env, $g);
                $p = $this->ev($n[2], $env, $g);
                if ($v === null || $p === null) {
                    return null;
                }
                return $this->like($v, $p) !== (bool) $n[3] ? 1 : 0;
            case "case":
                $op = $n[1] === null ? null : $this->ev($n[1], $env, $g);
                foreach ($n[2] as $w) {
                    $c = $this->ev($w[0], $env, $g);
                    $hit =
                        $n[1] === null
                            ? $this->truthy($c)
                            : $op !== null &&
                                $c !== null &&
                                $this->cmpv($op, $c) == 0;
                    if ($hit) {
                        return $this->ev($w[1], $env, $g);
                    }
                }
                return $n[3] === null ? null : $this->ev($n[3], $env, $g);
            case "sub":
                $rows = $this->select($n[1], $env);
                if (!$rows) {
                    return null;
                }
                return reset($rows[0]);
            case "exists":
                return ((bool) $this->select($n[1], $env)) !== (bool) $n[2]
                    ? 1
                    : 0;
            case "iv":
                return ["iv", $this->numv($this->ev($n[1], $env, $g)), $n[2]];
            case "star":
                return 1;
            case "f":
                return $this->fn($n, $env, $g);
        }
        throw new MongoSqlError("Cannot evaluate " . $n[0]);
    }
    private function inE($n, $env, $g)
    {
        $v = $this->ev($n[1], $env, $g);
        if ($v === null) {
            return null;
        }
        if (isset($n[2][0]) && $n[2][0] === "sub") {
            $vals = [];
            foreach ($this->select($n[2][1], $env) as $r) {
                $vals[] = reset($r);
            }
        } else {
            $vals = [];
            foreach ($n[2] as $e) {
                $vals[] = $this->ev($e, $env, $g);
            }
        }
        $found = false;
        $nul = false;
        foreach ($vals as $x) {
            if ($x === null) {
                $nul = true;
                continue;
            }
            if ($this->cmpv($v, $x) == 0) {
                $found = true;
                break;
            }
        }
        if ($found) {
            return $n[3] ? 0 : 1;
        }
        if ($nul) {
            return null;
        }
        return $n[3] ? 1 : 0;
    }
    private function addIv($d, $iv, $sign)
    {
        $t = $this->ts($d);
        if ($t === null) {
            return null;
        }
        $k = (int) round($iv[1]) * $sign;
        $u = $iv[2];
        $map = [
            "SECOND" => "seconds",
            "MINUTE" => "minutes",
            "HOUR" => "hours",
            "DAY" => "days",
            "WEEK" => "weeks",
            "MONTH" => "months",
            "YEAR" => "years",
        ];
        if (!isset($map[$u])) {
            throw new MongoSqlError("Unsupported INTERVAL unit $u");
        }
        return date(
            "Y-m-d H:i:s",
            strtotime(($k >= 0 ? "+" : "") . $k . " " . $map[$u], $t),
        );
    }
    private function bin($n, $env, $g)
    {
        $op = $n[1];
        if ($op === "AND") {
            $a = $this->ev($n[2], $env, $g);
            if ($a !== null && !$this->truthy($a)) {
                return 0;
            }
            $b = $this->ev($n[3], $env, $g);
            if ($b !== null && !$this->truthy($b)) {
                return 0;
            }
            return $a === null || $b === null ? null : 1;
        }
        if ($op === "OR") {
            $a = $this->ev($n[2], $env, $g);
            if ($a !== null && $this->truthy($a)) {
                return 1;
            }
            $b = $this->ev($n[3], $env, $g);
            if ($b !== null && $this->truthy($b)) {
                return 1;
            }
            return $a === null || $b === null ? null : 0;
        }
        $a = $this->ev($n[2], $env, $g);
        $b = $this->ev($n[3], $env, $g);
        if ($op === "<=>") {
            if ($a === null || $b === null) {
                return $a === null && $b === null ? 1 : 0;
            }
            return $this->cmpv($a, $b) == 0 ? 1 : 0;
        }
        if (
            is_array($b) &&
            ($b[0] ?? "") === "iv" &&
            ($op === "+" || $op === "-")
        ) {
            return $this->addIv($a, $b, $op === "+" ? 1 : -1);
        }
        if (is_array($a) && ($a[0] ?? "") === "iv" && $op === "+") {
            return $this->addIv($b, $a, 1);
        }
        if ($a === null || $b === null) {
            return null;
        }
        switch ($op) {
            case "=":
                return $this->cmpv($a, $b) == 0 ? 1 : 0;
            case "<>":
                return $this->cmpv($a, $b) != 0 ? 1 : 0;
            case "<":
                return $this->cmpv($a, $b) < 0 ? 1 : 0;
            case ">":
                return $this->cmpv($a, $b) > 0 ? 1 : 0;
            case "<=":
                return $this->cmpv($a, $b) <= 0 ? 1 : 0;
            case ">=":
                return $this->cmpv($a, $b) >= 0 ? 1 : 0;
        }
        $x = $this->numv($a);
        $y = $this->numv($b);
        switch ($op) {
            case "+":
                return $x + $y;
            case "-":
                return $x - $y;
            case "*":
                return $x * $y;
            case "/":
                return $y == 0 ? null : $x / $y;
            case "DIV":
                return $y == 0 ? null : (int) ($x / $y);
            case "%":
                return $y == 0
                    ? null
                    : (is_int($x) && is_int($y)
                        ? $x % $y
                        : fmod($x, $y));
        }
        throw new MongoSqlError("Unknown operator $op");
    }
    private function fn($n, $env, $g)
    {
        $name = $n[1];
        $a = $n[2];
        if (in_array($name, self::AGG, true)) {
            return $this->agg($n, $env, $g);
        }
        $v = function ($i) use ($a, $env, $g) {
            return isset($a[$i]) ? $this->ev($a[$i], $env, $g) : null;
        };
        switch ($name) {
            case "NOW":
            case "SYSDATE":
            case "LOCALTIME":
            case "LOCALTIMESTAMP":
            case "CURRENT_TIMESTAMP":
                return $this->now;
            case "CURDATE":
            case "CURRENT_DATE":
                return substr($this->now, 0, 10);
            case "CURTIME":
                return substr($this->now, 11);
            case "UNIX_TIMESTAMP":
                return $a ? $this->ts($v(0)) : strtotime($this->now);
            case "COALESCE":
                foreach ($a as $e) {
                    $x = $this->ev($e, $env, $g);
                    if ($x !== null) {
                        return $x;
                    }
                }
                return null;
            case "IFNULL":
                $x = $v(0);
                return $x !== null ? $x : $v(1);
            case "NULLIF":
                $x = $v(0);
                $y = $v(1);
                return $x !== null && $y !== null && $this->cmpv($x, $y) == 0
                    ? null
                    : $x;
            case "IF":
                return $this->truthy($v(0)) ? $v(1) : $v(2);
            case "ROUND":
                $x = $this->numv($v(0));
                if ($x === null) {
                    return null;
                }
                $d = isset($a[1]) ? (int) $this->numv($v(1)) : 0;
                $r = round($x, $d);
                return $d > 0 ? number_format($r, $d, ".", "") : (int) $r; // MySQL returns DECIMAL text such as "4.5" / "4.0"
            case "FLOOR":
                $x = $this->numv($v(0));
                return $x === null ? null : (int) floor($x);
            case "CEIL":
            case "CEILING":
                $x = $this->numv($v(0));
                return $x === null ? null : (int) ceil($x);
            case "ABS":
                $x = $this->numv($v(0));
                return $x === null ? null : abs($x);
            case "SIN":
            case "COS":
            case "TAN":
            case "ASIN":
            case "ACOS":
            case "ATAN":
            case "RADIANS":
            case "DEGREES":
            case "SQRT":
            case "EXP":
            case "LN":
            case "LOG10":
                $x = $this->numv($v(0));
                if ($x === null) {
                    return null;
                }
                if (
                    ($name === "ASIN" || $name === "ACOS") &&
                    ($x < -1 || $x > 1)
                ) {
                    return null;
                }
                if (
                    ($name === "SQRT" || $name === "LN" || $name === "LOG10") &&
                    $x < 0
                ) {
                    return null;
                }
                $f = [
                    "SIN" => "sin",
                    "COS" => "cos",
                    "TAN" => "tan",
                    "ASIN" => "asin",
                    "ACOS" => "acos",
                    "ATAN" => "atan",
                    "RADIANS" => "deg2rad",
                    "DEGREES" => "rad2deg",
                    "SQRT" => "sqrt",
                    "EXP" => "exp",
                    "LN" => "log",
                    "LOG10" => "log10",
                ][$name];
                return $f($x);
            case "PI":
                return M_PI;
            case "POW":
            case "POWER":
                $x = $this->numv($v(0));
                $y = $this->numv($v(1));
                return $x === null || $y === null ? null : pow($x, $y);
            case "MOD":
                $x = $this->numv($v(0));
                $y = $this->numv($v(1));
                return $x === null || $y === null || $y == 0
                    ? null
                    : (is_int($x) && is_int($y)
                        ? $x % $y
                        : fmod($x, $y));
            case "SIGN":
                $x = $this->numv($v(0));
                return $x === null ? null : $x <=> 0;
            case "REPLACE":
                $x = $v(0);
                return $x === null
                    ? null
                    : str_replace((string) $v(1), (string) $v(2), (string) $x);
            case "LEFT":
                $x = $v(0);
                return $x === null
                    ? null
                    : mb_substr(
                        (string) $x,
                        0,
                        (int) $this->numv($v(1)),
                        "UTF-8",
                    );
            case "RIGHT":
                $x = $v(0);
                $k = (int) $this->numv($v(1));
                return $x === null
                    ? null
                    : ($k <= 0
                        ? ""
                        : mb_substr((string) $x, -$k, null, "UTF-8"));
            case "GREATEST":
            case "LEAST":
                $best = null;
                foreach ($a as $e) {
                    $x = $this->ev($e, $env, $g);
                    if ($x === null) {
                        return null;
                    }
                    if (
                        $best === null ||
                        ($name === "GREATEST"
                            ? $this->cmpv($x, $best) > 0
                            : $this->cmpv($x, $best) < 0)
                    ) {
                        $best = $x;
                    }
                }
                return $best;
            case "CONCAT":
                $s = "";
                foreach ($a as $e) {
                    $x = $this->ev($e, $env, $g);
                    if ($x === null) {
                        return null;
                    }
                    $s .= $x;
                }
                return $s;
            case "LOWER":
            case "LCASE":
                $x = $v(0);
                return $x === null ? null : mb_strtolower((string) $x, "UTF-8");
            case "UPPER":
            case "UCASE":
                $x = $v(0);
                return $x === null ? null : mb_strtoupper((string) $x, "UTF-8");
            case "LENGTH":
            case "CHAR_LENGTH":
                $x = $v(0);
                return $x === null ? null : mb_strlen((string) $x, "UTF-8");
            case "TRIM":
                $x = $v(0);
                return $x === null ? null : trim((string) $x);
            case "SUBSTRING":
            case "SUBSTR":
                $x = $v(0);
                if ($x === null) {
                    return null;
                }
                $p = (int) $this->numv($v(1));
                $p =
                    $p > 0 ? $p - 1 : ($p < 0 ? max(0, mb_strlen($x) + $p) : 0);
                return isset($a[2])
                    ? mb_substr(
                        (string) $x,
                        $p,
                        (int) $this->numv($v(2)),
                        "UTF-8",
                    )
                    : mb_substr((string) $x, $p, null, "UTF-8");
            case "DATE":
                $t = $this->ts($v(0));
                return $t === null ? null : date("Y-m-d", $t);
            case "YEAR":
                $t = $this->ts($v(0));
                return $t === null ? null : (int) date("Y", $t);
            case "MONTH":
                $t = $this->ts($v(0));
                return $t === null ? null : (int) date("n", $t);
            case "DAY":
            case "DAYOFMONTH":
                $t = $this->ts($v(0));
                return $t === null ? null : (int) date("j", $t);
            case "HOUR":
                $t = $this->ts($v(0));
                return $t === null ? null : (int) date("G", $t);
            case "MINUTE":
                $t = $this->ts($v(0));
                return $t === null ? null : (int) date("i", $t);
            case "DAYOFWEEK":
                $t = $this->ts($v(0));
                return $t === null ? null : (int) date("w", $t) + 1; // Sunday = 1
            case "WEEKDAY":
                $t = $this->ts($v(0));
                return $t === null ? null : (int) date("N", $t) - 1; // Monday = 0
            case "DATE_ADD":
            case "ADDDATE":
            case "DATE_SUB":
            case "SUBDATE":
                $iv = $v(1);
                $d = $v(0);
                if ($d === null || !is_array($iv)) {
                    return null;
                }
                return $this->addIv(
                    $d,
                    $iv,
                    $name === "DATE_ADD" || $name === "ADDDATE" ? 1 : -1,
                );
            case "TIMESTAMPDIFF":
                $unit = strtoupper(
                    $a[0][0] === "c" ? $a[0][2] : (string) $v(0),
                );
                $t1 = $this->ts($v(1));
                $t2 = $this->ts($v(2));
                if ($t1 === null || $t2 === null) {
                    return null;
                }
                $d = $t2 - $t1;
                $div = [
                    "SECOND" => 1,
                    "MINUTE" => 60,
                    "HOUR" => 3600,
                    "DAY" => 86400,
                    "WEEK" => 604800,
                ];
                if (isset($div[$unit])) {
                    return (int) ($d / $div[$unit]);
                }
                if ($unit === "MONTH" || $unit === "YEAR") {
                    $m =
                        ((int) date("Y", $t2) - (int) date("Y", $t1)) * 12 +
                        (int) date("n", $t2) -
                        (int) date("n", $t1);
                    if ($m > 0 && date("d H:i:s", $t2) < date("d H:i:s", $t1)) {
                        $m--;
                    } elseif (
                        $m < 0 &&
                        date("d H:i:s", $t2) > date("d H:i:s", $t1)
                    ) {
                        $m++;
                    }
                    return $unit === "YEAR" ? (int) ($m / 12) : $m;
                }
                throw new MongoSqlError("Unsupported TIMESTAMPDIFF unit $unit");
            case "DATEDIFF":
                $t1 = $this->ts($v(0));
                $t2 = $this->ts($v(1));
                return $t1 === null || $t2 === null
                    ? null
                    : (int) (floor($t1 / 86400) - floor($t2 / 86400));
            case "DATE_FORMAT":
                $t = $this->ts($v(0));
                if ($t === null) {
                    return null;
                }
                $map = [
                    "%Y" => "Y",
                    "%y" => "y",
                    "%m" => "m",
                    "%c" => "n",
                    "%d" => "d",
                    "%e" => "j",
                    "%H" => "H",
                    "%k" => "G",
                    "%h" => "h",
                    "%I" => "h",
                    "%i" => "i",
                    "%s" => "s",
                    "%S" => "s",
                    "%b" => "M",
                    "%M" => "F",
                    "%a" => "D",
                    "%W" => "l",
                    "%p" => "A",
                    "%j" => "z",
                    "%%" => "\%",
                ];
                $out = "";
                $f = (string) $v(1);
                for ($i = 0; $i < strlen($f); $i++) {
                    if ($f[$i] === "%" && $i + 1 < strlen($f)) {
                        $k = "%" . $f[++$i];
                        $out .= isset($map[$k])
                            ? ($k === "%j"
                                ? sprintf("%03d", date("z", $t) + 1)
                                : ($k === "%%"
                                    ? "%"
                                    : date($map[$k], $t)))
                            : $f[$i];
                    } else {
                        $out .= $f[$i];
                    }
                }
                return $out;
        }
        throw new MongoSqlError("Unsupported SQL function $name()");
    }
    private function agg($n, $env, $g)
    {
        $name = $n[1];
        $a = $n[2];
        if ($g === null) {
            $g = [$env];
        }
        if ($name === "COUNT" && (!$a || $a[0][0] === "star")) {
            return count($g);
        }
        $vals = [];
        foreach ($g as $e) {
            $x = $this->ev($a[0], $e, null);
            if ($x !== null) {
                $vals[] = $x;
            }
        }
        if ($n[3]) {
            $seen = [];
            $u = [];
            foreach ($vals as $x) {
                $k = $this->hk($x);
                if (!isset($seen[$k])) {
                    $seen[$k] = 1;
                    $u[] = $x;
                }
            }
            $vals = $u;
        }
        switch ($name) {
            case "COUNT":
                return count($vals);
            case "SUM":
                if (!$vals) {
                    return null;
                }
                $s = 0;
                foreach ($vals as $x) {
                    $s += $this->numv($x);
                }
                return $s;
            case "AVG":
                if (!$vals) {
                    return null;
                }
                $s = 0;
                foreach ($vals as $x) {
                    $s += $this->numv($x);
                }
                return $s / count($vals);
            case "MIN":
            case "MAX":
                if (!$vals) {
                    return null;
                }
                $b = $vals[0];
                foreach ($vals as $x) {
                    if (
                        $name === "MIN"
                            ? $this->cmpv($x, $b) < 0
                            : $this->cmpv($x, $b) > 0
                    ) {
                        $b = $x;
                    }
                }
                return $b;
            case "GROUP_CONCAT":
                return $vals ? implode(",", $vals) : null;
        }
    }

    /* ---- AST helpers ---- */
    private function mapNode($n, $fn)
    {
        // rebuild an expression tree, $fn may replace nodes
        if (!is_array($n)) {
            return $n;
        }
        if (isset($n["type"])) {
            return $n;
        }
        if (isset($n[0]) && is_string($n[0])) {
            $r = $fn($n);
            if ($r !== null) {
                return $r;
            }
            if ($n[0] === "sub" || $n[0] === "exists") {
                return $n;
            }
        }
        foreach ($n as $k => $c) {
            if (is_array($c)) {
                $n[$k] = $this->mapNode($c, $fn);
            }
        }
        return $n;
    }
    private function conjuncts($n, &$out)
    {
        if ($n === null) {
            return;
        }
        if ($n[0] === "b" && $n[1] === "AND") {
            $this->conjuncts($n[2], $out);
            $this->conjuncts($n[3], $out);
        } else {
            $out[] = $n;
        }
    }
    private function isConst($n)
    {
        return $n[0] === "n" ||
            $n[0] === "p" ||
            ($n[0] === "neg" && $this->isConst($n[1]));
    }
    private function noCol($n)
    {
        // true when an expression does not read any row
        if (!is_array($n)) {
            return true;
        }
        if (isset($n[0]) && is_string($n[0])) {
            if ($n[0] === "c" || $n[0] === "sub" || $n[0] === "exists") {
                return false;
            }
            if ($n[0] === "f" && in_array($n[1], self::AGG, true)) {
                return false;
            }
        }
        foreach ($n as $c) {
            if (is_array($c) && !$this->noCol($c)) {
                return false;
            }
        }
        return true;
    }

    /* ---- SELECT ---- */
    private function select($q, $outer)
    {
        // 1. sources
        $src = [];
        foreach ($q["from"] as $f) {
            if ($f["sub"] !== null) {
                $rows = $this->select($f["sub"], []);
                $cols = $rows
                    ? array_keys($rows[0])
                    : $this->subCols($f["sub"]);
                $src[] = [
                    "alias" => $f["alias"],
                    "table" => null,
                    "cols" => $cols,
                    "rows" => $rows,
                    "f" => $f,
                ];
            } else {
                $src[] = [
                    "alias" => $f["alias"],
                    "table" => $f["table"],
                    "cols" => $this->cols($f["table"]),
                    "rows" => null,
                    "f" => $f,
                ];
            }
        }
        // WHERE conditions of the form column = constant are sent to MongoDB
        $conj = [];
        $this->conjuncts($q["where"], $conj);
        foreach ($src as $k => &$s) {
            if ($s["rows"] !== null) {
                continue;
            }
            $filter =
                $s["f"]["join"] === "left"
                    ? []
                    : $this->pushdown($conj, $s, $src);
            $s["rows"] = $this->table($s["table"], $filter);
        }
        unset($s);

        // 2. joins
        $envs = [[]];
        $aliasCols = [];
        foreach ($src as $k => $s) {
            $aliasCols[$s["alias"]] = $s["cols"];
            if ($k === 0) {
                $envs = [];
                foreach ($s["rows"] as $r) {
                    $envs[] = [$s["alias"] => $r];
                }
                continue;
            }
            $envs = $this->join($envs, $s, $aliasCols);
        }
        if (!$q["from"]) {
            $envs = [[]];
        }
        if ($outer) {
            foreach ($envs as $i => $e) {
                $envs[$i] = $e + $outer;
            }
        }

        // 3. WHERE
        if ($q["where"] !== null) {
            $keep = [];
            foreach ($envs as $e) {
                if ($this->truthy($this->ev($q["where"], $e))) {
                    $keep[] = $e;
                }
            }
            $envs = $keep;
        }

        // 4. alias resolution for GROUP BY / HAVING / ORDER BY
        $items = [];
        $alias = [];
        foreach ($q["items"] as $it) {
            if ($it[0] === "star") {
                foreach ($src as $s) {
                    if ($it[1] === null || $it[1] === $s["alias"]) {
                        foreach ($s["cols"] as $c) {
                            $items[] = [["c", $s["alias"], $c], $c];
                        }
                    }
                }
                continue;
            }
            $items[] = [$it[1], $it[2]];
            $alias[$it[2]] = $it[1];
        }
        $allCols = [];
        foreach ($src as $s) {
            foreach ($s["cols"] as $c) {
                $allCols[$c] = 1;
            }
        }
        $fromAlias = function ($n) use ($alias, $allCols) {
            // FROM columns win, then select aliases (GROUP BY / HAVING)
            return $this->mapNode($n, function ($x) use ($alias, $allCols) {
                if (
                    $x[0] === "c" &&
                    $x[1] === null &&
                    !isset($allCols[$x[2]]) &&
                    isset($alias[$x[2]])
                ) {
                    return $alias[$x[2]];
                }
                return null;
            });
        };
        $aliasFirst = function ($n) use ($alias) {
            // select aliases win (ORDER BY)
            return $this->mapNode($n, function ($x) use ($alias) {
                if ($x[0] === "c" && $x[1] === null && isset($alias[$x[2]])) {
                    return $alias[$x[2]];
                }
                return null;
            });
        };
        $group = array_map($fromAlias, $q["group"]);
        $having = $q["having"] === null ? null : $fromAlias($q["having"]);
        $order = [];
        foreach ($q["order"] as $o) {
            $order[] = [$aliasFirst($o[0]), $o[1]];
        }

        // 5. grouping
        $agg = $group || $this->anyAgg($items, $having, $order);
        $groups = [];
        if ($agg) {
            if ($group) {
                $map = [];
                foreach ($envs as $e) {
                    $key = "";
                    foreach ($group as $ge) {
                        $x = $this->ev($ge, $e);
                        $key .= ($x === null ? "\0N" : $this->hk($x)) . "\x1f";
                    }
                    $map[$key][] = $e;
                }
                $groups = array_values($map);
            } else {
                $groups = [$envs];
            } // aggregate without GROUP BY: one group, even when empty
        } else {
            foreach ($envs as $e) {
                $groups[] = [$e];
            }
        }

        // 6. output rows
        $out = [];
        foreach ($groups as $gr) {
            $env = $gr ? $gr[0] : ($outer ?: []);
            $gg = $agg ? $gr : null;
            if (
                $having !== null &&
                !$this->truthy($this->ev($having, $env, $gg))
            ) {
                continue;
            }
            $row = [];
            foreach ($items as $it) {
                $row[$it[1]] = $this->plain($this->ev($it[0], $env, $gg));
            }
            $keys = [];
            foreach ($order as $o) {
                $keys[] = $this->ev($o[0], $env, $gg);
            }
            $out[] = [$row, $keys];
        }
        if ($having !== null && !$agg) {
            /* HAVING without aggregates behaves like WHERE (handled above) */
        }

        // 7. DISTINCT, ORDER BY, LIMIT
        if ($q["distinct"]) {
            $seen = [];
            $u = [];
            foreach ($out as $r) {
                $k = "";
                foreach ($r[0] as $x) {
                    $k .= ($x === null ? "\0N" : $this->hk($x)) . "\x1f";
                }
                if (!isset($seen[$k])) {
                    $seen[$k] = 1;
                    $u[] = $r;
                }
            }
            $out = $u;
        }
        if ($order) {
            usort($out, function ($a, $b) use ($order) {
                foreach ($order as $i => $o) {
                    $c = $this->cmpOrder($a[1][$i], $b[1][$i]);
                    if ($c) {
                        return $o[1] ? -$c : $c;
                    }
                }
                return 0;
            });
        }
        $rows = array_map(function ($r) {
            return $r[0];
        }, $out);
        if ($q["limit"] !== null) {
            $lim = (int) $this->numv($this->ev($q["limit"], []));
            $off =
                $q["offset"] === null
                    ? 0
                    : (int) $this->numv($this->ev($q["offset"], []));
            $rows = array_slice($rows, max(0, $off), max(0, $lim));
        }
        return $rows;
    }
    private function anyAgg($items, $having, $order)
    {
        foreach ($items as $it) {
            if ($this->hasAgg($it[0])) {
                return true;
            }
        }
        if ($having !== null && $this->hasAgg($having)) {
            return true;
        }
        foreach ($order as $o) {
            if ($this->hasAgg($o[0])) {
                return true;
            }
        }
        return false;
    }
    private function plain($v)
    {
        return is_array($v) && ($v[0] ?? "") === "iv" ? null : $v;
    }
    private function subCols($q)
    {
        $c = [];
        foreach ($q["items"] as $it) {
            if ($it[0] === "expr") {
                $c[] = $it[2];
            }
        }
        return $c;
    }
    private function owner($node, array $aliasCols)
    {
        // which source alias does a column node belong to?
        if ($node[0] !== "c") {
            return null;
        }
        if ($node[1] !== null) {
            return $node[1];
        }
        foreach ($aliasCols as $a => $cols) {
            if (in_array($node[2], $cols, true)) {
                return $a;
            }
        }
        return null;
    }
    private function pushdown(array $conj, array $s, array $all)
    {
        // WHERE col = const / col IN (consts) for one table -> MongoDB filter
        $filter = [];
        $def = $this->def($s["table"])["cols"];
        $others = [];
        foreach ($all as $o) {
            if ($o["alias"] !== $s["alias"]) {
                $others[$o["alias"]] = $o["cols"];
            }
        }
        $mine = [$s["alias"] => $s["cols"]];
        foreach ($conj as $c) {
            $col = null;
            $vals = null;
            if ($c[0] === "b" && $c[1] === "=") {
                if ($c[2][0] === "c" && $this->isConst($c[3])) {
                    $col = $c[2];
                    $vals = [$c[3]];
                } elseif ($c[3][0] === "c" && $this->isConst($c[2])) {
                    $col = $c[3];
                    $vals = [$c[2]];
                }
            } elseif (
                $c[0] === "in" &&
                !$c[3] &&
                $c[1][0] === "c" &&
                isset($c[2][0]) &&
                $c[2][0] !== "sub"
            ) {
                $col = $c[1];
                $vals = $c[2];
                foreach ($vals as $v) {
                    if (!$this->isConst($v)) {
                        $col = null;
                        break;
                    }
                }
            }
            if ($col === null) {
                continue;
            }
            if (
                $col[1] !== null
                    ? $col[1] !== $s["alias"]
                    : !isset($def[$col[2]]) ||
                        $this->colInOthers($col[2], $others)
            ) {
                continue;
            }
            if (!isset($def[$col[2]])) {
                continue;
            }
            $cls = $def[$col[2]][0];
            $list = [];
            foreach ($vals as $v) {
                $x = $this->ev($v, []);
                if ($x === null) {
                    $list = null;
                    break;
                }
                if ($cls === "int") {
                    if (
                        is_int($x) ||
                        (is_string($x) && preg_match('/^-?\d+$/', $x))
                    ) {
                        $list[] = (int) $x;
                    } else {
                        $list = null;
                        break;
                    }
                } elseif ($cls === "str") {
                    if (is_string($x)) {
                        $list[] = $x;
                    } else {
                        $list = null;
                        break;
                    }
                } elseif ($cls === "float") {
                    if (is_numeric($x)) {
                        $list[] = (float) $x;
                    } else {
                        $list = null;
                        break;
                    }
                } else {
                    $list = null;
                    break;
                }
            }
            if (!$list || isset($filter[$col[2]])) {
                continue;
            }
            $filter[$col[2]] =
                count($list) == 1
                    ? $list[0]
                    : ['$in' => array_values(array_unique($list))];
        }
        return $filter;
    }
    private function colInOthers($c, $others)
    {
        foreach ($others as $cols) {
            if (in_array($c, $cols, true)) {
                return true;
            }
        }
        return false;
    }

    private function join(array $envs, array $s, array $aliasCols)
    {
        $f = $s["f"];
        $alias = $s["alias"];
        $left = $f["join"] === "left";
        $on = $f["on"];
        $nullRow = array_fill_keys($s["cols"], null);
        $nullRow["_id"] = null;
        $out = [];
        // hash join when ON contains  right.col = <expression on the left side>
        $hashCol = null;
        $probe = null;
        if ($on !== null) {
            $cj = [];
            $this->conjuncts($on, $cj);
            foreach ($cj as $c) {
                if ($c[0] !== "b" || $c[1] !== "=") {
                    continue;
                }
                foreach ([[$c[2], $c[3]], [$c[3], $c[2]]] as $p) {
                    if (
                        $p[0][0] === "c" &&
                        $this->owner(
                            $p[0],
                            [$alias => $s["cols"]] + $aliasCols,
                        ) === $alias &&
                        !$this->mentions($p[1], $alias, $s["cols"])
                    ) {
                        $hashCol = $p[0][2];
                        $probe = $p[1];
                        break 2;
                    }
                }
            }
        }
        $idx = null;
        if ($hashCol !== null) {
            $idx = [];
            foreach ($s["rows"] as $r) {
                $v = $r[$hashCol] ?? null;
                if ($v !== null) {
                    $idx[$this->hk($v)][] = $r;
                }
            }
        }
        foreach ($envs as $e) {
            $cands = $s["rows"];
            if ($idx !== null) {
                $v = $this->ev($probe, $e);
                $cands = $v === null ? [] : $idx[$this->hk($v)] ?? [];
            }
            $hit = false;
            foreach ($cands as $r) {
                $ne = $e + [$alias => $r];
                if ($on === null || $this->truthy($this->ev($on, $ne))) {
                    $out[] = $ne;
                    $hit = true;
                }
            }
            if (!$hit && $left) {
                $out[] = $e + [$alias => $nullRow];
            }
        }
        return $out;
    }
    private function mentions($n, $alias, $cols)
    {
        // does an expression reference the given source?
        if (!is_array($n)) {
            return false;
        }
        if (isset($n[0]) && $n[0] === "c") {
            return $n[1] === $alias ||
                ($n[1] === null && in_array($n[2], $cols, true));
        }
        if (isset($n["type"])) {
            return true;
        } // be safe with sub-selects
        foreach ($n as $c) {
            if (is_array($c) && $this->mentions($c, $alias, $cols)) {
                return true;
            }
        }
        return false;
    }

    /* ---- INSERT / REPLACE ---- */
    private function newRow($table, array $given)
    {
        $d = $this->def($table);
        $row = [];
        foreach ($d["cols"] as $c => $def) {
            if (array_key_exists($c, $given)) {
                $row[$c] = $this->coerce($def[0], $given[$c]);
            } else {
                $row[$c] = $def[1] === "@now" ? $this->now : $def[1];
            }
        }
        if ($d["ai"] && ($row[$d["ai"]] === null || $row[$d["ai"]] === 0)) {
            $row[$d["ai"]] = (int) $this->store->nextId($table);
        }
        return $row;
    }
    private function keyLookups($table, array $row)
    {
        // filters that find rows which would clash with $row on a UNIQUE / PRIMARY key
        $d = $this->def($table);
        $sets = [];
        foreach ($d["unique"] as $cols) {
            $f = [];
            foreach ($cols as $c) {
                if ($row[$c] === null) {
                    $f = null;
                    break;
                }
                $f[$c] = $row[$c];
            }
            if ($f) {
                $sets[] = $f;
            }
        }
        if ($d["ai"] && $row[$d["ai"]] !== null) {
            $sets[] = [$d["ai"] => $row[$d["ai"]]];
        }
        if ($d["pk"] && $d["pk"] !== $d["ai"] && $row[$d["pk"]] !== null) {
            $sets[] = [$d["pk"] => $row[$d["pk"]]];
        }
        return $sets;
    }
    private function insertQ($q)
    {
        $table = $q["table"];
        $d = $this->def($table);
        $n = 0;
        $cols = $q["cols"] ?? array_keys($d["cols"]);
        foreach ($q["rows"] as $tuple) {
            if (count($tuple) !== count($cols)) {
                throw new MongoSqlError(
                    "Column count does not match value count",
                );
            }
            $given = [];
            foreach ($cols as $i => $c) {
                if (!isset($d["cols"][$c])) {
                    throw new MongoSqlError("Unknown column '$c' in '$table'");
                }
                $given[$c] = $this->plain($this->ev($tuple[$i], []));
            }
            $row = $this->newRow($table, $given);
            if ($q["mode"] === "replace") {
                foreach ($this->keyLookups($table, $row) as $f) {
                    foreach ($this->store->load($table, $f) as $old) {
                        $this->store->delete($table, [$old["_id"]]);
                        $this->log(["del", $table, $old["_id"], $old]);
                        $n++;
                    }
                }
            }
            try {
                $id = $this->store->insert($table, $row);
                $this->log(["ins", $table, $id]);
                $n++;
                if ($d["ai"]) {
                    $this->lastId = $row[$d["ai"]];
                }
            } catch (MongoDupKey $e) {
                if ($q["mode"] === "ignore") {
                    continue;
                }
                if ($q["mode"] === "insert" && $q["dup"] !== null) {
                    $n += 2 * $this->onDuplicate($q, $row);
                    continue;
                }
                throw new MongoSqlError(
                    "SQLSTATE[23000]: Integrity constraint violation: 1062 " .
                        $e->getMessage(),
                );
            }
        }
        $this->cache = [];
        return $n;
    }
    private function onDuplicate($q, $row)
    {
        $table = $q["table"];
        foreach ($this->keyLookups($table, $row) as $f) {
            foreach ($this->store->load($table, $f) as $doc) {
                $old = $this->shape($table, $doc);
                $env = [$table => $old];
                $set = [];
                foreach ($q["dup"] as $a) {
                    $set[$a[0]] = $this->coerce(
                        $this->def($table)["cols"][$a[0]][0],
                        $this->ev($a[1], $env),
                    );
                }
                $prev = [];
                foreach ($set as $c => $_) {
                    $prev[$c] = $old[$c];
                }
                $this->store->update($table, $old["_id"], $set, [], []);
                $this->log(["upd", $table, $old["_id"], $prev, []]);
                $this->cache = [];
                return 1;
            }
        }
        return 0;
    }

    /* ---- UPDATE / DELETE ---- */
    private function matching($table, $alias, $where, array $order, $limit)
    {
        $s = [
            "alias" => $alias,
            "table" => $table,
            "cols" => $this->cols($table),
        ];
        $conj = [];
        $this->conjuncts($where, $conj);
        $rows = $this->table($table, $this->pushdown($conj, $s, [$s]));
        $hit = [];
        foreach ($rows as $r) {
            $env = [$alias => $r];
            if ($where === null || $this->truthy($this->ev($where, $env))) {
                $hit[] = $r;
            }
        }
        if ($order) {
            usort($hit, function ($a, $b) use ($order, $alias) {
                foreach ($order as $o) {
                    $c = $this->cmpOrder(
                        $this->ev($o[0], [$alias => $a]),
                        $this->ev($o[0], [$alias => $b]),
                    );
                    if ($c) {
                        return $o[1] ? -$c : $c;
                    }
                }
                return 0;
            });
        }
        if ($limit !== null) {
            $hit = array_slice(
                $hit,
                0,
                (int) $this->numv($this->ev($limit, [])),
            );
        }
        return [$hit, $conj];
    }
    private function guardCols($conj, $alias, $table)
    {
        // columns the WHERE clause relied on: the write must still see the same values
        $cols = $this->def($table)["cols"];
        $g = [];
        foreach ($conj as $c) {
            $cand = [];
            if (
                $c[0] === "b" &&
                in_array($c[1], ["=", "<>", "<", ">", "<=", ">="], true)
            ) {
                $cand = [$c[2], $c[3]];
            } elseif (
                in_array($c[0], ["in", "isnull", "like", "between"], true)
            ) {
                $cand = [$c[1]];
            }
            foreach ($cand as $x) {
                if (
                    $x[0] === "c" &&
                    ($x[1] === null || $x[1] === $alias) &&
                    isset($cols[$x[2]])
                ) {
                    $g[$x[2]] = 1;
                }
            }
        }
        return array_keys($g);
    }
    private function updateQ($q)
    {
        $table = $q["table"];
        $alias = $q["alias"];
        $d = $this->def($table);
        $count = 0;
        [$rows, $conj] = $this->matching(
            $table,
            $alias,
            $q["where"],
            $q["order"],
            $q["limit"],
        );
        $guardCols = $this->guardCols($conj, $alias, $table);
        foreach ($rows as $row) {
            $env = [$alias => $row];
            $set = [];
            $inc = [];
            foreach ($q["set"] as [$col, $expr]) {
                if (!isset($d["cols"][$col])) {
                    throw new MongoSqlError(
                        "Unknown column '$col' in '$table'",
                    );
                }
                $cls = $d["cols"][$col][0];
                // col = col + n  ->  atomic $inc
                if (
                    in_array($cls, ["int", "float"], true) &&
                    $expr[0] === "b" &&
                    in_array($expr[1], ["+", "-"], true) &&
                    $expr[2][0] === "c" &&
                    $expr[2][2] === $col &&
                    ($expr[2][1] === null || $expr[2][1] === $alias) &&
                    $this->noCol($expr[3])
                ) {
                    $delta = $this->numv($this->ev($expr[3], $env));
                    if ($delta === null) {
                        $set[$col] = null;
                        continue;
                    }
                    $delta =
                        $cls === "int" ? (int) round($delta) : (float) $delta;
                    $inc[$col] = ($expr[1] === "+" ? 1 : -1) * $delta;
                    continue;
                }
                $val = $this->coerce(
                    $cls,
                    $this->plain($this->ev($expr, $env)),
                );
                if (
                    $val !== $row[$col] ||
                    (is_float($val) && $val != $row[$col])
                ) {
                    $set[$col] = $val;
                }
            }
            $inc = array_filter($inc, function ($x) {
                return $x != 0;
            });
            if (!$set && !$inc) {
                continue;
            }
            $guard = [];
            foreach ($guardCols as $c) {
                $guard[$c] = $row[$c];
            }
            if (
                $this->store->update($table, $row["_id"], $set, $inc, $guard) >
                0
            ) {
                $count++;
                $prev = [];
                foreach ($set as $c => $_) {
                    $prev[$c] = $row[$c];
                }
                $this->log([
                    "upd",
                    $table,
                    $row["_id"],
                    $prev,
                    array_map(function ($x) {
                        return -$x;
                    }, $inc),
                ]);
            }
        }
        $this->cache = [];
        return $count;
    }
    private function deleteQ($q)
    {
        [$rows] = $this->matching(
            $q["table"],
            $q["alias"],
            $q["where"],
            $q["order"],
            $q["limit"],
        );
        $ids = array_map(function ($r) {
            return $r["_id"];
        }, $rows);
        $n = $this->store->delete($q["table"], $ids);
        foreach ($rows as $r) {
            $doc = [];
            foreach ($r as $c => $v) {
                $doc[$c] = $v;
            }
            $this->log(["del", $q["table"], $r["_id"], $doc]);
        }
        $this->cache = [];
        return $n;
    }
}

/* ===================================================================================================
 *  PDO look-alike
 * ================================================================================================= */
class MongoPDO
{
    const FETCH_ASSOC = 2,
        FETCH_NUM = 3,
        FETCH_BOTH = 4,
        FETCH_COLUMN = 7;
    public $engine;
    public function __construct(MongoStoreInterface $store)
    {
        $this->engine = new MongoSqlEngine($store);
    }
    public function prepare($sql)
    {
        return new MongoStatement($this->engine, $sql);
    }
    public function query($sql)
    {
        $s = new MongoStatement($this->engine, $sql);
        $s->execute();
        return $s;
    }
    public function exec($sql)
    {
        $s = new MongoStatement($this->engine, $sql);
        $s->execute();
        return $s->rowCount();
    }
    public function lastInsertId()
    {
        return (string) $this->engine->lastId;
    }
    public function beginTransaction()
    {
        $this->engine->begin();
        return true;
    }
    public function commit()
    {
        $this->engine->commit();
        return true;
    }
    public function rollBack()
    {
        $this->engine->rollback();
        return true;
    }
    public function inTransaction()
    {
        return $this->engine->inTx();
    }
    public function setAttribute($a, $v)
    {
        return true;
    }
    public function quote($s)
    {
        return "'" . addcslashes((string) $s, "\\'") . "'";
    }
}

class MongoStatement implements IteratorAggregate
{
    private $eng,
        $sql,
        $rows = [],
        $pos = 0,
        $count = 0;
    public function __construct($eng, $sql)
    {
        $this->eng = $eng;
        $this->sql = $sql;
    }
    public function execute($params = null)
    {
        $r = $this->eng->run($this->sql, $params ?? []);
        $this->rows = $r["rows"];
        $this->count = $r["count"];
        $this->pos = 0;
        return true;
    }
    public function rowCount()
    {
        return $this->count;
    }
    public function fetch($mode = null)
    {
        if ($this->pos >= count($this->rows)) {
            return false;
        }
        $r = $this->rows[$this->pos++];
        if ($mode === MongoPDO::FETCH_NUM) {
            return array_values($r);
        }
        if ($mode === MongoPDO::FETCH_COLUMN) {
            return reset($r);
        }
        return $r;
    }
    public function fetchAll($mode = null, $arg = 0)
    {
        $rest = array_slice($this->rows, $this->pos);
        $this->pos = count($this->rows);
        if ($mode === MongoPDO::FETCH_COLUMN) {
            return array_map(function ($r) use ($arg) {
                return array_values($r)[$arg] ?? null;
            }, $rest);
        }
        if ($mode === MongoPDO::FETCH_NUM) {
            return array_map("array_values", $rest);
        }
        return $rest;
    }
    public function fetchColumn($n = 0)
    {
        if ($this->pos >= count($this->rows)) {
            return false;
        }
        return array_values($this->rows[$this->pos++])[$n] ?? null;
    }
    public function closeCursor()
    {
        return true;
    }
    public function columnCount()
    {
        return $this->rows ? count($this->rows[0]) : 0;
    }
    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->fetchAll());
    }
}
