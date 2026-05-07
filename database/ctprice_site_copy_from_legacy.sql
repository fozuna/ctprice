-- Executar somente se as tabelas legadas existirem no mesmo banco ctprice:
-- banners, services, testimonials, partners, news

INSERT IGNORE INTO site_assets (kind, url, created_at, updated_at)
  SELECT 'image', b.image_url, COALESCE(b.created_at, CURRENT_TIMESTAMP), COALESCE(b.updated_at, CURRENT_TIMESTAMP)
  FROM banners b;

INSERT INTO site_banners (title, subtitle, image_asset_id, link_url, button_text, active, sort_order, created_at, updated_at)
  SELECT b.title, b.subtitle,
         (SELECT a.id FROM site_assets a WHERE a.url = b.image_url LIMIT 1),
         b.link_url, COALESCE(b.button_text, 'Saiba Mais'), b.active, b.sort_order, b.created_at, b.updated_at
  FROM banners b
  WHERE (SELECT COUNT(*) FROM site_banners) = 0;

INSERT INTO site_services (title, description, icon_class, link_url, active, sort_order, created_at, updated_at)
  SELECT s.title, s.description, s.icon_class, s.link_url, s.active, s.sort_order, s.created_at, s.updated_at
  FROM services s
  WHERE (SELECT COUNT(*) FROM site_services) = 0;

INSERT IGNORE INTO site_assets (kind, url, created_at, updated_at)
  SELECT 'image', p.logo_url, COALESCE(p.created_at, CURRENT_TIMESTAMP), COALESCE(p.updated_at, CURRENT_TIMESTAMP)
  FROM partners p;

INSERT INTO site_partners (name, logo_asset_id, link_url, active, sort_order, created_at, updated_at)
  SELECT p.name,
         (SELECT a.id FROM site_assets a WHERE a.url = p.logo_url LIMIT 1),
         p.link_url, p.active, p.sort_order, p.created_at, p.updated_at
  FROM partners p
  WHERE (SELECT COUNT(*) FROM site_partners) = 0;

INSERT IGNORE INTO site_assets (kind, url, created_at, updated_at)
  SELECT 'image', t.image_url, COALESCE(t.created_at, CURRENT_TIMESTAMP), COALESCE(t.updated_at, CURRENT_TIMESTAMP)
  FROM testimonials t
  WHERE t.image_url IS NOT NULL AND t.image_url != '';

INSERT INTO site_testimonials (client_name, client_company, content, image_asset_id, active, sort_order, created_at, updated_at)
  SELECT t.client_name, t.client_company, t.content,
         CASE
           WHEN t.image_url IS NULL OR t.image_url = '' THEN NULL
           ELSE (SELECT a.id FROM site_assets a WHERE a.url = t.image_url LIMIT 1)
         END,
         t.active, t.sort_order, t.created_at, t.updated_at
  FROM testimonials t
  WHERE (SELECT COUNT(*) FROM site_testimonials) = 0;

INSERT IGNORE INTO site_assets (kind, url, created_at, updated_at)
  SELECT 'image', n.image_url, COALESCE(n.created_at, CURRENT_TIMESTAMP), COALESCE(n.updated_at, CURRENT_TIMESTAMP)
  FROM news n
  WHERE n.image_url IS NOT NULL AND n.image_url != '';

INSERT INTO site_news_posts (title, slug, excerpt, content, cover_asset_id, published_at, active, status, created_at, updated_at)
  SELECT n.title, n.slug, n.excerpt, n.content,
         CASE
           WHEN n.image_url IS NULL OR n.image_url = '' THEN NULL
           ELSE (SELECT a.id FROM site_assets a WHERE a.url = n.image_url LIMIT 1)
         END,
         n.published_at, n.active,
         CASE WHEN n.active = 1 AND n.published_at IS NOT NULL THEN 'published' ELSE 'draft' END,
         n.created_at, n.updated_at
  FROM news n
  WHERE (SELECT COUNT(*) FROM site_news_posts) = 0;

