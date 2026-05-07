<?php

$cmd = $argv[1] ?? 'status';
$migrationDir = __DIR__ . '/../database/migrations';
if (!is_dir($migrationDir)) {
    @mkdir($migrationDir, 0775, true);
}

if ($cmd === 'make') {
    $name = $argv[2] ?? '';
    if ($name === '') {
        fwrite(STDERR, "Uso: php scripts/migrate.php make <nome>\n");
        exit(1);
    }
    $safe = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $name));
    $safe = trim($safe, '_');
    $ts = date('YmdHis');
    $id = $ts . '_' . $safe;
    $path = $migrationDir . DIRECTORY_SEPARATOR . $id . '.php';
    if (file_exists($path)) {
        fwrite(STDERR, "Já existe: {$path}\n");
        exit(1);
    }
    $tpl = "<?php\n\nreturn new class {\n    public string \$id = '{$id}';\n\n    public function up(PDO \$db): void {\n    }\n\n    public function down(PDO \$db): void {\n    }\n};\n";
    file_put_contents($path, $tpl);
    echo $path . "\n";
    exit(0);
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';

$db = Database::getInstance()->getConnection();

function mig_exec(PDO $db, string $sql): void {
    $db->exec($sql);
}

function mig_table_exists(PDO $db, string $table): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $st->execute([':t' => $table]);
    return (int)$st->fetchColumn() > 0;
}

function mig_column_exists(PDO $db, string $table, string $col): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c");
    $st->execute([':t' => $table, ':c' => $col]);
    return (int)$st->fetchColumn() > 0;
}

function mig_index_exists(PDO $db, string $table, string $index): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND INDEX_NAME = :i");
    $st->execute([':t' => $table, ':i' => $index]);
    return (int)$st->fetchColumn() > 0;
}

function mig_fk_exists(PDO $db, string $table, string $fk): bool {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = :t AND CONSTRAINT_NAME = :c");
    $st->execute([':t' => $table, ':c' => $fk]);
    return (int)$st->fetchColumn() > 0;
}

function mig_ensure_migrations_table(PDO $db): void {
    mig_exec($db, "CREATE TABLE IF NOT EXISTS schema_migrations (id VARCHAR(255) PRIMARY KEY, applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
}

function mig_list_files(string $dir): array {
    $files = glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*.php') ?: [];
    sort($files);
    return $files;
}

function mig_applied(PDO $db): array {
    mig_ensure_migrations_table($db);
    $rows = $db->query("SELECT id FROM schema_migrations ORDER BY applied_at ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    return array_map(fn($r) => (string)$r['id'], $rows);
}

function mig_load(string $file) {
    $m = require $file;
    if (!is_object($m) || !method_exists($m, 'up') || !method_exists($m, 'down') || !property_exists($m, 'id')) {
        throw new RuntimeException('Migration inválida: ' . basename($file));
    }
    return $m;
}

if ($cmd === 'status') {
    $applied = array_flip(mig_applied($db));
    foreach (mig_list_files($migrationDir) as $f) {
        $m = mig_load($f);
        $mark = isset($applied[$m->id]) ? 'up' : 'down';
        echo $mark . "\t" . $m->id . "\t" . basename($f) . "\n";
    }
    exit(0);
}

if ($cmd === 'check') {
    $applied = array_flip(mig_applied($db));
    $pending = [];
    foreach (mig_list_files($migrationDir) as $f) {
        $m = mig_load($f);
        if (!isset($applied[$m->id])) {
            $pending[] = $m->id;
            echo "pending\t" . $m->id . "\t" . basename($f) . "\n";
        }
    }
    if ($pending !== []) {
        fwrite(STDERR, "Há migrations pendentes. Execute: php scripts/migrate.php up\n");
        exit(1);
    }
    echo "ok\tno_pending_migrations\n";
    exit(0);
}

if ($cmd === 'up') {
    mig_ensure_migrations_table($db);
    $applied = array_flip(mig_applied($db));
    foreach (mig_list_files($migrationDir) as $f) {
        $m = mig_load($f);
        if (isset($applied[$m->id])) continue;
        $m->up($db);
        $ins = $db->prepare('INSERT INTO schema_migrations (id) VALUES (:id)');
        $ins->execute([':id' => $m->id]);
        echo "applied\t" . $m->id . "\n";
    }
    exit(0);
}

if ($cmd === 'down') {
    mig_ensure_migrations_table($db);
    $id = $argv[2] ?? null;
    if ($id === null) {
        $row = $db->query('SELECT id FROM schema_migrations ORDER BY applied_at DESC, id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        $id = $row['id'] ?? null;
    }
    if (!$id) {
        echo "no_migrations\n";
        exit(0);
    }
    $file = $migrationDir . DIRECTORY_SEPARATOR . $id . '.php';
    if (!is_file($file)) {
        throw new RuntimeException('Arquivo da migration não encontrado para id=' . $id);
    }
    $m = mig_load($file);
    $m->down($db);
    $del = $db->prepare('DELETE FROM schema_migrations WHERE id = :id');
    $del->execute([':id' => $id]);
    echo "rolled_back\t" . $id . "\n";
    exit(0);
}

if ($cmd === 'refresh') {
    mig_ensure_migrations_table($db);
    $rows = $db->query('SELECT id FROM schema_migrations ORDER BY applied_at DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    foreach ($rows as $r) {
        $id = (string)$r['id'];
        $file = $migrationDir . DIRECTORY_SEPARATOR . $id . '.php';
        if (!is_file($file)) continue;
        $m = mig_load($file);
        $m->down($db);
        $del = $db->prepare('DELETE FROM schema_migrations WHERE id = :id');
        $del->execute([':id' => $id]);
        echo "rolled_back\t" . $id . "\n";
    }
    $argv = [$argv[0], 'up'];
    $cmd = 'up';
    $applied = array_flip(mig_applied($db));
    foreach (mig_list_files($migrationDir) as $f) {
        $m = mig_load($f);
        if (isset($applied[$m->id])) continue;
        $m->up($db);
        $ins = $db->prepare('INSERT INTO schema_migrations (id) VALUES (:id)');
        $ins->execute([':id' => $m->id]);
        echo "applied\t" . $m->id . "\n";
    }
    exit(0);
}

fwrite(STDERR, "Comando desconhecido: {$cmd}\n");
exit(1);
