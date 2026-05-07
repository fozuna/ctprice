<?php
require_once __DIR__ . '/../includes/kanban_workflow_rules.php';

function wf_assert($cond, $msg)
{
    if (!$cond) {
        fwrite(STDERR, '[FAIL] ' . $msg . PHP_EOL);
        exit(1);
    }
    echo '[OK] ' . $msg . PHP_EOL;
}

try {
    $stages = [
        ['id' => 1, 'code' => 'RECEBIDO', 'position' => 1],
        ['id' => 2, 'code' => 'RH', 'position' => 2],
        ['id' => 3, 'code' => 'ENTREVISTA', 'position' => 3],
        ['id' => 4, 'code' => 'CONTRATADO', 'position' => 4],
    ];
    $map = kanbanBuildStagePositionMap($stages);
    wf_assert(($map[1] ?? null) === 1 && ($map[4] ?? null) === 4, 'Mapa de posições deve ser montado corretamente');

    $r1 = kanbanIsTransitionAllowed(1, 2, $map, false);
    wf_assert($r1['ok'] === true, 'Transição para próxima etapa deve ser permitida');

    $r2 = kanbanIsTransitionAllowed(3, 2, $map, false);
    wf_assert($r2['ok'] === true, 'Transição para etapa anterior deve ser permitida');

    $r3 = kanbanIsTransitionAllowed(1, 3, $map, false);
    wf_assert($r3['ok'] === false, 'Salto de duas etapas deve ser bloqueado para usuário comum');

    $r4 = kanbanIsTransitionAllowed(1, 3, $map, true);
    wf_assert($r4['ok'] === true, 'Salto de etapas deve ser permitido quando allowJump=true');

    $r5 = kanbanIsTransitionAllowed(2, 2, $map, false);
    wf_assert($r5['ok'] === false, 'Transição para a mesma etapa deve ser inválida');

    echo PHP_EOL . 'Resultado: testes de transição do workflow concluídos com sucesso.' . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo 'Erro inesperado: ' . $e->getMessage() . PHP_EOL;
    exit(1);
}

