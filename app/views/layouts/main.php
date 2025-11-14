<?php
use App\Core\Security;
?><!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>CT Price - Gestão de Currículos</title>
  <link rel="stylesheet" href="<?= $base ?>/assets/tailwind.css">
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style> body { font-family: 'Montserrat', system-ui, -apple-system, sans-serif; } </style>
</head>
<body class="min-h-screen bg-gray-50">
  <?php 
  // Check if this is the login page to provide full-screen layout
  $isLoginPage = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/login') !== false;
  ?>
  
  <?php if (!$isLoginPage): ?>
  <header class="bg-ctpblue text-white">
    <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
      <div class="flex items-center space-x-3">
        <img src="<?= $base ?>/assets/logo.png" alt="CT Price" class="h-4 w-auto max-w-[140px]" />
        <span class="font-semibold text-sm md:text-base whitespace-nowrap"">Sistema de Gestão de RH</span>
      </div>
      <nav class="text-sm flex items-center" style="gap: 1.25rem;">
        <a href="<?= $base ?>/" class="hover:text-ctgreen">Vagas</a>
        <a href="https://ctprice.com.br/wp/" class="hover:text-ctgreen" target="_blank" rel="noopener noreferrer">Página Inicial</a>
      </nav>
    </div>
  </header>
  <?php endif; ?>

  <main class="<?= $isLoginPage ? '' : 'max-w-6xl mx-auto px-4 py-8 min-h-screen pb-24' ?>">
    <?= $content ?>
  </main>

  <?php if (!$isLoginPage): ?>
  <footer class="fixed bottom-0 left-0 right-0 border-t bg-white">
    <div class="max-w-6xl mx-auto px-4 py-6 text-gray-500 text-sm text-center">
      © <?= date('Y') ?> <?= \App\Core\Config::app()['product_name'] ?? 'TRAXTER RH' ?>. Todos os direitos reservados. • v<?= \App\Core\Config::app()['version'] ?? '' ?>
    </div>
  </footer>
  <?php endif; ?>
</body>
</html>