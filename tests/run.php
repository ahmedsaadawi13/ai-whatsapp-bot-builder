<?php
// FILE: /tests/run.php

/**
 * Test runner.
 *
 *   php tests/run.php                 # all tests
 *   php tests/run.php BotEngineTest   # one class
 *   php tests/run.php ApiTest webhook # one class, methods containing "webhook"
 *
 * Uses a separate database (TEST_DB_NAME, default ai_whatsapp_bot_test) that is
 * DROPPED and re-created from database.sql before every test class.
 * Connection settings come from .env / environment (DB_HOST, DB_USER, DB_PASS).
 */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}

$testDb = getenv('TEST_DB_NAME') ?: 'ai_whatsapp_bot_test';
if (!preg_match('/^[a-zA-Z0-9_]+_test$/', $testDb)) {
    fwrite(STDERR, "Refusing to run: TEST_DB_NAME must end with _test\n");
    exit(1);
}
putenv('DB_NAME=' . $testDb);
putenv('APP_DEBUG=true');
putenv('APP_KEY=test-app-key');

require_once __DIR__ . '/../app/bootstrap.php';
require_once __DIR__ . '/TestCase.php';

/**
 * Re-create the test database from database.sql (statement splitter that
 * respects quoted strings).
 */
function reset_test_database($testDb) {
    $config = require APP_ROOT . '/config/database.php';
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $config['host'], $config['port']),
        $config['username'], $config['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $sql = file_get_contents(APP_ROOT . '/database.sql');
    $sql = str_replace('`ai_whatsapp_bot`', '`' . $testDb . '`', $sql);
    $pdo->exec('DROP DATABASE IF EXISTS `' . $testDb . '`');

    $statement = '';
    $quote = null;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $ch = $sql[$i];
        if ($quote === null && $ch === '-' && substr($sql, $i, 3) === '-- ') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $length : $end;
            continue;
        }
        $statement .= $ch;
        if ($quote !== null) {
            if ($ch === '\\') {
                $statement .= $sql[++$i];
            } elseif ($ch === $quote) {
                $quote = null;
            }
        } elseif ($ch === "'" || $ch === '"' || $ch === '`') {
            $quote = $ch;
        } elseif ($ch === ';') {
            if (trim($statement, " \n\r\t;") !== '') {
                $pdo->exec($statement);
            }
            $statement = '';
        }
    }
    if (trim($statement) !== '') {
        $pdo->exec($statement);
    }

    // The shared app connection lost its default schema when it was dropped.
    Database::getInstance()->getConnection()->exec('USE `' . $testDb . '`');
}

$classFilter = isset($argv[1]) ? $argv[1] : null;
$methodFilter = isset($argv[2]) ? $argv[2] : null;

$files = glob(__DIR__ . '/*Test.php');
sort($files);
$passed = 0;
$failed = [];
$start = microtime(true);

foreach ($files as $file) {
    $class = basename($file, '.php');
    if ($classFilter !== null && strcasecmp($class, $classFilter) !== 0) {
        continue;
    }
    require_once $file;
    reset_test_database($testDb);
    echo "\n{$class}\n";
    $test = new $class();
    $test->setUpClass();
    $test->run($methodFilter);
    if (method_exists($test, 'tearDownClass')) {
        $test->tearDownClass();
    }
    $passed += $test->passed;
    $failed = array_merge($failed, $test->failed);
}

printf("\n%d passed, %d failed (%.1fs)\n", $passed, count($failed), microtime(true) - $start);
foreach ($failed as $failure) {
    echo " - {$failure}\n";
}
exit(empty($failed) ? 0 : 1);
