<?php

namespace App\Models;

use App\Core\Model;

class Banner extends Model
{
    public function getAllActive()
    {
        return $this->fetchAll("SELECT * FROM banners WHERE active = 1 ORDER BY sort_order ASC");
    }
}
