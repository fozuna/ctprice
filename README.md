# CT Price - Website Institucional Moderno

Este projeto é uma reconstrução moderna da página inicial da CT Price, focada em arquitetura escalável, design premium e performance.

## 🚀 Como Rodar Localmente

### Pré-requisitos
- PHP 8.2+
- Composer (opcional, mas recomendado)
- Servidor Web (Apache/Nginx) ou PHP Built-in Server
- MariaDB/MySQL (para funcionalidades dinâmicas)

### Instalação

1.  **Clone o repositório** para `htdocs/ctprice` (se estiver usando XAMPP).
2.  **Configure o ambiente**:
    Copie o arquivo `.env.example` para `.env` e ajuste as credenciais do banco de dados.
    ```bash
    cp .env.example .env
    ```
3.  **Instale as dependências** (opcional, o projeto inclui um autoloader fallback):
    ```bash
    composer install
    ```
4.  **Banco de Dados**:
    Importe o arquivo `database.sql` no seu banco de dados MariaDB/MySQL.
    ```sql
    source database.sql
    ```
5.  **Acesse**:
    Abra `http://localhost/ctprice` no seu navegador.

    *Nota: Se não estiver usando Apache com mod_rewrite, configure seu servidor para apontar para a pasta `public` ou use o servidor embutido do PHP:*
    ```bash
    php -S localhost:8000 -t public
    ```

## 🏗️ Arquitetura

O projeto segue uma arquitetura **MVC (Model-View-Controller)** estrita, sem frameworks pesados, garantindo performance máxima e controle total.

-   **/app/Core**: Núcleo do framework (Router, Database Singleton, Controller Base, View Engine).
-   **/app/Controllers**: Lógica de negócios e orquestração de dados.
-   **/app/Models**: Camada de acesso a dados (DAO/Active Record simplificado).
-   **/app/Views**: Camada de apresentação, separada em layouts e partials.
-   **/public**: Ponto de entrada único (Entrypoint), aumentando a segurança.
-   **/config**: Configurações gerais.

### Decisões Técnicas

1.  **PHP 8.2+ Puro**: Evitamos frameworks full-stack (Laravel/Symfony) para manter o projeto leve e focado apenas na necessidade de uma Landing Page institucional, mas com estrutura pronta para escalar se necessário.
2.  **Tailwind CSS (CDN/JIT)**: Escolhido pela flexibilidade e capacidade de criar designs customizados "pixel-perfect" sem o peso do Bootstrap.
3.  **Singleton Database**: Garante uma única conexão com o banco por requisição, otimizando recursos.
4.  **Fallback de Dados**: O sistema funciona mesmo se o banco de dados cair ou não estiver configurado, exibindo conteúdo estático de emergência ("Graceful Degradation").
5.  **PSR-4 Autoloading**: Padrão da indústria para carregamento de classes.

### Decisões de Design

O design foi concebido para transmitir **Solidez, Modernidade e Premium**.

-   **Paleta de Cores**: Azul Marinho Profundo (Confiança, Institucional) com toques de Dourado/Bronze (Premium, Valor).
-   **Tipografia**: *Outfit* (títulos) para um toque moderno e geométrico, e *Plus Jakarta Sans* (texto) para legibilidade corporativa.
-   **Espaçamento (Whitespace)**: Uso generoso de espaço em branco para criar uma sensação de luxo e organização.
-   **Microinterações**: Hover effects suaves e transições elegantes para uma experiência de usuário polida.
-   **Mobile-First**: Totalmente responsivo, garantindo que a experiência seja perfeita em qualquer dispositivo.

## 📦 Estrutura de Arquivos

```
/app
  /Core (Database, Router, View, Controller)
  /Controllers (HomeController)
  /Models (Service, Banner, News, Testimonial)
  /Views
    /layouts (main.php)
    /partials (header.php, footer.php)
    /home (index.php)
/public
  index.php (Entry Point)
  .htaccess
/config
.env
composer.json
database.sql
README.md
```
