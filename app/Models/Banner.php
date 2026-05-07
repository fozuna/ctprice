<?php

namespace App\Models;

use App\Core\Model;

class Banner extends Model
{
    public function getAllActive()
    {
        return $this->fetchAll(
            "SELECT b.id, b.title, b.subtitle, a.url AS image_url, b.link_url, b.button_text, b.active, b.sort_order, b.created_at, b.updated_at
               FROM site_banners b
               JOIN site_assets a ON a.id = b.image_asset_id
              WHERE b.active = 1
              ORDER BY b.sort_order ASC, b.id ASC"
        );
    }
}
