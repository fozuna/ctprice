<?php

namespace App\Models;

use App\Core\Model;

class Partner extends Model
{
    public function getAllActive()
    {
        return $this->fetchAll(
            "SELECT p.id, p.name, a.url AS logo_url, p.link_url, p.active, p.sort_order, p.created_at, p.updated_at
               FROM site_partners p
               JOIN site_assets a ON a.id = p.logo_asset_id
              WHERE p.active = 1
              ORDER BY p.sort_order ASC, p.id ASC"
        );
    }
}
