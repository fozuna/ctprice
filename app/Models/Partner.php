<?php

namespace App\Models;

use App\Core\Model;

class Partner extends Model
{
    public function getAllActive()
    {
        return $this->fetchAll("SELECT * FROM partners WHERE active = 1 ORDER BY sort_order ASC");
    }
}
