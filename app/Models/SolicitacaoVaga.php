<?php
require_once __DIR__ . '/../classes/BaseModel.php';

class SolicitacaoVaga extends BaseModel {
    protected $table = 'solicitacoes_vagas';
    protected $primaryKey = 'id';

    public function createWithBind(array $data) {
        $sql = "INSERT INTO solicitacoes_vagas (
            area_departamento, quantidade_vagas, cargo, maquina_florestal, gestor_solicitante,
            tipo_vaga, nome_substituido, data_desligamento, motivo_saida, motivo_outros,
            tipo_contratacao, salario_previsto, beneficios, centro_custo, previsto_orcamento, justificativa_nao_previsto,
            jornada_trabalho, escala, turno,
            escolaridade_minima, formacao_academica, experiencia, entregas_esperadas, competencias_tecnicas, competencias_comportamentais, nivel_responsabilidade,
            data_inicio, urgencia, data_limite,
            lider_imediato, rh_responsavel, status, created_by
        ) VALUES (
            :area_departamento, :quantidade_vagas, :cargo, :maquina_florestal, :gestor_solicitante,
            :tipo_vaga, :nome_substituido, :data_desligamento, :motivo_saida, :motivo_outros,
            :tipo_contratacao, :salario_previsto, :beneficios, :centro_custo, :previsto_orcamento, :justificativa_nao_previsto,
            :jornada_trabalho, :escala, :turno,
            :escolaridade_minima, :formacao_academica, :experiencia, :entregas_esperadas, :competencias_tecnicas, :competencias_comportamentais, :nivel_responsabilidade,
            :data_inicio, :urgencia, :data_limite,
            :lider_imediato, :rh_responsavel, :status, :created_by
        )";
        $stmt = $this->db->prepare($sql);

        $stmt->bindParam(':area_departamento', $data['area_departamento'], PDO::PARAM_STR);
        $stmt->bindParam(':quantidade_vagas', $data['quantidade_vagas'], PDO::PARAM_INT);
        $stmt->bindParam(':cargo', $data['cargo'], PDO::PARAM_STR);
        $stmt->bindParam(':maquina_florestal', $data['maquina_florestal']);
        $stmt->bindParam(':gestor_solicitante', $data['gestor_solicitante'], PDO::PARAM_STR);

        $stmt->bindParam(':tipo_vaga', $data['tipo_vaga'], PDO::PARAM_STR);
        $stmt->bindParam(':nome_substituido', $data['nome_substituido']);
        $stmt->bindParam(':data_desligamento', $data['data_desligamento']);
        $stmt->bindParam(':motivo_saida', $data['motivo_saida']);
        $stmt->bindParam(':motivo_outros', $data['motivo_outros']);

        $stmt->bindParam(':tipo_contratacao', $data['tipo_contratacao'], PDO::PARAM_STR);
        $stmt->bindParam(':salario_previsto', $data['salario_previsto']);
        $stmt->bindParam(':beneficios', $data['beneficios']);
        $stmt->bindParam(':centro_custo', $data['centro_custo'], PDO::PARAM_STR);
        $stmt->bindParam(':previsto_orcamento', $data['previsto_orcamento'], PDO::PARAM_STR);
        $stmt->bindParam(':justificativa_nao_previsto', $data['justificativa_nao_previsto']);

        $stmt->bindParam(':jornada_trabalho', $data['jornada_trabalho']);
        $stmt->bindParam(':escala', $data['escala']);
        $stmt->bindParam(':turno', $data['turno'], PDO::PARAM_STR);

        $stmt->bindParam(':escolaridade_minima', $data['escolaridade_minima']);
        $stmt->bindParam(':formacao_academica', $data['formacao_academica']);
        $stmt->bindParam(':experiencia', $data['experiencia']);
        $stmt->bindParam(':entregas_esperadas', $data['entregas_esperadas']);
        $stmt->bindParam(':competencias_tecnicas', $data['competencias_tecnicas']);
        $stmt->bindParam(':competencias_comportamentais', $data['competencias_comportamentais']);
        $stmt->bindParam(':nivel_responsabilidade', $data['nivel_responsabilidade'], PDO::PARAM_STR);

        $stmt->bindParam(':data_inicio', $data['data_inicio']);
        $stmt->bindParam(':urgencia', $data['urgencia'], PDO::PARAM_STR);
        $stmt->bindParam(':data_limite', $data['data_limite']);

        $stmt->bindParam(':lider_imediato', $data['lider_imediato'], PDO::PARAM_STR);
        $stmt->bindParam(':rh_responsavel', $data['rh_responsavel'], PDO::PARAM_STR);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
        $stmt->bindParam(':created_by', $data['created_by']);

        $stmt->execute();
        return (int)$this->db->lastInsertId();
    }
}
?>
