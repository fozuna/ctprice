# Sistema de Versionamento Automático

## Visão Geral

O sistema de versionamento automático foi implementado para facilitar o controle de versões do projeto. Ele incrementa automaticamente os números de versão baseado no tipo de alteração realizada.

## Estrutura da Versão

A versão segue o padrão: `v{major}.{minor}.{patch}.{build}`

- **Major**: Mudanças importantes que podem quebrar compatibilidade
- **Minor**: Novas funcionalidades
- **Patch**: Correções de bugs
- **Build**: Pequenas alterações e ajustes

## Arquivos do Sistema

### 1. `config/version.php`
Arquivo principal que gerencia o versionamento. Contém a classe `VersionManager` e funções utilitárias.

### 2. `config/version-hooks.php`
Sistema de hooks que detecta mudanças em arquivos e incrementa automaticamente a versão.

### 3. `scripts/increment-version.php`
Script de linha de comando para incrementar versões manualmente.

## Como Usar

### Incremento Manual via Script

```bash
# Incrementar build (padrão)
php scripts/increment-version.php

# Incrementar patch (correção)
php scripts/increment-version.php patch "Correção de bug no dashboard"

# Incrementar minor (nova funcionalidade)
php scripts/increment-version.php minor "Adicionado sistema de relatórios"

# Incrementar major (mudança importante)
php scripts/increment-version.php major "Nova arquitetura do sistema"

# Ver informações completas
php scripts/increment-version.php build "Alteração teste" --info
```

### Incremento Automático

O sistema detecta automaticamente mudanças nos seguintes arquivos:

- **Arquivos PHP principais** (*.php) → Incrementa build
- **Arquivos de configuração** (config/*.php) → Incrementa patch
- **Arquivos importantes** (dashboard.php, equipment.php, etc.) → Incrementa minor

### Uso Programático

```php
// Incluir o sistema de versionamento
require_once 'config/version.php';

// Obter versão atual
$version = getSystemVersion(); // Ex: "v1.3.360"

// Obter versão completa com build
$fullVersion = getFullSystemVersion(); // Ex: "v1.3.360.15"

// Incrementar versão manualmente
$newVersion = incrementSystemVersion('minor', 'Nova funcionalidade');

// Obter informações completas
$info = getVersionInfo();
```

### Integração com Hooks

```php
// Incluir hooks para incremento automático
require_once 'config/version-hooks.php';

// Verificar e incrementar automaticamente
checkAndIncrementVersion();

// Forçar incremento
$hooks = VersionHooks::getInstance();
$newVersion = $hooks->forceIncrement('patch', 'Correção manual');
```

## Exemplos de Uso

### 1. Ao fazer uma correção de bug:
```bash
php scripts/increment-version.php patch "Corrigido problema de login"
```

### 2. Ao adicionar nova funcionalidade:
```bash
php scripts/increment-version.php minor "Adicionado dashboard de relatórios"
```

### 3. Incremento automático no código:
```php
// No início de arquivos importantes
require_once 'config/version-hooks.php';
checkAndIncrementVersion();
```

## Arquivo de Versão (version.json)

O sistema cria automaticamente um arquivo `config/version.json` com a estrutura:

```json
{
    "major": 1,
    "minor": 3,
    "patch": 360,
    "build": 15,
    "last_updated": "2024-01-15 14:30:25",
    "last_change": "Implementação do sistema de versionamento automático"
}
```

## Integração com Footer

O footer foi atualizado para exibir:
- Versão atual do sistema
- Número do build
- Data da última atualização
- Descrição da última mudança (via tooltip)

## Boas Práticas

1. **Use incremento automático** para mudanças rotineiras
2. **Use scripts manuais** para mudanças específicas com descrições detalhadas
3. **Documente mudanças importantes** nas descrições
4. **Use tipos apropriados**:
   - `build`: Pequenos ajustes, correções de estilo
   - `patch`: Correções de bugs
   - `minor`: Novas funcionalidades
   - `major`: Mudanças arquiteturais importantes

## Comandos Úteis

```bash
# Ver versão atual
php -r "require 'config/version.php'; echo getSystemVersion();"

# Ver informações completas
php -r "require 'config/version.php'; print_r(getVersionInfo());"

# Incrementar com descrição personalizada
php scripts/increment-version.php build "Ajustes no CSS do dashboard"
```

## Troubleshooting

### Problema: Versão não incrementa automaticamente
**Solução**: Verifique se o arquivo `config/version-hooks.php` está sendo incluído nos arquivos principais.

### Problema: Erro de permissão ao salvar version.json
**Solução**: Verifique as permissões da pasta `config/` e certifique-se de que o servidor web pode escrever nela.

### Problema: Versão não aparece no footer
**Solução**: Verifique se o arquivo `config/version.php` está sendo incluído corretamente no `includes/footer.php`.