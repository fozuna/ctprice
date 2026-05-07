<?php
/**
 * Modelo Client
 * Sistema BDO - Controle de Maquinários
 */

class Client extends BaseModel {
    protected $table = 'clients';
    
    /**
     * Buscar todos os clientes ativos
     */
    public function findAllActive() {
        return $this->findAll(['active' => 1], 'name ASC');
    }
    
    /**
     * Buscar cliente por código
     */
    public function findByCode($code) {
        return $this->findOne(['code' => $code]);
    }
    
    /**
     * Verificar se código já existe
     */
    public function codeExists($code, $excludeId = null) {
        $conditions = ['code' => $code];
        
        if ($excludeId) {
            $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE code = :code AND id != :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindValue(':code', $code);
            $stmt->bindValue(':id', $excludeId);
            $stmt->execute();
            $result = $stmt->fetch();
            return $result['count'] > 0;
        }
        
        $client = $this->findByCode($code);
        return $client !== false;
    }
    
    /**
     * Criar novo cliente
     */
    public function create($data) {
        // Validar dados obrigatórios
        if (empty($data['name']) || empty($data['code'])) {
            throw new Exception('Nome e código são obrigatórios');
        }
        
        // Verificar se código já existe
        if ($this->codeExists($data['code'])) {
            throw new Exception('Código já existe');
        }
        
        // Converter código para maiúsculo
        $data['code'] = strtoupper($data['code']);
        
        return parent::create($data);
    }
    
    /**
     * Atualizar cliente
     */
    public function update($id, $data) {
        // Validar dados obrigatórios
        if (empty($data['name']) || empty($data['code'])) {
            throw new Exception('Nome e código são obrigatórios');
        }
        
        // Verificar se código já existe (excluindo o próprio registro)
        if ($this->codeExists($data['code'], $id)) {
            throw new Exception('Código já existe');
        }
        
        // Converter código para maiúsculo
        $data['code'] = strtoupper($data['code']);
        
        return parent::update($id, $data);
    }
    
    /**
     * Desativar cliente (soft delete)
     */
    public function deactivate($id) {
        return $this->update($id, ['active' => 0]);
    }
    
    /**
     * Ativar cliente
     */
    public function activate($id) {
        return $this->update($id, ['active' => 1]);
    }
    
    /**
     * Atualizar status do cliente
     */
    public function updateStatus($id, $active) {
        return $this->update($id, ['active' => (int)$active]);
    }
    
    /**
     * Buscar clientes com contagem de metas
     */
    public function findAllWithGoalsCount() {
        $sql = "SELECT c.*, 
                       COUNT(g.id) as goals_count,
                       COUNT(CASE WHEN g.end_date >= CURDATE() THEN 1 END) as active_goals_count
                FROM {$this->table} c
                LEFT JOIN goals g ON c.id = g.client_id
                WHERE c.active = 1
                GROUP BY c.id
                ORDER BY c.name ASC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Verificar se cliente pode ser excluído
     */
    public function canDelete($id) {
        $sql = "SELECT COUNT(*) as count FROM goals WHERE client_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['count'] == 0;
    }
    
    /**
     * Excluir cliente (apenas se não tiver metas)
     */
    public function delete($id) {
        if (!$this->canDelete($id)) {
            throw new Exception('Não é possível excluir cliente que possui metas cadastradas');
        }
        
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id);
        return $stmt->execute();
    }
}
?>