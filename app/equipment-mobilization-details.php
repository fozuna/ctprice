<?php
/**
 * Detalhes da Mobilização (AJAX)
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar autenticação
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn()) {
    http_response_code(403);
    echo '<div class="alert alert-danger">Acesso negado.</div>';
    exit();
}

$mobilizationModel = new EquipmentMobilization($db);

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    echo '<div class="alert alert-danger">ID da mobilização não informado.</div>';
    exit();
}

$mobilization = $mobilizationModel->findByIdWithDetails($id);
if (!$mobilization) {
    echo '<div class="alert alert-danger">Mobilização não encontrada.</div>';
    exit();
}

$statusClass = [
    'SOLICITADA' => 'warning',
    'APROVADA' => 'info',
    'EM_TRANSITO' => 'primary',
    'CONCLUIDA' => 'success',
    'CANCELADA' => 'danger'
];

$statusText = [
    'SOLICITADA' => 'Solicitada',
    'APROVADA' => 'Aprovada',
    'EM_TRANSITO' => 'Em Trânsito',
    'CONCLUIDA' => 'Concluída',
    'CANCELADA' => 'Cancelada'
];

$transportTypes = [
    'PRANCHA' => 'Prancha',
    'PROPRIO' => 'Próprio',
    'GUINCHO' => 'Guincho',
    'OUTRO' => 'Outro'
];
?>

<div class="row">
    <div class="col-md-6">
        <h6 class="text-muted">Informações Básicas</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>ID:</strong></td>
                <td><?php echo $mobilization['id']; ?></td>
            </tr>
            <tr>
                <td><strong>Status:</strong></td>
                <td>
                    <span class="badge bg-<?php echo $statusClass[$mobilization['status']]; ?>">
                        <?php echo $statusText[$mobilization['status']]; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td><strong>Equipamento:</strong></td>
                <td>
                    <strong><?php echo htmlspecialchars($mobilization['equipment_tag']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($mobilization['equipment_description']); ?></small>
                </td>
            </tr>
            <tr>
                <td><strong>Frente Origem:</strong></td>
                <td>
                    <?php if ($mobilization['from_front_name']): ?>
                        <?php echo htmlspecialchars($mobilization['from_front_name']); ?>
                        <small class="text-muted">(<?php echo htmlspecialchars($mobilization['from_front_code']); ?>)</small>
                    <?php else: ?>
                        <span class="text-muted">Primeira mobilização</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>Frente Destino:</strong></td>
                <td>
                    <?php echo htmlspecialchars($mobilization['to_front_name']); ?>
                    <small class="text-muted">(<?php echo htmlspecialchars($mobilization['to_front_code']); ?>)</small>
                </td>
            </tr>
            <tr>
                <td><strong>Data/Hora:</strong></td>
                <td>
                    <?php echo date('d/m/Y', strtotime($mobilization['mobilization_date'])); ?> às 
                    <?php echo date('H:i', strtotime($mobilization['mobilization_time'])); ?>
                </td>
            </tr>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-muted">Responsáveis</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Solicitante:</strong></td>
                <td><?php echo htmlspecialchars($mobilization['requested_by_name']); ?></td>
            </tr>
            <?php if ($mobilization['approved_by_name']): ?>
            <tr>
                <td><strong>Aprovado por:</strong></td>
                <td><?php echo htmlspecialchars($mobilization['approved_by_name']); ?></td>
            </tr>
            <?php endif; ?>
            <tr>
                <td><strong>Criado por:</strong></td>
                <td><?php echo htmlspecialchars($mobilization['created_by_name']); ?></td>
            </tr>
            <tr>
                <td><strong>Criado em:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($mobilization['created_at'])); ?></td>
            </tr>
            <?php if ($mobilization['updated_at'] !== $mobilization['created_at']): ?>
            <tr>
                <td><strong>Atualizado em:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($mobilization['updated_at'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
</div>

<div class="row mt-3">
    <div class="col-md-6">
        <h6 class="text-muted">Transporte</h6>
        <table class="table table-sm">
            <tr>
                <td><strong>Tipo:</strong></td>
                <td><?php echo $transportTypes[$mobilization['transport_type']]; ?></td>
            </tr>
            <?php if ($mobilization['transport_company']): ?>
            <tr>
                <td><strong>Empresa:</strong></td>
                <td><?php echo htmlspecialchars($mobilization['transport_company']); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($mobilization['transport_cost']): ?>
            <tr>
                <td><strong>Custo:</strong></td>
                <td>R$ <?php echo number_format($mobilization['transport_cost'], 2, ',', '.'); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($mobilization['departure_datetime']): ?>
            <tr>
                <td><strong>Saída:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($mobilization['departure_datetime'])); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($mobilization['arrival_datetime']): ?>
            <tr>
                <td><strong>Chegada:</strong></td>
                <td><?php echo date('d/m/Y H:i', strtotime($mobilization['arrival_datetime'])); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>
    
    <div class="col-md-6">
        <h6 class="text-muted">Cronologia</h6>
        <div class="timeline">
            <div class="timeline-item">
                <div class="timeline-marker bg-primary"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Solicitação Criada</h6>
                    <p class="timeline-text">
                        <?php echo date('d/m/Y H:i', strtotime($mobilization['created_at'])); ?><br>
                        <small class="text-muted">por <?php echo htmlspecialchars($mobilization['created_by_name']); ?></small>
                    </p>
                </div>
            </div>
            
            <?php if ($mobilization['status'] !== 'SOLICITADA'): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-info"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Aprovação</h6>
                    <p class="timeline-text">
                        <?php if ($mobilization['approved_by_name']): ?>
                            Aprovado por <?php echo htmlspecialchars($mobilization['approved_by_name']); ?>
                        <?php else: ?>
                            Status alterado para aprovado
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($mobilization['departure_datetime']): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-warning"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Transporte Iniciado</h6>
                    <p class="timeline-text">
                        <?php echo date('d/m/Y H:i', strtotime($mobilization['departure_datetime'])); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($mobilization['arrival_datetime']): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-success"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Mobilização Concluída</h6>
                    <p class="timeline-text">
                        <?php echo date('d/m/Y H:i', strtotime($mobilization['arrival_datetime'])); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if ($mobilization['status'] === 'CANCELADA'): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-danger"></div>
                <div class="timeline-content">
                    <h6 class="timeline-title">Mobilização Cancelada</h6>
                    <p class="timeline-text">
                        <?php echo date('d/m/Y H:i', strtotime($mobilization['updated_at'])); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row mt-3">
    <div class="col-12">
        <h6 class="text-muted">Motivo da Mobilização</h6>
        <div class="alert alert-light">
            <?php echo nl2br(htmlspecialchars($mobilization['reason'])); ?>
        </div>
    </div>
</div>

<?php if ($mobilization['observations']): ?>
<div class="row">
    <div class="col-12">
        <h6 class="text-muted">Observações</h6>
        <div class="alert alert-info">
            <?php echo nl2br(htmlspecialchars($mobilization['observations'])); ?>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-marker {
    position: absolute;
    left: -22px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px 15px;
    border-radius: 5px;
    border-left: 3px solid #dee2e6;
}

.timeline-title {
    margin: 0 0 5px 0;
    font-size: 14px;
    font-weight: 600;
}

.timeline-text {
    margin: 0;
    font-size: 13px;
    color: #6c757d;
}
</style>