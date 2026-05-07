<?php

namespace App\Models;

use App\Core\Model;

class Service extends Model
{
    public function getAllActive()
    {
        return $this->fetchAll("SELECT * FROM site_services WHERE active = 1 ORDER BY sort_order ASC, id ASC");
    }
}
