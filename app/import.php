<?php
/**
 * Sistema de Importação CSV
 * Sistema BDO - Controle de Maquinários
 */

require_once 'config/config.php';

// Verificar autenticação e permissão de administrador
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);

if (!$auth->isLoggedIn() || !$auth->hasPermission(ROLE_ADMIN)) {
    header('Location: login.php');
    exit();
}

$pageTitle = 'Importação de Dados';
$equipmentModel = new Equipment($db);
$userModel = new User($db);
$occurrenceTypeModel = new OccurrenceType($db);
$frontModel = new Front($db);
$auditLog = new AuditLog($db);

$message = '';
$messageType = '';

// Processar upload de arquivo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $importType = $_POST['import_type'] ?? '';
    $file = $_FILES['csv_file'];
    
    if ($file['error'] === UPLOAD_ERR_OK) {
        $result = processCSVImport($file, $importType);
        $message = $result['message'];
        $messageType = $result['type'];
    } else {
        $message = 'Erro no upload do arquivo.';
        $messageType = 'error';
    }
}

function processCSVImport($file, $importType) {
    global $equipmentModel, $userModel, $occurrenceTypeModel, $frontModel, $auditLog;
    
    $tempFile = $file['tmp_name'];
    $handle = fopen($tempFile, 'r');
    
    if (!$handle) {
        return ['message' => 'Erro ao abrir o arquivo CSV.', 'type' => 'error'];
    }
    
    // Detectar encoding e converter se necessário
    $content = file_get_contents($tempFile);
    $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
    
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        file_put_contents($tempFile, $content);
    }
    
    $handle = fopen($tempFile, 'r');
    
    $successCount = 0;
    $errorCount = 0;
    $errors = [];
    $lineNumber = 0;
    
    // Pular cabeçalho
    $header = fgetcsv($handle, 1000, ';');
    
    try {
        switch ($importType) {
            case 'equipments':
                $result = importEquipments($handle, $successCount, $errorCount, $errors, $lineNumber);
                break;
            case 'users':
                $result = importUsers($handle, $successCount, $errorCount, $errors, $lineNumber);
                break;
            case 'occurrence_types':
                $result = importOccurrenceTypes($handle, $successCount, $errorCount, $errors, $lineNumber);
                break;
            case 'fronts':
                $result = importFronts($handle, $successCount, $errorCount, $errors, $lineNumber);
                break;
            default:
                return ['message' => 'Tipo de importação inválido.', 'type' => 'error'];
        }
        
        fclose($handle);
        
        $message = "Importação concluída: {$successCount} registros importados";
        if ($errorCount > 0) {
            $message .= ", {$errorCount} erros encontrados";
        }
        
        if (!empty($errors)) {
            $message .= "\n\nErros encontrados:\n" . implode("\n", array_slice($errors, 0, 10));
            if (count($errors) > 10) {
                $message .= "\n... e mais " . (count($errors) - 10) . " erros.";
            }
        }
        
        return ['message' => $message, 'type' => $errorCount > 0 ? 'warning' : 'success'];
        
    } catch (Exception $e) {
        fclose($handle);
        return ['message' => 'Erro durante a importação: ' . $e->getMessage(), 'type' => 'error'];
    }
}

function importEquipments($handle, &$successCount, &$errorCount, &$errors, &$lineNumber) {
    global $equipmentModel, $frontModel, $auditLog;
    
    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
        $lineNumber++;
        
        if (count($data) < 4) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Dados insuficientes";
            continue;
        }
        
        $tag = trim($data[0]);
        $description = trim($data[1]);
        $frontCode = trim($data[2]);
        $currentHourMeter = floatval(str_replace(',', '.', $data[3]));
        
        if (empty($tag) || empty($description) || empty($frontCode)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Campos obrigatórios vazios";
            continue;
        }
        
        // Verificar se a frente existe
        $front = $frontModel->findByCode($frontCode);
        if (!$front) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Frente '{$frontCode}' não encontrada";
            continue;
        }
        
        // Verificar se o equipamento já existe
        if ($equipmentModel->tagExists($tag)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Equipamento '{$tag}' já existe";
            continue;
        }
        
        try {
            $equipmentData = [
                'tag' => $tag,
                'description' => $description,
                'front_id' => $front['id'],
                'current_hours' => $currentHourMeter,
                'status' => 'ATIVO'
            ];
            
            $equipmentId = $equipmentModel->create($equipmentData);
            
            if ($equipmentId) {
                $successCount++;
                $auditLog->logInsert('equipments', $equipmentId, $equipmentData, $_SESSION['user_id']);
            } else {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Erro ao criar equipamento '{$tag}'";
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: {$e->getMessage()}";
        }
    }
}

function importUsers($handle, &$successCount, &$errorCount, &$errors, &$lineNumber) {
    global $userModel, $frontModel, $auditLog;
    
    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
        $lineNumber++;
        
        if (count($data) < 5) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Dados insuficientes";
            continue;
        }
        
        $name = trim($data[0]);
        $email = trim($data[1]);
        $role = trim($data[2]);
        $frontCode = trim($data[3]);
        $password = trim($data[4]);
        
        if (empty($name) || empty($email) || empty($role) || empty($password)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Campos obrigatórios vazios";
            continue;
        }
        
        $validRoles = [ROLE_ADMIN, ROLE_SUPERVISOR, ROLE_LIDER, ROLE_OPERADOR, ROLE_COORD_RH];
        if (!in_array($role, $validRoles)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Role '{$role}' inválida";
            continue;
        }
        
        // Verificar se a frente existe (se informada)
        $frontId = null;
        if (!empty($frontCode)) {
            $front = $frontModel->findByCode($frontCode);
            if (!$front) {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Frente '{$frontCode}' não encontrada";
                continue;
            }
            $frontId = $front['id'];
        }
        
        // Verificar se o email já existe
        if ($userModel->emailExists($email)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Email '{$email}' já existe";
            continue;
        }
        
        try {
            $userData = [
                'name' => $name,
                'email' => $email,
                'password' => $password, // Será hasheada no modelo
                'role' => $role,
                'front_id' => $frontId,
                'status' => 'ATIVO'
            ];
            
            $userId = $userModel->create($userData);
            
            if ($userId) {
                $successCount++;
                // Não logar a senha no audit
                unset($userData['password']);
                $auditLog->logInsert('users', $userId, $userData, $_SESSION['user_id']);
            } else {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Erro ao criar usuário '{$email}'";
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: {$e->getMessage()}";
        }
    }
}

function importOccurrenceTypes($handle, &$successCount, &$errorCount, &$errors, &$lineNumber) {
    global $occurrenceTypeModel, $auditLog;
    
    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
        $lineNumber++;
        
        if (count($data) < 3) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Dados insuficientes";
            continue;
        }
        
        $code = trim($data[0]);
        $description = trim($data[1]);
        $category = trim($data[2]);
        
        if (empty($code) || empty($description) || empty($category)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Campos obrigatórios vazios";
            continue;
        }
        
        // Validar categoria
        $validCategories = ['PRODUCAO', 'MANUTENCAO', 'PARADA'];
        if (!in_array($category, $validCategories)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Categoria '{$category}' inválida";
            continue;
        }
        
        // Verificar se o código já existe
        if ($occurrenceTypeModel->codeExists($code)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Código '{$code}' já existe";
            continue;
        }
        
        try {
            $typeData = [
                'code' => $code,
                'description' => $description,
                'category' => $category,
                'status' => 'ATIVO'
            ];
            
            $typeId = $occurrenceTypeModel->create($typeData);
            
            if ($typeId) {
                $successCount++;
                $auditLog->logInsert('occurrence_types', $typeId, $typeData, $_SESSION['user_id']);
            } else {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Erro ao criar tipo '{$code}'";
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: {$e->getMessage()}";
        }
    }
}

function importFronts($handle, &$successCount, &$errorCount, &$errors, &$lineNumber) {
    global $frontModel, $auditLog;
    
    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
        $lineNumber++;
        
        if (count($data) < 2) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Dados insuficientes";
            continue;
        }
        
        $code = trim($data[0]);
        $name = trim($data[1]);
        $description = isset($data[2]) ? trim($data[2]) : '';
        
        if (empty($code) || empty($name)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Campos obrigatórios vazios";
            continue;
        }
        
        // Verificar se o código já existe
        if ($frontModel->codeExists($code)) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: Código '{$code}' já existe";
            continue;
        }
        
        try {
            $frontData = [
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'status' => 'ATIVO'
            ];
            
            $frontId = $frontModel->create($frontData);
            
            if ($frontId) {
                $successCount++;
                $auditLog->logInsert('fronts', $frontId, $frontData, $_SESSION['user_id']);
            } else {
                $errorCount++;
                $errors[] = "Linha {$lineNumber}: Erro ao criar frente '{$code}'";
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = "Linha {$lineNumber}: {$e->getMessage()}";
        }
    }
}

include 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Mensagem de resultado -->
    <?php if (!empty($message)): ?>
        <div class="p-4 rounded-lg <?php 
            echo $messageType === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 
                ($messageType === 'warning' ? 'bg-yellow-100 border border-yellow-400 text-yellow-700' : 
                'bg-red-100 border border-red-400 text-red-700'); 
        ?>">
            <pre class="whitespace-pre-wrap"><?php echo htmlspecialchars($message); ?></pre>
        </div>
    <?php endif; ?>

    <!-- Instruções de Importação -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <h2 class="text-lg font-semibold text-rich-black mb-4">Importação de Dados via CSV</h2>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Formulário de Upload -->
            <div>
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Tipo de Importação</label>
                        <select name="import_type" required 
                                class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                            <option value="">Selecione o tipo</option>
                            <option value="fronts">Frentes de Serviço</option>
                            <option value="equipments">Equipamentos</option>
                            <option value="occurrence_types">Tipos de Ocorrência</option>
                            <option value="users">Usuários</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-paynes-gray mb-2">Arquivo CSV</label>
                        <input type="file" name="csv_file" accept=".csv" required 
                               class="w-full px-3 py-2 border border-silver-lake-blue rounded-lg focus:ring-2 focus:ring-paynes-gray">
                        <p class="text-xs text-paynes-gray mt-1">
                            Arquivo deve estar em formato CSV com separador ponto e vírgula (;)
                        </p>
                    </div>
                    
                    <button type="submit" class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-upload mr-2"></i>Importar Dados
                    </button>
                </form>
            </div>
            
            <!-- Instruções -->
            <div>
                <h3 class="text-md font-semibold text-rich-black mb-3">Instruções de Formato</h3>
                
                <div class="space-y-4">
                    <div class="border border-silver-lake-blue rounded-lg p-3">
                        <h4 class="font-medium text-paynes-gray mb-2">Frentes de Serviço</h4>
                        <p class="text-sm text-paynes-gray mb-2">Colunas obrigatórias:</p>
                        <code class="text-xs bg-eggshell p-2 rounded block">
                            Código;Nome;Descrição
                        </code>
                        <p class="text-xs text-paynes-gray mt-1">Exemplo: FR01;Frente Norte;Área de produção norte</p>
                    </div>
                    
                    <div class="border border-silver-lake-blue rounded-lg p-3">
                        <h4 class="font-medium text-paynes-gray mb-2">Equipamentos</h4>
                        <p class="text-sm text-paynes-gray mb-2">Colunas obrigatórias:</p>
                        <code class="text-xs bg-eggshell p-2 rounded block">
                            Tag;Descrição;Código Frente;Horímetro Atual
                        </code>
                        <p class="text-xs text-paynes-gray mt-1">Exemplo: EQ001;Escavadeira CAT 320;FR01;1250.5</p>
                    </div>
                    
                    <div class="border border-silver-lake-blue rounded-lg p-3">
                        <h4 class="font-medium text-paynes-gray mb-2">Tipos de Ocorrência</h4>
                        <p class="text-sm text-paynes-gray mb-2">Colunas obrigatórias:</p>
                        <code class="text-xs bg-eggshell p-2 rounded block">
                            Código;Descrição;Categoria
                        </code>
                        <p class="text-xs text-paynes-gray mt-1">Exemplo: PROD01;Operação Normal;PRODUCAO</p>
                        <p class="text-xs text-paynes-gray mt-1">Categorias válidas: PRODUCAO, MANUTENCAO, PARADA</p>
                    </div>
                    
                    <div class="border border-silver-lake-blue rounded-lg p-3">
                        <h4 class="font-medium text-paynes-gray mb-2">Usuários</h4>
                        <p class="text-sm text-paynes-gray mb-2">Colunas obrigatórias:</p>
                        <code class="text-xs bg-eggshell p-2 rounded block">
                            Nome;Email;Role;Código Frente;Senha
                        </code>
                        <p class="text-xs text-paynes-gray mt-1">Exemplo: João Silva;joao@empresa.com;OPERATOR;FR01;123456</p>
                        <p class="text-xs text-paynes-gray mt-1">Roles válidas: ADMIN, SUPERVISOR, LEADER, OPERATOR</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Templates para Download -->
    <div class="bg-white rounded-lg shadow-sm border border-silver-lake-blue p-6">
        <h2 class="text-lg font-semibold text-rich-black mb-4">Templates de Exemplo</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            <button onclick="downloadTemplate('fronts')" 
                    class="p-4 border border-silver-lake-blue rounded-lg hover:bg-eggshell transition-colors text-center">
                <i class="fas fa-download text-blue-600 text-2xl mb-2"></i>
                <div class="text-sm font-medium text-rich-black">Template Frentes</div>
                <div class="text-xs text-paynes-gray">Baixar exemplo CSV</div>
            </button>
            
            <button onclick="downloadTemplate('equipments')" 
                    class="p-4 border border-silver-lake-blue rounded-lg hover:bg-eggshell transition-colors text-center">
                <i class="fas fa-download text-blue-600 text-2xl mb-2"></i>
                <div class="text-sm font-medium text-rich-black">Template Equipamentos</div>
                <div class="text-xs text-paynes-gray">Baixar exemplo CSV</div>
            </button>
            
            <button onclick="downloadTemplate('occurrence_types')" 
                    class="p-4 border border-silver-lake-blue rounded-lg hover:bg-eggshell transition-colors text-center">
                <i class="fas fa-download text-blue-600 text-2xl mb-2"></i>
                <div class="text-sm font-medium text-rich-black">Template Tipos</div>
                <div class="text-xs text-paynes-gray">Baixar exemplo CSV</div>
            </button>
            
            <button onclick="downloadTemplate('users')" 
                    class="p-4 border border-silver-lake-blue rounded-lg hover:bg-eggshell transition-colors text-center">
                <i class="fas fa-download text-blue-600 text-2xl mb-2"></i>
                <div class="text-sm font-medium text-rich-black">Template Usuários</div>
                <div class="text-xs text-paynes-gray">Baixar exemplo CSV</div>
            </button>
        </div>
    </div>
</div>

<script>
function downloadTemplate(type) {
    let content = '';
    let filename = '';
    
    switch(type) {
        case 'fronts':
            content = 'Código;Nome;Descrição\nFR01;Frente Norte;Área de produção norte\nFR02;Frente Sul;Área de produção sul';
            filename = 'template_frentes.csv';
            break;
        case 'equipments':
            content = 'Tag;Descrição;Código Frente;Horímetro Atual\nEQ001;Escavadeira CAT 320;FR01;1250.5\nEQ002;Caminhão Volvo FH;FR01;2500.0';
            filename = 'template_equipamentos.csv';
            break;
        case 'occurrence_types':
            content = 'Código;Descrição;Categoria\nPROD01;Operação Normal;PRODUCAO\nMANT01;Manutenção Preventiva;MANUTENCAO\nPAR01;Parada para Abastecimento;PARADA';
            filename = 'template_tipos_ocorrencia.csv';
            break;
        case 'users':
            content = 'Nome;Email;Role;Código Frente;Senha\nJoão Silva;joao@empresa.com;OPERATOR;FR01;123456\nMaria Santos;maria@empresa.com;LEADER;FR01;123456';
            filename = 'template_usuarios.csv';
            break;
    }
    
    // Criar e baixar arquivo
    const blob = new Blob(['\ufeff' + content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include 'includes/footer.php'; ?>
