<?php
use App\Core\Security;
?>
<div class="flex items-center justify-between">
  <h2 class="text-xl font-semibold text-ctpblue">Vagas</h2>
  <a href="<?= $base ?>/admin/vagas/novo" class="bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark">Nova vaga</a>
</div>
<div class="mt-4 bg-white shadow rounded">
  <table class="min-w-full text-sm">
    <thead>
      <tr class="border-b">
        <th class="text-left p-3">Título</th>
        <th class="text-left p-3">Área</th>
        <th class="text-left p-3">Local</th>
        <th class="text-left p-3">Ativo</th>
        <th class="text-left p-3">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($vagas as $v): ?>
        <tr class="border-b hover:bg-gray-50">
          <td class="p-3 font-medium text-ctpblue"><?= Security::e($v['titulo']) ?></td>
          <td class="p-3"><?= Security::e($v['area']) ?></td>
          <td class="p-3"><?= Security::e($v['local']) ?></td>
          <td class="p-3">
            <span class="px-2 py-1 rounded text-white <?= (int)$v['ativo'] === 1 ? 'bg-ctgreen' : 'bg-red-400' ?>">
              <?= (int)$v['ativo'] === 1 ? 'Sim' : 'Não' ?>
            </span>
          </td>
          <td class="p-3 space-x-2">
            <a href="<?= $base ?>/admin/vagas/editar/<?= (int)$v['id'] ?>" class="text-ctpblue hover:text-ctgreen">Editar</a>
            <form action="<?= $base ?>/admin/vagas/excluir/<?= (int)$v['id'] ?>" method="post" class="inline" onsubmit="return confirm('Excluir esta vaga?');">
              <input type="hidden" name="csrf" value="<?= Security::e(\App\Core\Security::csrfToken()) ?>">
              <button class="text-red-600 hover:text-red-800">Excluir</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>