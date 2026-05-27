## Superficies Claras Nas Paginas Internas

Data: 2026-05-19

### Objetivo

Substituir fundos escuros das paginas internas por superficies claras baseadas em `#fefefe`, mantendo contraste, legibilidade e coerencia com a identidade verde da CT Price.

### Auditoria Realizada

Arquivos auditados com ocorrencias de fundos escuros ou textos dependentes de fundo escuro:

- `app/Views/layouts/main.php`
- `app/Views/pages/sobre.php`
- `app/Views/pages/servicos.php`
- `app/Views/pages/clientes.php`
- `app/Views/pages/parceiros.php`
- `app/Views/pages/depoimentos.php`
- `app/Views/pages/noticias.php`
- `app/Views/pages/fale_conosco.php`

Padroes identificados durante a auditoria:

- Heros internos com `bg-primary-dark`
- Secoes de conteudo com `bg-[#050911]`
- Blocos CTA com `bg-primary-dark`
- Textos e links configurados em branco para fundos escuros

### Alteracoes Implementadas

#### Estilos Globais

Arquivo:

- `app/Views/layouts/main.php`

Classes adicionadas ou ajustadas:

- `.ct-hero.ct-hero--internal`
- `.ct-hero--internal .ct-hero-eyebrow`
- `.ct-hero--internal .ct-hero-title`
- `.ct-hero--internal .ct-hero-copy`
- `.ct-hero--internal .ct-hero-accent`
- `.ct-hero--internal .ct-hero-secondary-link`
- `.ct-section-surface`
- `.ct-surface-pattern`
- `.ct-surface-overlay-y`
- `.ct-surface-overlay-x`
- `.ct-surface-glow`
- `.ct-cta-surface`
- `.ct-cta-title`
- `.ct-cta-copy`
- `.ct-cta-email`

### Paginas Internas Afetadas

- `/sobre`
- `/servicos`
- `/clientes`
- `/parceiros`
- `/depoimentos`
- `/noticias`
- `/fale-conosco`

### Resultado Visual

- Heros internos migrados para base clara `#fefefe`
- Secao principal de servicos migrada de fundo escuro para superficie clara
- CTAs internos convertidos para superficies claras com hierarquia tipografica escura
- Botoes secundarios adaptados para contraste adequado sobre fundo branco

### Validacao Tecnica

- Diagnosticos sem erros nos arquivos alterados
- Estrutura responsiva preservada, sem alteracao de breakpoints
- Hierarquia visual mantida com contraste escuro sobre fundo claro

### Observacao

O painel principal escuro do formulario em `/fale-conosco` foi preservado por se tratar de um bloco compositivo isolado, nao do fundo estrutural da pagina.
