<?php
use App\Core\Security;
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CT Price - Painel</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/tailwind.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style> body { font-family: 'Montserrat', system-ui, -apple-system, sans-serif; } </style>
</head>
<body class="min-h-screen bg-gray-50">
  <?php include APP_PATH . '/views/layouts/sidebar.php'; ?>

  <main class="ml-64 px-6 py-8 min-h-screen pb-24">
    <?= $content ?>
  </main>

  <footer class="fixed bottom-0 left-64 right-0 border-t bg-white z-20">
    <div class="px-6 py-3 text-gray-500 text-sm text-center">
      © <?= date('Y') ?> <?= \App\Core\Config::app()['product_name'] ?? 'TRAXTER RH' ?>. Todos os direitos reservados. • v<?= \App\Core\Config::app()['version'] ?? '' ?>
    </div>
  </footer>
</body>
</html>