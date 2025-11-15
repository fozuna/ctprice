<?php
$log = dirname(__DIR__) . '/storage/php-error.log';
$w = @file_put_contents($log, '[' . date('c') . "] ENTRY PUBLIC\n", FILE_APPEND);
if ($w === false) {
    @file_put_contents(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ctprice-php-error.log', '[' . date('c') . "] ENTRY PUBLIC (fallback)\n", FILE_APPEND);
}
try {
    require __DIR__ . '/../app/core/bootstrap.php';
} catch (\Throwable $e) {
    @file_put_contents($log, '[' . date('c') . "] PUBLIC BOOTSTRAP FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    exit;
}

use App\Core\Router;
use App\Core\Config;
use App\Controllers\HomeController;
use App\Controllers\AuthController;
use App\Controllers\AdminController;
use App\Controllers\AdminVagasController;
use App\Controllers\AdminCandidaturasController;
use App\Controllers\AdminBeneficiosController;
use App\Controllers\AdminUsuariosController;
use App\Controllers\ApiController;

$base = Config::app()['base_url'] ?? '';
$router = new Router($base);

// Rotas públicas
$router->get('/', [HomeController::class, 'index']);
$router->get('/vaga/{id}', [HomeController::class, 'vaga']);
$router->post('/candidatar/{id}', [HomeController::class, 'candidatar']);

// API
$router->post('/api/check-cpf', [ApiController::class, 'checkCpf']);

// Autenticação admin
$router->get('/admin/login', [AuthController::class, 'login']);
$router->post('/admin/login', [AuthController::class, 'doLogin']);
$router->get('/admin/logout', [AuthController::class, 'logout']);

// Painel Admin
$router->get('/admin', [AdminController::class, 'index']);

// Vagas (Admin)
$router->get('/admin/vagas', [AdminVagasController::class, 'index']);
$router->get('/admin/vagas/novo', [AdminVagasController::class, 'create']);
$router->post('/admin/vagas/novo', [AdminVagasController::class, 'store']);
$router->get('/admin/vagas/editar/{id}', [AdminVagasController::class, 'edit']);
$router->post('/admin/vagas/editar/{id}', [AdminVagasController::class, 'update']);
$router->post('/admin/vagas/excluir/{id}', [AdminVagasController::class, 'delete']);

// Candidaturas (Admin)
$router->get('/admin/candidaturas', [AdminCandidaturasController::class, 'index']);
$router->get('/admin/candidaturas/{id}', [AdminCandidaturasController::class, 'show']);
$router->get('/admin/candidaturas/{id}/download', [AdminCandidaturasController::class, 'download']);
$router->post('/admin/candidaturas/{id}/atualizar', [AdminCandidaturasController::class, 'update']);

// Benefícios (Admin)
$router->get('/admin/beneficios', [AdminBeneficiosController::class, 'index']);
$router->get('/admin/beneficios/novo', [AdminBeneficiosController::class, 'create']);
$router->post('/admin/beneficios/novo', [AdminBeneficiosController::class, 'store']);
$router->get('/admin/beneficios/editar/{id}', [AdminBeneficiosController::class, 'edit']);
$router->post('/admin/beneficios/editar/{id}', [AdminBeneficiosController::class, 'update']);
$router->post('/admin/beneficios/excluir/{id}', [AdminBeneficiosController::class, 'delete']);

// Usuários (Admin)
$router->get('/admin/usuarios/novo', [AdminUsuariosController::class, 'create']);
$router->post('/admin/usuarios/novo', [AdminUsuariosController::class, 'store']);

try {
    $router->dispatch();
} catch (\Throwable $e) {
    @file_put_contents($log, '[' . date('c') . "] DISPATCH FATAL: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
}