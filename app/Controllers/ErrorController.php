<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\View;

class ErrorController extends Controller
{
    public function notFound()
    {
        View::render('errors/404', ['title' => 'Página não encontrada']);
    }
}
