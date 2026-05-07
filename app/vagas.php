<?php
require_once __DIR__ . '/config/config.php';
if (file_exists(__DIR__ . '/config/Database.php')) {
    require_once __DIR__ . '/config/Database.php';
}
if (file_exists(__DIR__ . '/includes/helpers.php')) {
    require_once __DIR__ . '/includes/helpers.php';
}

$db = Database::getInstance()->getConnection();
$q = trim($_GET['q'] ?? '');
$today = date('Y-m-d');

$where = "status = 'ATIVA' AND (valid_until IS NULL OR valid_until >= :today)";
$params = [':today' => $today];
if ($q !== '') {
    $where .= " AND (title LIKE :q OR department LIKE :q OR location LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}

$sql = "SELECT id, title, description, requirements, benefits, location, contract_type, department, valid_until, created_at
        FROM job_vacancies
        WHERE $where
        ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$vacancies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$logoPath = __DIR__ . '/assets/logowhite.png';
$logoVer = file_exists($logoPath) ? filemtime($logoPath) : time();
$logoUrl = (function_exists('asset_url') ? asset_url('assets/logowhite.png') : 'public/assets/logowhite.png') . '?v=' . $logoVer;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Vagas - <?php echo SITE_NAME; ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            'rich-black': '#0d1321',
            'prussian-blue': '#1d2d44',
            'paynes-gray': '#3e5c76',
            'silver-lake-blue': '#748cab',
            'eggshell': '#f0ebd8'
          },
          fontFamily: { 'sans': ['Inter','system-ui','sans-serif'] }
        }
      }
    }
  </script>
  <style>
    a:focus-visible, button:focus-visible, input:focus-visible { outline: 3px solid #3e5c76; outline-offset: 2px; border-radius: .375rem; }
  </style>
</head>
<body class="min-h-screen bg-gray-50 font-sans">
  <header class="bg-prussian-blue text-eggshell">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex items-center justify-between h-16">
        <a href="vagas.php" class="flex items-center space-x-2">
          <img src="<?php echo $logoUrl; ?>" alt="Logo Madeplant" class="h-10 w-auto md:h-12 object-contain">
          <span class="font-semibold text-eggshell text-base md:text-lg">Vagas Abertas</span>
        </a>
        <a href="candidate-register.php?talent_pool=1" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 rounded text-white font-medium">
          Cadastrar Currículo (Banco de Talentos)
        </a>
      </div>
    </div>
  </header>

  <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
      <form method="get" class="flex gap-2">
        <input type="text" name="q" value="<?php echo htmlspecialchars($q); ?>"
               placeholder="Buscar por título, departamento ou localização"
               class="flex-1 px-3 py-2 border rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-silver-lake-blue">
        <button class="px-4 py-2 bg-paynes-gray text-white rounded-md hover:bg-prussian-blue">Buscar</button>
      </form>
    </div>

    <?php if (empty($vacancies)): ?>
      <div class="bg-white border rounded-lg p-6 text-center text-gray-600">
        Nenhuma vaga ativa no momento. Você pode enviar seu currículo pelo banco de talentos.
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach ($vacancies as $v): ?>
          <div class="bg-white border rounded-lg shadow-sm p-5 flex flex-col">
            <div class="mb-2 flex items-center justify-between">
              <h2 class="text-xl font-semibold text-gray-900">
                <?php echo htmlspecialchars($v['title']); ?>
              </h2>
              <?php if (!empty($v['contract_type'])): ?>
                <span class="text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded">
                  <?php echo htmlspecialchars($v['contract_type']); ?>
                </span>
              <?php endif; ?>
            </div>
            <div class="text-sm text-gray-600 mb-3">
              <?php if (!empty($v['department'])): ?>
                <span class="mr-3"><i class="fas fa-sitemap mr-1"></i><?php echo htmlspecialchars($v['department']); ?></span>
              <?php endif; ?>
              <?php if (!empty($v['location'])): ?>
                <span><i class="fas fa-map-marker-alt mr-1"></i><?php echo htmlspecialchars($v['location']); ?></span>
              <?php endif; ?>
            </div>
            <?php if (!empty($v['description'])): ?>
              <p class="text-gray-700 mb-3"><?php echo nl2br(htmlspecialchars($v['description'])); ?></p>
            <?php endif; ?>
            <div class="mt-auto pt-4 flex items-center justify-between">
              <div class="text-xs text-gray-500">
                <?php if (!empty($v['valid_until'])): ?>
                  Vigência: <?php echo date('d/m/Y', strtotime($v['valid_until'])); ?>
                <?php else: ?>
                  Vigência: indeterminada
                <?php endif; ?>
              </div>
              <a class="px-4 py-2 bg-silver-lake-blue hover:bg-paynes-gray text-white rounded-md"
                 href="candidate-register.php?vacancy_id=<?php echo (int)$v['id']; ?>">
                 Candidatar-se
              </a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <footer class="py-6 text-center text-xs text-gray-500">
    © <?php echo date('Y'); ?> <?php echo htmlspecialchars(SITE_NAME); ?>
  </footer>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/js/all.min.js"></script>
</body>
</html>

