SHOW TABLES LIKE 'site\_%';

SHOW CREATE TABLE site_news_posts;
SHOW INDEX FROM site_news_posts;
SHOW INDEX FROM site_banners;
SHOW INDEX FROM site_partners;

EXPLAIN SELECT id, slug, published_at
  FROM site_news_posts
 WHERE active = 1
 ORDER BY published_at DESC
 LIMIT 3;

EXPLAIN SELECT id, title
  FROM site_services
 WHERE active = 1
 ORDER BY sort_order ASC, id ASC
 LIMIT 20;

