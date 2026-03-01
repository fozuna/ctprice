<?php

namespace App\Models;

use App\Core\Model;

class News extends Model
{
    public function getLatest($limit = 3)
    {
        return $this->fetchAll("SELECT * FROM news WHERE active = 1 AND published_at <= NOW() ORDER BY published_at DESC LIMIT ?", [$limit]);
    }
}
