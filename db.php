<?php
// includes/db.php
// MySQL connection using PDO

ddefine('DB_HOST', 'mysql.railway.internal');
define('DB_NAME', 'railway');
define('DB_USER', 'root');
define('DB_PASS', 'WugZsbasyrjrXdJQMvBQSGdLDAkYVoWN');    // ← blank by default in XAMPP, add password if you set one

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// SELECT — returns array of rows
function dbSelect($sql, $params = []) {
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

// SELECT single row
function dbSelectOne($sql, $params = []) {
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (PDOException $e) {
        return null;
    }
}

// INSERT / UPDATE / DELETE
function dbExecute($sql, $params = []) {
    try {
        $stmt = getDB()->prepare($sql);
        $stmt->execute($params);
        return ['success' => true, 'lastId' => getDB()->lastInsertId()];
    } catch (PDOException $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
