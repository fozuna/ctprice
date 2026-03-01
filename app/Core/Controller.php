<?php

namespace App\Core;

abstract class Controller
{
    public function view($view, $data = [])
    {
        return View::render($view, $data);
    }

    public function json($data)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
