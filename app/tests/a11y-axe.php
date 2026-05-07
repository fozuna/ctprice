<?php
require_once __DIR__ . '/../config/config.php';
$target = $_GET['url'] ?? 'reports.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Teste de Acessibilidade WCAG 2.1</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/axe-core/4.7.2/axe.min.js" integrity="sha512-JF8T1U3mFQZxSvt0gLVr1sYf2Q+mtbCq2ZQoyP2W0YgpTg0xP0B2+Qm4PS3Qn1H7j+ZyE7xJkF2MyQxZ1Zr5Cw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50">
  <div class="max-w-4xl mx-auto p-4">
    <h1 class="text-2xl font-semibold mb-4">Verificador WCAG 2.1 com axe-core</h1>
    <form class="mb-4">
      <label class="block mb-2 text-base">Página alvo (mesmo domínio)</label>
      <input type="text" name="url" value="<?php echo htmlspecialchars($target); ?>" class="w-full border rounded px-3 py-2 text-base" placeholder="ex: reports.php">
      <button class="mt-3 bg-paynes-gray text-white px-4 py-2 rounded min-h-[44px]">Carregar</button>
    </form>
    <div class="border rounded bg-white overflow-hidden">
      <iframe id="targetFrame" title="Alvo" src="../<?php echo htmlspecialchars($target); ?>" class="w-full h-[70vh] border-0"></iframe>
    </div>
    <pre id="results" class="mt-4 p-3 text-sm bg-white border rounded overflow-auto max-h-80"></pre>
  </div>
  <script>
    const frame = document.getElementById('targetFrame');
    const resultsEl = document.getElementById('results');
    frame.addEventListener('load', async () => {
      try {
        const context = frame.contentDocument;
        const { violations } = await axe.run(context, {
          runOnly: { type: 'tag', values: ['wcag2a','wcag2aa'] }
        });
        resultsEl.textContent = JSON.stringify(violations, null, 2);
      } catch (e) {
        resultsEl.textContent = 'Erro ao executar axe: ' + e.message + '\nGaranta que a página está no mesmo domínio.';
      }
    });
  </script>
</body>
</html>
