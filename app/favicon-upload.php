<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
$database = Database::getInstance();
$db = $database->getConnection();
$auth = new Auth($db);
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php', true, 302);
    exit;
}
$canManage = $auth->hasPermission(ROLE_ADMIN);
$error = null;
$success = null;
$uploadedPath = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canManage) {
    if (!isset($_FILES['favicon']) || !is_array($_FILES['favicon'])) {
        $error = 'Arquivo não enviado.';
    } else {
        $f = $_FILES['favicon'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $error = 'Falha no upload: código ' . $f['error'];
        } elseif ($f['size'] <= 0 || $f['size'] > MAX_UPLOAD_SIZE) {
            $error = 'Tamanho inválido.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($f['tmp_name']);
            $allowed = [
                'image/x-icon' => 'ico',
                'image/vnd.microsoft.icon' => 'ico',
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];
            if (!isset($allowed[$mime])) {
                $error = 'Tipo de arquivo não suportado.';
            } else {
                $orig = basename($f['name']);
                $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
                $dstExt = $allowed[$mime];
                if ($ext !== $dstExt) {
                    $ext = $dstExt;
                }
                $base = preg_replace('/[^A-Za-z0-9\.\-_]/', '_', pathinfo($orig, PATHINFO_FILENAME));
                if ($base === '' || $base === '.' || $base === '..') {
                    $base = 'favicon_' . date('Ymd_His');
                }
                $fileName = $base . '.' . $ext;
                $dir = __DIR__ . '/public/assets/favicons';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $dest = $dir . '/' . $fileName;
                if (!@move_uploaded_file($f['tmp_name'], $dest)) {
                    $error = 'Não foi possível salvar o arquivo no destino.';
                } else {
                    $rel = 'assets/favicons/' . $fileName;
                    $cfgPath = __DIR__ . '/config/site.json';
                    $cfg = [];
                    if (is_file($cfgPath)) {
                        $j = @file_get_contents($cfgPath);
                        $d = json_decode((string)$j, true);
                        if (is_array($d)) { $cfg = $d; }
                    }
                    $cfg['favicon_path'] = $rel;
                    $cfg['favicon_updated_at'] = date('c');
                    @file_put_contents($cfgPath, json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
                    $uploadedPath = $rel;
                    $success = 'Favicon atualizado com sucesso.';
                }
            }
        }
    }
}
$pageTitle = 'Favicon';
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-3xl mx-auto">
  <div class="bg-white shadow rounded-lg p-6 space-y-4">
    <h3 class="text-lg font-semibold">Atualizar Favicon</h3>
    <?php if (!$canManage): ?>
      <div class="alert-info">Acesso restrito.</div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded"><?php echo htmlspecialchars($success); ?></div>
      <?php endif; ?>
      <form method="post" enctype="multipart/form-data" class="space-y-4">
        <div>
          <label for="favicon" class="form-label">Imagem</label>
          <input id="favicon" name="favicon" type="file" accept=".ico,image/x-icon,image/vnd.microsoft.icon,image/png,image/jpeg,image/gif,image/webp" class="form-input" required>
        </div>
        <p class="text-sm text-gray-600">Formatos suportados: ICO, PNG, JPEG, GIF, WEBP. O nome do arquivo será preservado. Cache com versão automática.</p>
        <div>
          <button type="submit" class="btn-primary">Enviar</button>
        </div>
      </form>
      <div class="mt-6">
        <h4 class="font-medium mb-2">Pré-visualização</h4>
        <?php
          $cfgFile = __DIR__ . '/config/site.json';
          $path = null;
          if (is_file($cfgFile)) {
            $d = json_decode((string)@file_get_contents($cfgFile), true);
            if (is_array($d) && !empty($d['favicon_path'])) {
              $path = ltrim($d['favicon_path'], '/');
            }
          }
          if ($path) {
            $abs = __DIR__ . '/' . $path;
            $ver = is_file($abs) ? (@filemtime($abs) ?: time()) : time();
            $url = asset_url($path) . '?v=' . $ver;
            echo '<img src="' . htmlspecialchars($url) . '" alt="Favicon" class="w-16 h-16 object-contain border rounded p-2 bg-white shadow"/>';
          } else {
            echo '<p class="text-sm text-gray-600">Nenhum favicon configurado.</p>';
          }
        ?>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
