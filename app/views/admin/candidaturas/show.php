<?php
use App\Core\Security;
use App\Models\Candidatura;
$statusOptions = array_keys(Candidatura::statusMap());
?>
<div class="bg-white shadow rounded p-6">
  <h2 class="text-2xl font-semibold text-ctpblue"><?= Security::e($c['nome']) ?></h2>
  <?php if (!empty($c['cpf'])): ?>
    <p class="text-lg text-gray-600 font-medium">CPF: <?= substr($c['cpf'], 0, 3) . '.' . substr($c['cpf'], 3, 3) . '.' . substr($c['cpf'], 6, 3) . '-' . substr($c['cpf'], 9, 2) ?></p>
  <?php endif; ?>
  <p class="mt-2"><strong>Cargo pretendido:</strong> <?= Security::e($c['cargo_pretendido'] ?? $c['vaga_titulo'] ?? '') ?></p>
  <p><strong>E-mail:</strong> <?= Security::e($c['email']) ?></p>
  <p><strong>Telefone:</strong> <?= Security::e($c['telefone']) ?></p>
  <p class="mt-2"><strong>Experiência:</strong></p>
  <div class="mt-1 p-3 bg-gray-50 border rounded"><?= nl2br(Security::e($c['experiencia'])) ?></div>

  <form class="mt-6 space-y-4" action="<?= $base ?>/admin/candidaturas/<?= (int)$c['id'] ?>/atualizar" method="post">
    <input type="hidden" name="csrf" value="<?= Security::e($csrf ?? '') ?>">
    <div>
      <label class="block text-sm">Status</label>
      <select name="status" class="mt-1 w-full border rounded px-3 py-2">
        <?php foreach ($statusOptions as $st): ?>
          <option value="<?= $st ?>" <?= ($c['status'] ?? '') === $st ? 'selected' : '' ?>><?= Candidatura::statusLabel($st) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-sm">Observações</label>
      <textarea name="observacoes" rows="4" class="mt-1 w-full border rounded px-3 py-2" placeholder="Suas considerações para este estágio"><?= Security::e($c['observacoes'] ?? '') ?></textarea>
    </div>
    <div>
      <button type="submit" class="bg-ctgreen text-white px-4 py-2 rounded hover:bg-ctdark">Salvar e avançar</button>
      <a href="<?= $base ?>/admin/candidaturas" class="ml-2 text-ctpblue hover:text-ctgreen">Voltar</a>
      <a href="<?= $base ?>/admin/candidaturas/<?= (int)$c['id'] ?>/download" class="ml-2 text-ctgreen hover:text-ctdark">Baixar currículo (PDF)</a>
    </div>
  </form>

  <!-- Histórico de Observações -->
  <?php if (!empty($historico)): ?>
  <div class="mt-8 bg-white shadow rounded p-6">
    <h3 class="text-lg font-semibold text-ctpblue mb-4">Histórico de Observações</h3>
    <div class="space-y-4">
      <?php foreach ($historico as $h): ?>
      <div class="border-l-4 border-ctgreen pl-4 py-2 bg-gray-50">
        <div class="flex justify-between items-start">
          <div>
            <?php if ($h['status_anterior'] && $h['status_anterior'] !== $h['status_novo']): ?>
              <p class="text-sm text-gray-600">
                <strong>Status alterado:</strong> 
                <span class="text-red-600"><?= Candidatura::statusLabel($h['status_anterior']) ?></span> 
                → 
                <span class="text-green-600"><?= Candidatura::statusLabel($h['status_novo']) ?></span>
              </p>
            <?php else: ?>
              <p class="text-sm text-gray-600">
                <strong>Status:</strong> <?= Candidatura::statusLabel($h['status_novo']) ?>
              </p>
            <?php endif; ?>
            <?php if (!empty($h['observacoes'])): ?>
              <div class="mt-2 text-gray-800">
                <?= nl2br(Security::e($h['observacoes'])) ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="text-right text-xs text-gray-500">
            <?php if (!empty($h['usuario_nome'])): ?>
              <p>Por: <?= Security::e($h['usuario_nome']) ?></p>
            <?php endif; ?>
            <p><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></p>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>