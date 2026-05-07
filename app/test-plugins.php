<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste dos Plugins Tailwind CSS</title>
<link rel="stylesheet" href="/assets/css/output.css">
</head>
<body class="bg-gray-50">
    <div class="min-h-screen py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-gray-900">Teste dos Plugins Tailwind CSS</h1>
                <p class="mt-2 text-gray-600">Demonstração dos plugins Typography, Forms e Aspect Ratio</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Typography Plugin -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900">Typography Plugin</h2>
                    </div>
                    <div class="card-body">
                        <div class="content-area">
                            <h1>Título Principal</h1>
                            <p>Este é um parágrafo de exemplo usando o plugin <strong>@tailwindcss/typography</strong>. O plugin adiciona estilos tipográficos bonitos e consistentes para conteúdo de texto.</p>
                            
                            <h2>Subtítulo</h2>
                            <p>Você pode usar listas, links e outros elementos HTML com estilos automáticos:</p>
                            
                            <ul>
                                <li>Item da lista 1</li>
                                <li>Item da lista 2</li>
                                <li>Item da lista 3</li>
                            </ul>
                            
                            <blockquote>
                                <p>Esta é uma citação de exemplo que demonstra como o plugin Typography estiliza automaticamente elementos de citação.</p>
                            </blockquote>
                            
                            <p>Também suporta <a href="#">links</a> e <code>código inline</code> com estilos apropriados.</p>
                        </div>
                    </div>
                </div>

                <!-- Forms Plugin -->
                <div class="card">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900">Forms Plugin</h2>
                    </div>
                    <div class="card-body">
                        <form class="space-y-4">
                            <div>
                                <label class="form-label">Input Personalizado</label>
                                <input type="text" class="custom-input" placeholder="Digite algo aqui...">
                            </div>
                            
                            <div>
                                <label class="form-label">Select Personalizado</label>
                                <select class="custom-select">
                                    <option>Opção 1</option>
                                    <option>Opção 2</option>
                                    <option>Opção 3</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="form-label">Textarea</label>
                                <textarea class="form-textarea" rows="3" placeholder="Digite uma mensagem..."></textarea>
                            </div>
                            
                            <div class="flex items-center">
                                <input type="checkbox" class="form-checkbox h-4 w-4 text-blue-600">
                                <label class="ml-2 text-sm text-gray-700">Aceito os termos e condições</label>
                            </div>
                            
                            <div class="flex space-x-4">
                                <div class="flex items-center">
                                    <input type="radio" class="form-radio h-4 w-4 text-blue-600" name="radio-example" value="1">
                                    <label class="ml-2 text-sm text-gray-700">Opção A</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" class="form-radio h-4 w-4 text-blue-600" name="radio-example" value="2">
                                    <label class="ml-2 text-sm text-gray-700">Opção B</label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn-primary">
                                Enviar Formulário
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Aspect Ratio (Native) -->
                <div class="card lg:col-span-2">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900">Aspect Ratio (Nativo do Tailwind v3.4+)</h2>
                    </div>
                    <div class="card-body">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Video Container -->
                            <div>
                                <h3 class="text-lg font-medium mb-3">Proporção de Vídeo (16:9)</h3>
                                <div class="video-container bg-gradient-to-br from-blue-400 to-purple-500 rounded-lg flex items-center justify-center">
                                    <div class="text-white text-center">
                                        <svg class="w-16 h-16 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"></path>
                                        </svg>
                                        <p class="text-sm">Vídeo 16:9</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Square Container -->
                            <div>
                                <h3 class="text-lg font-medium mb-3">Proporção Quadrada (1:1)</h3>
                                <div class="square-container bg-gradient-to-br from-green-400 to-blue-500 rounded-lg flex items-center justify-center">
                                    <div class="text-white text-center">
                                        <svg class="w-16 h-16 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm12 12H4l4-8 3 6 2-4 3 6z" clip-rule="evenodd"></path>
                                        </svg>
                                        <p class="text-sm">Quadrado 1:1</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Custom Aspect Ratio -->
                            <div>
                                <h3 class="text-lg font-medium mb-3">Proporção 4:3</h3>
                                <div class="relative w-full aspect-[4/3] bg-gradient-to-br from-purple-400 to-pink-500 rounded-lg flex items-center justify-center">
                                    <div class="text-white text-center">
                                        <svg class="w-16 h-16 mx-auto mb-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 011 1v2a1 1 0 01-1 1H4a1 1 0 01-1-1V4zM3 10a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H4a1 1 0 01-1-1v-6zM14 9a1 1 0 00-1 1v6a1 1 0 001 1h2a1 1 0 001-1v-6a1 1 0 00-1-1h-2z" clip-rule="evenodd"></path>
                                        </svg>
                                        <p class="text-sm">Layout 4:3</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="card lg:col-span-2">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold text-gray-900">Componentes Personalizados</h2>
                    </div>
                    <div class="card-body">
                        <div class="space-y-6">
                            <!-- Badges -->
                            <div>
                                <h3 class="text-lg font-medium mb-3">Status Badges</h3>
                                <div class="flex flex-wrap gap-2">
                                    <span class="badge badge-success">Sucesso</span>
                                    <span class="badge badge-warning">Aviso</span>
                                    <span class="badge badge-danger">Erro</span>
                                    <span class="badge badge-info">Informação</span>
                                </div>
                            </div>
                            
                            <!-- Buttons -->
                            <div>
                                <h3 class="text-lg font-medium mb-3">Botões Personalizados</h3>
                                <div class="flex flex-wrap gap-3">
                                    <button class="btn-primary">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                                        </svg>
                                        Primário
                                    </button>
                                    <button class="btn-secondary">Secundário</button>
                                    <button class="btn-success">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        Sucesso
                                    </button>
                                    <button class="btn-danger">
                                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z" clip-rule="evenodd"></path>
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                        </svg>
                                        Perigo
                                    </button>
                                </div>
                            </div>

                            <!-- Plugin Status -->
                            <div>
                                <h3 class="text-lg font-medium mb-3">Status dos Plugins</h3>
                                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-green-600 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <span class="text-green-800 font-medium">Plugins instalados e funcionando!</span>
                                    </div>
                                    <ul class="mt-2 text-sm text-green-700 ml-7">
                                        <li>✓ @tailwindcss/typography - Estilos tipográficos automáticos</li>
                                        <li>✓ @tailwindcss/forms - Estilos de formulário melhorados</li>
                                        <li>✓ aspect-ratio - Suporte nativo do Tailwind v3.4+</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>