<?php

namespace App\Models;

use App\Core\Model;

class Testimonial extends Model
{
    public function getAllActive()
    {
        return $this->fetchAll(
            "SELECT t.id, t.client_name, t.client_company, t.content, a.url AS image_url, t.active, t.sort_order, t.created_at, t.updated_at
               FROM site_testimonials t
          LEFT JOIN site_assets a ON a.id = t.image_asset_id
              WHERE t.active = 1
              ORDER BY t.sort_order ASC, t.id ASC"
        );
    }
}
