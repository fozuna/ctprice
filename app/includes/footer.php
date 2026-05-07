</main>
        </div>
    </div>
    
    <!-- Footer -->
    <div class="bg-white border-t border-silver-lake-blue px-6 py-3">
        <div class="flex items-center justify-between text-sm text-paynes-gray">
            <div class="flex items-center space-x-4">
                <?php 
                // Incluir o sistema de versionamento
                require_once __DIR__ . '/../config/version.php';
                $versionInfo = getVersionInfo();
                ?>
                <span><?php echo SITE_NAME; ?> <?php echo getSystemVersion(); ?></span>
                <span class="text-xs bg-gray-100 px-2 py-1 rounded" title="Última atualização: <?php echo $versionInfo['last_updated']; ?>">
                    Build <?php echo $versionInfo['build']; ?>
                </span>
            </div>
            <div class="flex items-center space-x-4">
                <span>Desenvolvido para controle de Produção e Entregas</span>
                <span class="text-xs text-gray-500" title="<?php echo $versionInfo['last_change']; ?>">
                    📅 <?php echo date('d/m/Y', strtotime($versionInfo['last_updated'])); ?>
                </span>
            </div>
        </div>
    </div>
    
    <!-- Scripts Globais -->
    <script>
        // Configurações globais
        window.BDO = {
            baseUrl: '<?php echo function_exists('base_url') ? base_url() : (defined('BASE_URL') ? BASE_URL : ''); ?>',
            userId: <?php echo $_SESSION['user_id'] ?? 'null'; ?>,
            userRole: '<?php echo $_SESSION['user_role'] ?? 'null'; ?>'
        };
        
        // Função para mostrar notificações
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' :
                type === 'error' ? 'bg-red-500 text-white' :
                type === 'warning' ? 'bg-yellow-500 text-black' :
                'bg-blue-500 text-white'
            }`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <span>${message}</span>
                    <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-lg">&times;</button>
                </div>
            `;
            document.body.appendChild(notification);
            
            // Auto remove após 5 segundos
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 5000);
        }
        
        // Função para confirmar ações
        function confirmAction(message, callback) {
            if (confirm(message)) {
                callback();
            }
        }
        
        // Função para formatar data/hora
        function formatDateTime(dateString) {
            const date = new Date(dateString);
            return date.toLocaleString('pt-BR');
        }
        
        // Função para formatar duração em horas
        function formatDuration(minutes) {
            const hours = Math.floor(minutes / 60);
            const mins = minutes % 60;
            return `${hours}h${mins.toString().padStart(2, '0')}m`;
        }
        
        // Função para validar formulários
        function validateForm(formId) {
            const form = document.getElementById(formId);
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('border-red-500');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500');
                }
            });
            
            return isValid;
        }
        
        // Função para fazer requisições AJAX
        async function makeRequest(url, options = {}) {
            try {
                const response = await fetch(url, {
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    ...options
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                return await response.json();
            } catch (error) {
                console.error('Request failed:', error);
                showNotification('Erro na comunicação com o servidor', 'error');
                throw error;
            }
        }
        
        // Auto-refresh para páginas de monitoramento
        if (window.location.pathname.includes('dashboard') || window.location.pathname.includes('occurrences')) {
            setInterval(() => {
                // Verificar se há atualizações necessárias
                const lastUpdate = localStorage.getItem('lastUpdate');
                const now = Date.now();
                
                if (!lastUpdate || (now - parseInt(lastUpdate)) > 300000) { // 5 minutos
                    // Recarregar dados se necessário
                    if (typeof refreshData === 'function') {
                        refreshData();
                    }
                    localStorage.setItem('lastUpdate', now.toString());
                }
            }, 60000); // Verificar a cada minuto
        }
    </script>
    
    <!-- Removido Bootstrap JS (usamos Tailwind/Alpine.js) -->
    
    <!-- Scripts específicos da página -->
    <?php if (isset($pageScripts)): ?>
        <?php echo $pageScripts; ?>
    <?php endif; ?>
</body>
</html>
