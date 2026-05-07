<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/classes/Auth.php';
$db = Database::getInstance()->getConnection();
$auth = new Auth($db);
if (!$auth->isLoggedIn()) { header('Location: login.php'); exit; }
$pageTitle = 'Solicitação de Vaga';
include __DIR__ . '/includes/header.php';
?>
<main class="p-6 max-w-6xl mx-auto">
  <h1 class="text-2xl md:text-3xl font-bold text-rich-black text-center mb-4">Solicitação de Vaga</h1>
  <div id="alertBox" class="hidden mb-4 p-3 rounded border"></div>
  <form id="formSolicitacao" class="space-y-6">
    <section class="bg-white border border-silver-lake-blue rounded-lg p-4">
      <h2 class="text-lg font-semibold text-rich-black mb-3">Identificação da Vaga</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="form-label">Área/Departamento *</label><input type="text" name="area_departamento" class="form-input w-full" required></div>
        <div><label class="form-label">Quantidade de Vagas *</label><input type="number" min="1" name="quantidade_vagas" class="form-input w-full" required></div>
        <div><label class="form-label">Cargo *</label><input type="text" name="cargo" class="form-input w-full" required></div>
        <div><label class="form-label">Máquina florestal (opcional)</label><input type="text" name="maquina_florestal" class="form-input w-full"></div>
        <div><label class="form-label">Gestor solicitante *</label><input type="text" name="gestor_solicitante" class="form-input w-full" required></div>
      </div>
      <div class="mt-4">
        <label class="form-label">Tipo de vaga *</label>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_vaga" value="nova_posicao" class="form-radio" required> Nova posição</label>
          <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_vaga" value="substituicao" class="form-radio" required> Substituição</label>
          <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_vaga" value="aumento_quadro" class="form-radio" required> Aumento de quadro</label>
          <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_vaga" value="projeto_temporario" class="form-radio" required> Projeto temporário</label>
        </div>
      </div>
      <div id="substituicaoBlock" class="mt-4 hidden">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div><label class="form-label">Nome do substituído *</label><input type="text" name="nome_substituido" class="form-input w-full"></div>
          <div><label class="form-label">Data do desligamento *</label><input type="date" name="data_desligamento" class="form-input w-full"></div>
          <div>
            <label class="form-label">Motivo da saída *</label>
            <div class="grid grid-cols-2 gap-2">
              <label class="inline-flex items-center gap-2"><input type="radio" name="motivo_saida" value="desligamento" class="form-radio"> Desligamento</label>
              <label class="inline-flex items-center gap-2"><input type="radio" name="motivo_saida" value="promocao" class="form-radio"> Promoção</label>
              <label class="inline-flex items-center gap-2"><input type="radio" name="motivo_saida" value="transferencia" class="form-radio"> Transferência</label>
              <label class="inline-flex items-center gap-2"><input type="radio" name="motivo_saida" value="outros" class="form-radio"> Outros</label>
            </div>
          </div>
          <div class="md:col-span-3"><label class="form-label">Motivo (outros)</label><input type="text" name="motivo_outros" class="form-input w-full"></div>
        </div>
      </div>
    </section>

    <section class="bg-white border border-silver-lake-blue rounded-lg p-4">
      <h2 class="text-lg font-semibold text-rich-black mb-3">Informações Contratuais</h2>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="md:col-span-2">
          <label class="form-label">Tipo de contratação *</label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_contratacao" value="clt" class="form-radio" required> CLT</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_contratacao" value="temporario" class="form-radio" required> Temporário</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_contratacao" value="terceiro" class="form-radio" required> Terceiro</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="tipo_contratacao" value="pj" class="form-radio" required> PJ</label>
          </div>
        </div>
        <div><label class="form-label">Salário previsto</label><input type="number" step="0.01" name="salario_previsto" class="form-input w-full"></div>
        <div><label class="form-label">Centro de custo *</label><input type="text" name="centro_custo" class="form-input w-full" required></div>
      </div>
      <div class="mt-4">
        <label class="form-label">Está previsto no orçamento? *</label>
        <div class="grid grid-cols-2 gap-3">
          <label class="inline-flex items-center gap-2"><input type="radio" name="previsto_orcamento" value="sim" class="form-radio" required> Sim</label>
          <label class="inline-flex items-center gap-2"><input type="radio" name="previsto_orcamento" value="nao" class="form-radio" required> Não</label>
        </div>
      </div>
      <div id="orcamentoBlock" class="mt-4 hidden"><label class="form-label">Justificativa (não previsto)</label><textarea name="justificativa_nao_previsto" rows="3" class="form-textarea w-full"></textarea></div>
      <div class="mt-4"><label class="form-label">Benefícios</label><textarea name="beneficios" rows="3" class="form-textarea w-full"></textarea></div>
    </section>

    <section class="bg-white border border-silver-lake-blue rounded-lg p-4">
      <h2 class="text-lg font-semibold text-rich-black mb-3">Jornada e Escala</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div><label class="form-label">Jornada de trabalho</label><input type="text" name="jornada_trabalho" class="form-input w-full"></div>
        <div><label class="form-label">Escala</label><input type="text" name="escala" class="form-input w-full"></div>
        <div>
          <label class="form-label">Turno *</label>
          <div class="grid grid-cols-3 gap-2">
            <label class="inline-flex items-center gap-2"><input type="radio" name="turno" value="diurno" class="form-radio" required> Diurno</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="turno" value="noturno" class="form-radio" required> Noturno</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="turno" value="misto" class="form-radio" required> Misto</label>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white border border-silver-lake-blue rounded-lg p-4">
      <h2 class="text-lg font-semibold text-rich-black mb-3">Perfil da Vaga</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="form-label">Escolaridade mínima</label><input type="text" name="escolaridade_minima" class="form-input w-full"></div>
        <div><label class="form-label">Formação acadêmica</label><input type="text" name="formacao_academica" class="form-input w-full"></div>
        <div class="md:col-span-2"><label class="form-label">Experiência</label><textarea name="experiencia" rows="3" class="form-textarea w-full"></textarea></div>
        <div class="md:col-span-2"><label class="form-label">Entregas esperadas</label><textarea name="entregas_esperadas" rows="3" class="form-textarea w-full"></textarea></div>
        <div class="md:col-span-2"><label class="form-label">Competências técnicas</label><textarea name="competencias_tecnicas" rows="3" class="form-textarea w-full"></textarea></div>
        <div class="md:col-span-2"><label class="form-label">Competências comportamentais</label><textarea name="competencias_comportamentais" rows="3" class="form-textarea w-full"></textarea></div>
        <div>
          <label class="form-label">Nível de responsabilidade *</label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <label class="inline-flex items-center gap-2"><input type="radio" name="nivel_responsabilidade" value="operacional" class="form-radio" required> Operacional</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="nivel_responsabilidade" value="tecnico" class="form-radio" required> Técnico</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="nivel_responsabilidade" value="analitico" class="form-radio" required> Analítico</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="nivel_responsabilidade" value="estrategico" class="form-radio" required> Estratégico</label>
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white border border-silver-lake-blue rounded-lg p-4">
      <h2 class="text-lg font-semibold text-rich-black mb-3">Prazos</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div><label class="form-label">Data de início *</label><input type="date" name="data_inicio" class="form-input w-full" required></div>
        <div>
          <label class="form-label">Urgência *</label>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
            <label class="inline-flex items-center gap-2"><input type="radio" name="urgencia" value="baixa" class="form-radio" required> Baixa</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="urgencia" value="media" class="form-radio" required> Média</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="urgencia" value="alta" class="form-radio" required> Alta</label>
            <label class="inline-flex items-center gap-2"><input type="radio" name="urgencia" value="critica" class="form-radio" required> Crítica</label>
          </div>
        </div>
        <div><label class="form-label">Data limite</label><input type="date" name="data_limite" class="form-input w-full"></div>
      </div>
    </section>

    <section class="bg-white border border-silver-lake-blue rounded-lg p-4">
      <h2 class="text-lg font-semibold text-rich-black mb-3">Aprovações</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div><label class="form-label">Líder imediato *</label><input type="text" name="lider_imediato" class="form-input w-full" required></div>
        <div><label class="form-label">RH responsável *</label><input type="text" name="rh_responsavel" class="form-input w-full" required></div>
      </div>
    </section>

    <div class="flex justify-end">
      <button type="submit" class="px-5 py-2 rounded bg-blue-600 hover:bg-blue-700 text-white font-semibold">Salvar solicitação</button>
    </div>
  </form>
</main>
<script>
(function(){
  const tipoRadios = document.querySelectorAll('input[name="tipo_vaga"]');
  const subBlock = document.getElementById('substituicaoBlock');
  const orcRadios = document.querySelectorAll('input[name="previsto_orcamento"]');
  const orcBlock = document.getElementById('orcamentoBlock');
  function syncSub(){ const v = document.querySelector('input[name="tipo_vaga"]:checked'); subBlock.style.display = (v && v.value==='substituicao')?'block':'none'; }
  function syncOrc(){ const v = document.querySelector('input[name="previsto_orcamento"]:checked'); orcBlock && (orcBlock.style.display = (v && v.value==='nao')?'block':'none'); }
  tipoRadios.forEach(r=>r.addEventListener('change', syncSub));
  orcRadios.forEach(r=>r.addEventListener('change', syncOrc));
  syncSub(); syncOrc();
  function alertBox(msg, ok){
    const el = document.getElementById('alertBox');
    el.classList.remove('hidden'); el.textContent = msg;
    el.className = 'mb-4 p-3 rounded border ' + (ok?'bg-green-50 border-green-300 text-green-800':'bg-red-50 border-red-300 text-red-800');
  }
  document.getElementById('formSolicitacao').addEventListener('submit', async function(e){
    e.preventDefault();
    const form = new FormData(this);
    try {
      const resp = await fetch('actions/salvar_solicitacao.php', { method:'POST', body: form });
      const json = await resp.json();
      if (json.success) { alertBox(json.message, true); this.reset(); syncSub(); syncOrc(); }
      else { alertBox(json.message||'Erro ao salvar.', false); }
    } catch (err) { alertBox('Falha de comunicação com o servidor.', false); }
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
