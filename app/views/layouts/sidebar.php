<?php
use App\Core\Auth;
?>
<aside class="fixed inset-y-0 left-0 w-64 shadow-sm z-30" style="background-color: #00222C;">
  <div class="h-full flex flex-col">
    <div class="px-4 py-4 flex items-center justify-center border-b border-gray-600">
        <img src="<?= $base ?>/assets/logo.png" alt="CT Price" class="h-8 w-auto object-contain">
      </div>
    <nav class="mt-2 flex-1 px-2 space-y-1 text-sm">
      <?php if (Auth::check()): ?>
        <a href="<?= $base ?>/admin" class="flex items-center px-3 py-2 rounded hover:bg-[#10E36B] hover:bg-opacity-50 text-gray-200 hover:text-white transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="8" height="8" rx="2"/><rect x="13" y="3" width="8" height="8" rx="2"/><rect x="3" y="13" width="8" height="8" rx="2"/><rect x="13" y="13" width="8" height="8" rx="2"/></svg>
          <span>Dashboard</span>
        </a>
        <a href="<?= $base ?>/admin/vagas" class="flex items-center px-3 py-2 rounded hover:bg-[#10E36B] hover:bg-opacity-50 text-gray-200 hover:text-white transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 8V6h6v2"/><rect x="3" y="8" width="18" height="12" rx="2"/></svg>
          <span>Vagas</span>
        </a>
        <a href="<?= $base ?>/admin/candidaturas" class="flex items-center px-3 py-2 rounded hover:bg-[#10E36B] hover:bg-opacity-50 text-gray-200 hover:text-white transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="8" cy="8" r="3"/><circle cx="16" cy="12" r="3"/></svg>
          <span>Candidaturas</span>
        </a>
        <a href="<?= $base ?>/admin/beneficios" class="flex items-center px-3 py-2 rounded hover:bg-[#10E36B] hover:bg-opacity-50 text-gray-200 hover:text-white transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="8" width="18" height="12" rx="2"/><path d="M12 8v12"/><path d="M7 8c0-2 2-3 5-3s5 1 5 3"/></svg>
          <span>Benefícios</span>
        </a>
        <?php if (Auth::role() === 'admin'): ?>
        <a href="<?= $base ?>/admin/usuarios/novo" class="flex items-center px-3 py-2 rounded hover:bg-[#10E36B] hover:bg-opacity-50 text-gray-200 hover:text-white transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 4v16M4 12h16"/></svg>
          <span>Novo usuário</span>
        </a>
        <?php endif; ?>
        <a href="<?= $base ?>/admin/logout" class="flex items-center px-3 py-2 rounded hover:bg-red-600 hover:bg-opacity-20 text-red-300 hover:text-red-200 transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="4" width="14" height="16" rx="2"/><path d="M12 12h8M16 8l4 4-4 4"/></svg>
          <span>Sair</span>
        </a>
      <?php else: ?>
        <a href="<?= $base ?>/" class="flex items-center px-3 py-2 rounded hover:bg-[#10E36B] hover:bg-opacity-50 text-gray-200 hover:text-white transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 8V6h6v2"/><rect x="3" y="8" width="18" height="12" rx="2"/></svg>
          <span>Vagas</span>
        </a>
        <a href="<?= $base ?>/admin/login" class="flex items-center px-3 py-2 rounded hover:bg-[#10E36B] hover:bg-opacity-50 text-gray-300 hover:text-white transition-colors">
          <svg class="w-4 h-4 mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          <span>Área administrativa</span>
        </a>
      <?php endif; ?>
    </nav>
    <div class="px-4 py-4 text-xs text-gray-400 border-t border-gray-600 text-center">
      <span><?= \App\Core\Config::app()['product_name'] ?? 'TRAXTER RH' ?> • v<?= \App\Core\Config::app()['version'] ?? '' ?></span>
    </div>
  </div>
</aside>