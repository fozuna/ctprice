<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Vaga;
use App\Models\Candidatura;

class AdminController extends Controller
{
    public function index(): void
    {
        Auth::requireRole(['admin', 'rh', 'viewer']);
        $vagasAtivas = count(Vaga::allActive());
        $totalCandidaturas = count(Candidatura::all());
        $statuses = ['novo','em_analise','entrevista','aprovado','dispensado'];
        $kanban = [];
        foreach ($statuses as $st) {
            $kanban[$st] = Candidatura::all(['status' => $st]);
        }
        $this->view->render('admin/dashboard', [
            'vagasAtivas' => $vagasAtivas,
            'totalCandidaturas' => $totalCandidaturas,
            'kanban' => $kanban,
            'statuses' => $statuses,
        ], 'layouts/admin');
    }
}