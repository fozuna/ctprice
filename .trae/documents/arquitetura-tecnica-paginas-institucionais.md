## 1.Architecture design
```mermaid
graph TD
  A["User Browser"] --> B["PHP App (public_html/index.php)"]
  B --> C["Router"]
  C --> D["Controllers"]
  D --> E["Views (PHP + Tailwind)"]
  D --> F["Models"]
  F --> G["PDO"]
  G --> H["MySQL Database (existente)"]

  subgraph "Presentation"
    A
    E
  end

  subgraph "Application"
    B
    C
    D
    F
  end

  subgraph "Data"
    H
  end
```

## 2.Technology Description
- Frontend: HTML renderizado no servidor (Views PHP) + tailwindcss@3.4 (build CSS) + JavaScript (vanilla)
- Backend: PHP@8.2 (MVC leve) + PDO (MySQL)
- Database: MySQL existente (sem alterações de schema)

## 3.Route definitions
| Route | Purpose |
|---|---|
| / | Home (landing atual) com links atualizados para páginas internas |
| /sobre | Página “A CT Price” (conteúdo institucional reutilizando layout atual) |
| /servicos | Página de listagem completa de serviços (site_services) |
| /clientes | Página de clientes/parceiros (site_partners + diretório de imagens) |
| /depoimentos | Página de depoimentos (site_testimonials) |
| /noticias | Página de notícias (site_news_posts) |
| /partners/load-more | Endpoint existente para rotação de clientes/parceiros (manter como está) |

## 6.Data model(if applicable)
### 6.1 Data model definition
Sem mudanças no banco. As páginas novas devem consumir as tabelas já existentes:
- site_services
- site_testimonials (com imagem via site_assets)
- site_news_posts (com capa via site_assets)
- site_partners (com logo via site_assets)
- site_assets

```mermaid
erDiagram
  "site_assets" ||--o{ "site_testimonials" : "image_asset_id"
  "site_assets" ||--o{ "site_news_posts" : "cover_asset_id"
  "site_assets" ||--o{ "site_partners" : "logo_asset_id"

  "site_services" {
    int id
    boolean active
    int sort_order
    string title
    string description
    string icon_class
    string link_url
  }

  "site_testimonials" {
    int id
    boolean active
    int sort_order
    string client_name
    string client_company
    string content
    int image_asset_id
  }

  "site_news_posts" {
    int id
    boolean active
    string title
    string slug
    string excerpt
    string content
    int cover_asset_id
    datetime published_at
  }

  "site_partners" {
    int id
    boolean active
    int sort_order
    string name
    int logo_asset_id
    string link_url
  }

  "site_assets" {
    int id
    string url
  }
```