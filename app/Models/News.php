<?php

namespace App\Models;

use App\Core\Model;

class News extends Model
{
    public function getAllPublished()
    {
        return $this->fetchAll(
            "SELECT n.id, n.title, n.slug, n.excerpt, n.content, a.url AS image_url, n.published_at, n.active, n.created_at, n.updated_at
               FROM site_news_posts n
          LEFT JOIN site_assets a ON a.id = n.cover_asset_id
              WHERE n.active = 1
                AND n.published_at IS NOT NULL
                AND n.published_at <= NOW()
              ORDER BY n.published_at DESC, n.id DESC"
        );
    }

    public function getLatest($limit = 3)
    {
        return $this->fetchAll(
            "SELECT n.id, n.title, n.slug, n.excerpt, n.content, a.url AS image_url, n.published_at, n.active, n.created_at, n.updated_at
               FROM site_news_posts n
          LEFT JOIN site_assets a ON a.id = n.cover_asset_id
              WHERE n.active = 1
                AND n.published_at IS NOT NULL
                AND n.published_at <= NOW()
              ORDER BY n.published_at DESC
              LIMIT ?",
            [$limit]
        );
    }
}
