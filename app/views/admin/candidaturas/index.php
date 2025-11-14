<?php
use App\Core\Security;
use App\Models\Candidatura;
?>
<div class="bg-white shadow rounded p-6">
  <h2 class="text-xl font-semibold text-ctpblue">Candidaturas</h2>
  <form class="mt-4 grid md:grid-cols-4 gap-3" method="get">
    <div>
      <label class="block text-sm">Vaga</label>
      <select name="vaga_id" class="mt-1 w-full border rounded px-3 py-2">
        <option value="">Todas</option>
        <?php foreach ($vagas as $v): ?>
          <option value="<?= (int)$v['id'] ?>" <?= ($filters['vaga_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= Security::e($v['titulo']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm">Status</label>
      <select name="status" class="mt-1 w-full border rounded px-3 py-2">
        <option value="">Todos</option>
        <?php foreach (array_keys(Candidatura::statusMap()) as $st): ?>
          <option value="<?= $st ?>" <?= ($filters['status'] ?? '') === $st ? 'selected' : '' ?>><?= Candidatura::statusLabel($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm">De</label>
      <input type="date" name="data_de" value="<?= Security::e($filters['data_de'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2" />
    </div>
    <div>
      <label class="block text-sm">Até</label>
      <input type="date" name="data_ate" value="<?= Security::e($filters['data_ate'] ?? '') ?>" class="mt-1 w-full border rounded px-3 py-2" />
    </div>
    <div class="md:col-span-4">
      <button class="bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark">Filtrar</button>
    </div>
  </form>

  <div class="mt-6 overflow-x-auto">
    <table class="min-w-full text-sm">
      <thead>
        <tr class="border-b">
          <th class="text-left p-3">#</th>
          <th class="text-left p-3">Vaga</th>
          <th class="text-left p-3">Nome</th>
          <th class="text-left p-3">E-mail</th>
          <th class="text-left p-3">Telefone</th>
          <th class="text-left p-3">Status</th>
          <th class="text-left p-3">Data</th>
          <th class="text-left p-3">Ações</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($candidaturas as $c): ?>
          <tr class="border-b hover:bg-gray-50">
            <td class="p-3"><?= (int)$c['id'] ?></td>
            <td class="p-3"><?= Security::e($c['vaga_titulo'] ?? '') ?></td>
            <td class="p-3"><?= Security::e($c['nome']) ?></td>
            <td class="p-3"><?= Security::e($c['email']) ?></td>
            <td class="p-3"><?= Security::e($c['telefone']) ?></td>
            <?php $bg = Candidatura::statusBg($c['status']); $tc = Candidatura::statusTextColor($c['status']); ?>
            <td class="p-3"><span class="px-2 py-1 rounded" style="background-color: <?= $bg ?>; color: <?= $tc ?>;"><?= Candidatura::statusLabel($c['status']) ?></span></td>
            <td class="p-3"><?= Security::e($c['created_at']) ?></td>
            <td class="p-3 space-x-2">
              <a href="<?= $base ?>/admin/candidaturas/<?= (int)$c['id'] ?>" class="text-ctpblue hover:text-ctgreen">Detalhes</a>
              <a href="<?= $base ?>/admin/candidaturas/<?= (int)$c['id'] ?>/download" class="text-ctgreen hover:text-ctdark">Baixar PDF</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>