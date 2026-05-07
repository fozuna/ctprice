<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Portal do Fornecedor - Login';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pageTitle; ?></title>
  <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
  <div class="bg-white w-full max-w-md p-6 rounded-lg shadow space-y-4">
    <h1 class="text-xl font-semibold text-center">Portal do Fornecedor</h1>
    <div id="msg" class="text-red-600 text-sm"></div>
    <div>
      <label class="form-label">Email</label>
      <input id="email" type="email" class="form-input" required>
    </div>
    <div>
      <label class="form-label">Senha</label>
      <input id="password" type="password" class="form-input" required>
    </div>
    <button id="login" class="btn-primary w-full">Entrar</button>
  </div>
  <script>
  document.getElementById('login').addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('action','supplier_login');
    fd.append('email', document.getElementById('email').value);
    fd.append('password', document.getElementById('password').value);
    const res = await fetch('procurement-api.php', { method:'POST', body: fd, credentials:'same-origin' });
    const txt = await res.text(); let r; try { r = JSON.parse(txt); } catch { r = { ok:false, message:'Resposta inválida' }; }
    if (r.ok) { window.location.href = 'supplier-portal.php'; } else { document.getElementById('msg').textContent = r.message; }
  });
  </script>
</body>
</html>
