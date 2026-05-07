-- Guarded executor: este arquivo deve ser aplicado apenas quando SELECT DATABASE() = ctprice

CREATE TABLE IF NOT EXISTS site_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind ENUM('image','file') NOT NULL DEFAULT 'image',
  url VARCHAR(500) NOT NULL,
  alt_text VARCHAR(255) NULL,
  mime_type VARCHAR(120) NULL,
  width INT NULL,
  height INT NULL,
  size_bytes BIGINT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_assets_url (url)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_banners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  subtitle VARCHAR(255) NULL,
  image_asset_id BIGINT UNSIGNED NOT NULL,
  link_url VARCHAR(255) NULL,
  button_text VARCHAR(50) NOT NULL DEFAULT 'Saiba Mais',
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_banners_active_sort (active, sort_order, id),
  KEY idx_site_banners_image (image_asset_id),
  CONSTRAINT fk_site_banners_image_asset FOREIGN KEY (image_asset_id) REFERENCES site_assets(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_services (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  icon_class VARCHAR(100) NULL,
  link_url VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_services_active_sort (active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_testimonials (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_name VARCHAR(255) NOT NULL,
  client_company VARCHAR(255) NULL,
  content TEXT NOT NULL,
  image_asset_id BIGINT UNSIGNED NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_testimonials_active_sort (active, sort_order, id),
  KEY idx_site_testimonials_image (image_asset_id),
  CONSTRAINT fk_site_testimonials_image_asset FOREIGN KEY (image_asset_id) REFERENCES site_assets(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_partners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(255) NOT NULL,
  logo_asset_id BIGINT UNSIGNED NOT NULL,
  link_url VARCHAR(255) NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_site_partners_active_sort (active, sort_order, id),
  KEY idx_site_partners_logo (logo_asset_id),
  CONSTRAINT fk_site_partners_logo_asset FOREIGN KEY (logo_asset_id) REFERENCES site_assets(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_news_posts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL,
  excerpt TEXT NULL,
  content LONGTEXT NOT NULL,
  cover_asset_id BIGINT UNSIGNED NULL,
  published_at TIMESTAMP NULL DEFAULT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  status ENUM('draft','published','archived') NOT NULL DEFAULT 'published',
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_news_posts_slug (slug),
  KEY idx_site_news_posts_published_at (published_at),
  KEY idx_site_news_posts_status (status, active),
  KEY idx_site_news_posts_cover (cover_asset_id),
  CONSTRAINT fk_site_news_posts_cover_asset FOREIGN KEY (cover_asset_id) REFERENCES site_assets(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_news_categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_news_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_news_post_categories (
  post_id BIGINT UNSIGNED NOT NULL,
  category_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, category_id),
  KEY idx_site_news_post_categories_category (category_id),
  CONSTRAINT fk_site_news_post_categories_post FOREIGN KEY (post_id) REFERENCES site_news_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_site_news_post_categories_category FOREIGN KEY (category_id) REFERENCES site_news_categories(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_tags (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_tags_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_news_post_tags (
  post_id BIGINT UNSIGNED NOT NULL,
  tag_id BIGINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (post_id, tag_id),
  KEY idx_site_news_post_tags_tag (tag_id),
  CONSTRAINT fk_site_news_post_tags_post FOREIGN KEY (post_id) REFERENCES site_news_posts(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_site_news_post_tags_tag FOREIGN KEY (tag_id) REFERENCES site_tags(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS site_settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  setting_key VARCHAR(120) NOT NULL,
  setting_value TEXT NULL,
  created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_site_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
