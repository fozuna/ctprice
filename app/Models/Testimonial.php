<?php

namespace App\Models;

use App\Core\Model;

class Testimonial extends Model
{
    public function getAllActive()
    {
        return $this->fetchAll("SELECT * FROM testimonials WHERE active = 1 ORDER BY sort_order ASC");
    }
}
