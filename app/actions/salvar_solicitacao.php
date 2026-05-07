<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/Database.php';
require_once __DIR__ . '/../classes/Auth.php';
require_once __DIR__ . '/../models/SolicitacaoVaga.php';

try {
    $db = Database::getInstance()->getConnection();
    $auth = new Auth($db);
    if (!$auth->isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada. Faça login.']); exit;
    }
    $u = function($s){ return trim(strip_tags((string)$s)); };
    $payload = [
        'area_departamento' => $u($_POST['area_departamento'] ?? ''),
        'quantidade_vagas'  => (int)($_POST['quantidade_vagas'] ?? 0),
        'cargo'             => $u($_POST['cargo'] ?? ''),
        'maquina_florestal' => $u($_POST['maquina_florestal'] ?? ''),
        'gestor_solicitante'=> $u($_POST['gestor_solicitante'] ?? ''),
        'tipo_vaga'         => $u($_POST['tipo_vaga'] ?? ''),
        'nome_substituido'  => $u($_POST['nome_substituido'] ?? ''),
        'data_desligamento' => $u($_POST['data_desligamento'] ?? ''),
        'motivo_saida'      => $u($_POST['motivo_saida'] ?? ''),
        'motivo_outros'     => $u($_POST['motivo_outros'] ?? ''),
        'tipo_contratacao'  => $u($_POST['tipo_contratacao'] ?? ''),
        'salario_previsto'  => $_POST['salario_previsto'] !== '' ? number_format((float)str_replace(',', '.', $_POST['salario_previsto']), 2, '.', '') : null,
        'beneficios'        => $u($_POST['beneficios'] ?? ''),
        'centro_custo'      => $u($_POST['centro_custo'] ?? ''),
        'previsto_orcamento'=> $u($_POST['previsto_orcamento'] ?? ''),
        'justificativa_nao_previsto' => $u($_POST['justificativa_nao_previsto'] ?? ''),
        'jornada_trabalho'  => $u($_POST['jornada_trabalho'] ?? ''),
        'escala'            => $u($_POST['escala'] ?? ''),
        'turno'             => $u($_POST['turno'] ?? ''),
        'escolaridade_minima' => $u($_POST['escolaridade_minima'] ?? ''),
        'formacao_academica'  => $u($_POST['formacao_academica'] ?? ''),
        'experiencia'         => $u($_POST['experiencia'] ?? ''),
        'entregas_esperadas'  => $u($_POST['entregas_esperadas'] ?? ''),
        'competencias_tecnicas' => $u($_POST['competencias_tecnicas'] ?? ''),
        'competencias_comportamentais' => $u($_POST['competencias_comportamentais'] ?? ''),
        'nivel_responsabilidade' => $u($_POST['nivel_responsabilidade'] ?? ''),
        'data_inicio'       => $u($_POST['data_inicio'] ?? ''),
        'urgencia'          => $u($_POST['urgencia'] ?? ''),
        'data_limite'       => $u($_POST['data_limite'] ?? ''),
        'lider_imediato'    => $u($_POST['lider_imediato'] ?? ''),
        'rh_responsavel'    => $u($_POST['rh_responsavel'] ?? ''),
        'status'            => 'em_analise',
        'created_by'        => $_SESSION['user_id'] ?? null,
    ];
    $required = ['area_departamento','quantidade_vagas','cargo','gestor_solicitante','tipo_vaga','tipo_contratacao','centro_custo','previsto_orcamento','turno','nivel_responsabilidade','data_inicio','urgencia','lider_imediato','rh_responsavel'];
    foreach ($required as $k) {
        if ($payload[$k] === '' || $payload[$k] === null || $payload[$k] === 0) {
            echo json_encode(['success'=>false,'message'=>"Campo obrigatório ausente: {$k}."]); exit;
        }
    }
    if ($payload['tipo_vaga'] === 'substituicao') {
        if ($payload['nome_substituido'] === '' || $payload['data_desligamento'] === '' || $payload['motivo_saida'] === '') {
            echo json_encode(['success'=>false,'message'=>'Preencha os campos de substituição.']); exit;
        }
        if ($payload['motivo_saida'] === 'outros' && $payload['motivo_outros'] === '') {
            echo json_encode(['success'=>false,'message'=>'Informe o motivo (outros).']); exit;
        }
    } else {
        $payload['nome_substituido'] = null;
        $payload['data_desligamento'] = null;
        $payload['motivo_saida'] = null;
        $payload['motivo_outros'] = null;
    }
    if ($payload['previsto_orcamento'] === 'nao' && $payload['justificativa_nao_previsto'] === '') {
        echo json_encode(['success'=>false,'message'=>'Justifique por que não está previsto no orçamento.']); exit;
    }
    $model = new SolicitacaoVaga($db);
    $id = $model->createWithBind($payload);
    echo json_encode(['success'=>true,'message'=>'Solicitação registrada com sucesso.','id'=>$id]); exit;
} catch (Throwable $e) {
    echo json_encode(['success'=>false,'message'=>'Erro: '.$e->getMessage()]); exit;
}
