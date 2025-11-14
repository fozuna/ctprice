<?php
use App\Core\Security;
?>
<div class="flex items-center justify-between">
  <h2 class="text-xl font-semibold text-ctpblue">Benefícios</h2>
  <a href="<?= $base ?>/admin/beneficios/novo" class="bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark">Novo benefício</a>
</div>

<div class="mt-4 bg-white shadow rounded">
  <table class="min-w-full text-sm">
    <thead>
      <tr class="text-left text-gray-500 border-b">
        <th class="p-3">Logo</th>
        <th class="p-3">Nome</th>
        <th class="p-3">Parceiro</th>
        <th class="p-3">Ativo</th>
        <th class="p-3">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach (($beneficios ?? []) as $b): ?>
        <tr class="border-b">
          <td class="p-3">
            <?php if (!empty($b['logo_path'])): ?>
              <img src="<?= $base ?>/uploads/logos/<?= Security::e($b['logo_path']) ?>" alt="Logo" class="h-10 w-10 object-contain" />
            <?php else: ?>
              <span class="text-gray-400">—</span>
            <?php endif; ?>
          </td>
          <td class="p-3"><?= Security::e($b['nome']) ?></td>
          <td class="p-3"><?= Security::e($b['parceiro'] ?? '') ?></td>
          <td class="p-3"><?= (int)($b['ativo'] ?? 0) === 1 ? 'Sim' : 'Não' ?></td>
          <td class="p-3 space-x-2">
            <a href="<?= $base ?>/admin/beneficios/editar/<?= (int)$b['id'] ?>" class="text-ctpblue hover:text-ctgreen">Editar</a>
            <form action="<?= $base ?>/admin/beneficios/excluir/<?= (int)$b['id'] ?>" method="post" class="inline">
              <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>" />
              <button type="submit" class="text-red-600 hover:text-red-800" onclick="return confirm('Excluir este benefício?')">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($beneficios)): ?>
        <tr>
          <td colspan="5" class="p-4 text-center text-gray-500">Nenhum benefício cadastrado</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>