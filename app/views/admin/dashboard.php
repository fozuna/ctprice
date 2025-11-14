<?php
use App\Models\Candidatura;
?>
<div class="grid md:grid-cols-2 gap-4">
  <div class="bg-white shadow rounded p-6">
    <h3 class="text-sm text-gray-500">Vagas ativas</h3>
    <p class="mt-2 text-3xl font-semibold text-ctpblue"><?= (int)$vagasAtivas ?></p>
  </div>
  <div class="bg-white shadow rounded p-6">
    <h3 class="text-sm text-gray-500">Total de candidaturas</h3>
    <p class="mt-2 text-3xl font-semibold text-ctgreen"><?= (int)$totalCandidaturas ?></p>
  </div>
</div>
<div class="mt-6">
  <a href="<?= $base ?>/admin/vagas" class="inline-block bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark">Gerenciar vagas</a>
  <a href="<?= $base ?>/admin/candidaturas" class="inline-block ml-2 bg-ctpblue text-white px-4 py-2 rounded hover:bg-ctdark">Ver candidaturas</a>
</div>

<!-- Kanban de Candidaturas -->
<div class="mt-8">
  <h3 class="text-lg font-semibold text-ctpblue">Acompanhamento de candidaturas</h3>
  <div class="mt-4 grid md:grid-cols-5 gap-4">
    <?php foreach ($statuses as $st): ?>
      <div class="bg-white shadow rounded">
        <?php $bg = Candidatura::statusBg($st); $tc = Candidatura::statusTextColor($st); ?>
        <div class="px-4 py-3 rounded-t" style="background-color: <?= $bg ?>; color: <?= $tc ?>;">
          <span class="text-sm font-medium"><?= Candidatura::statusLabel($st) ?></span>
          <span class="ml-2 text-xs opacity-80">(<?= count($kanban[$st] ?? []) ?>)</span>
        </div>
        <div class="p-3 space-y-3" style="max-height: 420px; overflow-y: auto;">
          <?php foreach ($kanban[$st] ?? [] as $c): ?>
            <div class="border rounded p-3">
              <div class="text-sm font-medium text-ctpblue">#<?= (int)$c['id'] ?> • <?= htmlspecialchars($c['nome']) ?></div>
              <div class="text-xs text-gray-600">Vaga: <?= htmlspecialchars($c['vaga_titulo'] ?? '') ?></div>
              <div class="mt-1 text-xs text-gray-500">Email: <?= htmlspecialchars($c['email']) ?> • Tel: <?= htmlspecialchars($c['telefone']) ?></div>
              <?php if (!empty($c['observacoes'])): ?>
                <div class="mt-2 text-xs bg-gray-50 border rounded p-2"><?= nl2br(htmlspecialchars($c['observacoes'])) ?></div>
              <?php endif; ?>
              <div class="mt-3">
                <a href="<?= $base ?>/admin/candidaturas/<?= (int)$c['id'] ?>" class="text-ctpblue hover:text-ctgreen text-sm">Abrir</a>
              </div>
            </div>
          <?php endforeach; ?>
          <?php if (empty($kanban[$st])): ?>
            <div class="text-xs text-gray-400">Nenhuma candidatura</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>