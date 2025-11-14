<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class Candidatura
{
    public static function create(array $data): int
    {
        self::ensureCpfColumn();
        $sql = 'INSERT INTO candidaturas (vaga_id, nome, email, telefone, cpf, cargo_pretendido, experiencia, pdf_path, status) VALUES (?,?,?,?,?,?,?,?,?)';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([
            (int)$data['vaga_id'], $data['nome'], $data['email'], $data['telefone'], $data['cpf'], $data['cargo_pretendido'], $data['experiencia'], $data['pdf_path'], $data['status'] ?? 'novo'
        ]);
        return (int)Database::conn()->lastInsertId();
    }

    public static function all(array $filters = []): array
    {
        $sql = 'SELECT c.*, v.titulo AS vaga_titulo FROM candidaturas c LEFT JOIN vagas v ON v.id = c.vaga_id WHERE 1=1';
        $params = [];
        if (!empty($filters['vaga_id'])) { $sql .= ' AND c.vaga_id = ?'; $params[] = (int)$filters['vaga_id']; }
        if (!empty($filters['status'])) { $sql .= ' AND c.status = ?'; $params[] = $filters['status']; }
        if (!empty($filters['data_de'])) { $sql .= ' AND c.created_at >= ?'; $params[] = $filters['data_de']; }
        if (!empty($filters['data_ate'])) { $sql .= ' AND c.created_at <= ?'; $params[] = $filters['data_ate']; }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function find(int $id): ?array
    {
        $sql = 'SELECT c.*, v.titulo AS vaga_titulo FROM candidaturas c LEFT JOIN vagas v ON v.id = c.vaga_id WHERE c.id = ? LIMIT 1';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        return $data ?: null;
    }

    public static function updateStatusNotes(int $id, string $status, ?string $observacoes = null, ?int $usuarioId = null): bool
    {
        self::ensureObservacoesColumn();
        self::ensureHistoricoTable();
        
        // Buscar status anterior para histórico
        $candidatura = self::find($id);
        $statusAnterior = $candidatura['status'] ?? null;
        
        $sql = 'UPDATE candidaturas SET status = ?, observacoes = ? WHERE id = ?';
        $stmt = Database::conn()->prepare($sql);
        $result = $stmt->execute([$status, $observacoes, $id]);
        
        if ($result) {
            // Salvar no histórico
            self::addHistorico($id, $statusAnterior, $status, $observacoes, $usuarioId);
        }
        
        return $result;
    }

    public static function getHistorico(int $candidaturaId): array
    {
        $sql = 'SELECT h.*, u.nome AS usuario_nome FROM candidatura_historico h 
                LEFT JOIN usuarios u ON u.id = h.usuario_id 
                WHERE h.candidatura_id = ? 
                ORDER BY h.created_at DESC';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$candidaturaId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function addHistorico(int $candidaturaId, ?string $statusAnterior, string $statusNovo, ?string $observacoes, ?int $usuarioId): void
    {
        $sql = 'INSERT INTO candidatura_historico (candidatura_id, status_anterior, status_novo, observacoes, usuario_id) VALUES (?, ?, ?, ?, ?)';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$candidaturaId, $statusAnterior, $statusNovo, $observacoes, $usuarioId]);
    }

    private static function ensureHistoricoTable(): void
    {
        try {
            $db = Database::conn();
            $check = $db->prepare('SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $check->execute(['candidatura_historico']);
            $exists = (int)($check->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
            if (!$exists) {
                $db->exec("CREATE TABLE candidatura_historico (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    candidatura_id INT NOT NULL,
                    status_anterior VARCHAR(30) DEFAULT NULL,
                    status_novo VARCHAR(30) NOT NULL,
                    observacoes TEXT DEFAULT NULL,
                    usuario_id INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    CONSTRAINT fk_hist_candidatura FOREIGN KEY (candidatura_id) REFERENCES candidaturas(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            }
        } catch (\Throwable $e) {
            // Silencia para não quebrar fluxo caso permissões de ALTER falhem
        }
    }

    private static function ensureObservacoesColumn(): void
    {
        try {
            $db = Database::conn();
            $check = $db->prepare('SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $check->execute(['candidaturas', 'observacoes']);
            $exists = (int)($check->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
            if (!$exists) {
                // MySQL 8+ supports IF NOT EXISTS; para versões anteriores, o SELECT acima evita erro
                $db->exec('ALTER TABLE candidaturas ADD COLUMN observacoes TEXT NULL');
            }
        } catch (\Throwable $e) {
            // Silencia para não quebrar fluxo caso permissões de ALTER falhem; a coluna será simplesmente ignorada
        }
    }

    private static function ensureCpfColumn(): void
    {
        try {
            $db = Database::conn();
            $check = $db->prepare('SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
            $check->execute(['candidaturas', 'cpf']);
            $exists = (int)($check->fetch(PDO::FETCH_ASSOC)['cnt'] ?? 0) > 0;
            if (!$exists) {
                // Para migrações em bancos já existentes, adiciona como NULL para evitar falhas
                $db->exec('ALTER TABLE candidaturas ADD COLUMN cpf VARCHAR(11) NULL');
                // Opcional: tentar criar índice único se possível
                try {
                    $db->exec('ALTER TABLE candidaturas ADD UNIQUE INDEX uq_candidaturas_cpf (cpf)');
                } catch (\Throwable $e2) { /* ignora se já existir ou se houver duplicatas */ }
            }
        } catch (\Throwable $e) {
            // Silencia para não quebrar fluxo
        }
    }

    // Helpers de apresentação de status
    public static function statusMap(): array
    {
        return [
            'novo' => ['label' => 'Novo', 'bg' => '#edede9', 'text' => '#00222C'],
            'em_analise' => ['label' => 'Em análise', 'bg' => '#669bbc', 'text' => '#ffffff'],
            'entrevista' => ['label' => 'Entrevista', 'bg' => '#003049', 'text' => '#ffffff'],
            'aprovado' => ['label' => 'Aprovado', 'bg' => '#00222C', 'text' => '#ffffff'],
            'dispensado' => ['label' => 'Dispensado', 'bg' => '#c1121f', 'text' => '#ffffff'],
        ];
    }

    public static function statusLabel(string $code): string
    {
        $m = self::statusMap();
        return $m[$code]['label'] ?? $code;
    }

    public static function statusBg(string $code): string
    {
        $m = self::statusMap();
        return $m[$code]['bg'] ?? '#e5e7eb';
    }

    public static function statusTextColor(string $code): string
    {
        $m = self::statusMap();
        return $m[$code]['text'] ?? '#111111';
    }

    public static function cpfExists(string $cpf): bool
    {
        self::ensureCpfColumn();
        $sql = 'SELECT COUNT(*) as count FROM candidaturas WHERE cpf = ? LIMIT 1';
        $stmt = Database::conn()->prepare($sql);
        $stmt->execute([$cpf]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0) > 0;
    }
}