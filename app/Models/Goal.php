<?php
/**
 * Modelo Goal
 * Sistema BDO - Controle de Maquinários
 */

class Goal extends BaseModel {
    protected $table = 'goals';
    
    /**
     * Buscar metas com detalhes
     */
    public function findAllWithDetails($conditions = [], $orderBy = 'start_date DESC', $limit = null) {
        $sql = "SELECT g.*, 
                       f.name as front_name, f.code as front_code,
                       c.name as client_name, c.code as client_code,
                       u.name as created_by_name
                FROM {$this->table} g
                LEFT JOIN fronts f ON g.front_id = f.id
                LEFT JOIN clients c ON g.client_id = c.id
                LEFT JOIN users u ON g.created_by = u.id";
        
        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', array_map(function($key) {
                return "g.{$key} = :{$key}";
            }, array_keys($conditions)));
        }
        
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($conditions as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar meta por ID com detalhes
     */
    public function findByIdWithDetails($id) {
        $goals = $this->findAllWithDetails(['id' => $id]);
        return $goals ? $goals[0] : false;
    }
    
    /**
     * Criar nova meta e gerar metas diárias
     */
    public function create($data) {
        // Validar dados obrigatórios
        $goalType = $data['goal_type'] ?? 'production';
        $clientRequired = ($goalType !== 'production');
        if (empty($data['front_id']) || ($clientRequired && empty($data['client_id'])) || 
            empty($data['start_date']) || empty($data['end_date']) || 
            empty($data['total_goal'])) {
            throw new Exception('Todos os campos obrigatórios devem ser preenchidos');
        }
        
        // Validar datas
        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        
        if ($startDate >= $endDate) {
            throw new Exception('Data final deve ser maior que data inicial');
        }
        
        // Iniciar transação
        $this->conn->beginTransaction();
        
        try {
            // Criar meta principal
            $goalId = parent::create($data);

            // Tipo de meta (default produção já obtido acima)
            
            // Gerar metas diárias (considera regra específica por tipo)
            $this->generateDailyGoals($goalId, $data['start_date'], $data['end_date'], $data['total_goal'], $goalType);
            
            // Registrar histórico das metas diárias criadas
            if (class_exists('AuditLog')) {
                $audit = new AuditLog($this->conn);
                $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;
                $newValues = $this->fetchDailyGoals($goalId);
                $audit->logInsert('daily_goals', $goalId, $newValues, $userId);
            }
            
            $this->conn->commit();
            return $goalId;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Atualizar meta e recalcular metas diárias
     */
    public function update($id, $data) {
        // Buscar meta atual
        $currentGoal = $this->findById($id);
        if (!$currentGoal) {
            throw new Exception('Meta não encontrada');
        }
        
        // Iniciar transação
        $this->conn->beginTransaction();
        
        try {
            // Atualizar meta principal
            parent::update($id, $data);
            
            // Se datas ou valor total mudaram, recalcular metas diárias
            if (isset($data['start_date']) || isset($data['end_date']) || isset($data['total_goal'])) {
                $startDate = $data['start_date'] ?? $currentGoal['start_date'];
                $endDate = $data['end_date'] ?? $currentGoal['end_date'];
                $totalGoal = $data['total_goal'] ?? $currentGoal['total_goal'];
                $goalType = $data['goal_type'] ?? ($currentGoal['goal_type'] ?? 'production');
                
                // Registrar snapshot antes da alteração
                $oldValues = $this->fetchDailyGoals($id);
                
                // Remover metas diárias antigas
                $this->deleteDailyGoals($id);
                
                // Gerar novas metas diárias
                $this->generateDailyGoals($id, $startDate, $endDate, $totalGoal, $goalType);
                
                // Registrar histórico após a alteração
                if (class_exists('AuditLog')) {
                    $audit = new AuditLog($this->conn);
                    $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;
                    $newValues = $this->fetchDailyGoals($id);
                    $audit->logUpdate('daily_goals', $id, $oldValues, $newValues, $userId);
                }
            }
            
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Gerar metas diárias automaticamente
     */
    private function generateDailyGoals($goalId, $startDate, $endDate, $totalGoal, $goalType = 'production') {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $policyDate = new DateTime('2026-03-01');
        $isDelivery = ($goalType === 'delivery');
        
        // Produção: distribuição uniforme por dia do período
        if (!$isDelivery || $end < $policyDate) {
            $interval = $start->diff($end);
            $totalDays = $interval->days + 1;
            $dailyGoal = round($totalGoal / $totalDays, 2);
            
            $current = clone $start;
            $dailyGoalsData = [];
            while ($current <= $end) {
                $weekInfo = $this->getWeekInfo($current);
                $monthlyGoal = $this->calculateMonthlyGoal($current, $start, $end, $totalGoal, $goalType);
                $weeklyGoal = $this->calculateWeeklyGoal($current, $start, $end, $totalGoal, $goalType);
                
                $dailyGoalsData[] = [
                    'goal_id' => $goalId,
                    'goal_date' => $current->format('Y-m-d'),
                    'week_number' => $weekInfo['week_number'],
                    'week_start_date' => $weekInfo['week_start'],
                    'daily_goal' => $dailyGoal,
                    'weekly_goal' => $weeklyGoal,
                    'monthly_goal' => $monthlyGoal
                ];
                $current->add(new DateInterval('P1D'));
            }
            $this->insertDailyGoalsBatch($dailyGoalsData);
            return;
        }
        
        // Entrega a partir de 03/2026: redistribuir por semanas, excluindo domingos e feriados
        $holidays = $this->getHolidaysMap($start, $end); // opcional; vazio se tabela não existir
        
        // Enumerar semanas (seg a dom) que intersectam o período
        $firstWeekStart = new DateTime($this->getWeekInfo($start)['week_start']);
        $weeks = [];
        $ws = clone $firstWeekStart;
        while ($ws <= $end) {
            $we = (clone $ws)->add(new DateInterval('P6D'));
            $seg = [
                'start' => $ws < $start ? clone $start : clone $ws,
                'end'   => $we > $end ? clone $end : clone $we,
                'week_start' => $ws->format('Y-m-d'),
            ];
            $weeks[] = $seg;
            $ws = (clone $we)->add(new DateInterval('P1D'));
        }
        if (empty($weeks)) {
            throw new Exception('Período inválido para cálculo de metas.');
        }
        // Pré-calcular dias úteis por semana e filtrar semanas elegíveis (com >=1 dia útil)
        $weeksMeta = [];
        foreach ($weeks as $seg) {
            $dates = $this->listWorkingDatesMonSat($seg['start'], $seg['end'], $holidays);
            $weeksMeta[] = [
                'week_start' => $seg['week_start'],
                'start' => $seg['start'],
                'end' => $seg['end'],
                'dates' => $dates,
                'n' => count($dates),
            ];
        }
        $eligible = array_values(array_filter($weeksMeta, fn($w) => $w['n'] > 0));
        if (count($eligible) === 0) {
            throw new Exception('Nenhuma semana com dias úteis (seg a sáb) para distribuir a meta.');
        }
        // Distribuir meta total por semanas elegíveis em centavos garantindo soma exata
        $totalCents = (int)round($totalGoal * 100);
        $numWeeks = count($eligible);
        $baseWeek = intdiv($totalCents, $numWeeks);
        $weekRemainder = $totalCents - ($baseWeek * $numWeeks);
        $weekAllocMap = []; // week_start => cents
        for ($i = 0; $i < $numWeeks; $i++) {
            $weekAllocMap[$eligible[$i]['week_start']] = $baseWeek + ($i < $weekRemainder ? 1 : 0);
        }
        // Preparar distribuição diária por semana
        $plan = []; // 'Y-m-d' => ['daily'=>float, 'weekly'=>float]
        foreach ($eligible as $seg) {
            $n = $seg['n'];
            $wc = $weekAllocMap[$seg['week_start']];
            $dailyBase = intdiv($wc, $n);
            $dailyRem = $wc - ($dailyBase * $n);
            foreach ($seg['dates'] as $idx => $d) {
                $val = $dailyBase + ($idx < $dailyRem ? 1 : 0);
                $plan[$d] = [
                    'daily' => $val / 100.0,
                    'weekly' => $wc / 100.0,
                ];
            }
        }
        
        // Garantir soma exata do plano
        $sumPlanCents = 0;
        foreach ($plan as $p) $sumPlanCents += (int)round($p['daily'] * 100);
        if ($sumPlanCents !== $totalCents) {
            // Ajustar no último dia útil
            $keys = array_keys($plan);
            $last = end($keys);
            $delta = $totalCents - $sumPlanCents;
            $plan[$last]['daily'] = round($plan[$last]['daily'] + ($delta / 100.0), 2);
        }
        
        // Montar registros por dia do período
        $dailyGoalsData = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $dateStr = $cursor->format('Y-m-d');
            $weekInfo = $this->getWeekInfo($cursor);
            $monthlyGoal = $this->calculateMonthlyGoal($cursor, $start, $end, $totalGoal, $goalType);
            
            $dow = intval($cursor->format('w')); // 0 Dom
            $isHoliday = isset($holidays[$dateStr]);
            if ($dow === 0 || $isHoliday) {
                $daily = 0.00;
                // Descobrir weekly a partir da semana (se elegível)
                $weekly = isset($weekAllocMap[$weekInfo['week_start']]) ? ($weekAllocMap[$weekInfo['week_start']] / 100.0) : 0.00;
            } else {
                $daily = isset($plan[$dateStr]) ? $plan[$dateStr]['daily'] : 0.00;
                $weekly = isset($plan[$dateStr]) ? $plan[$dateStr]['weekly'] : 0.00;
            }
            
            $dailyGoalsData[] = [
                'goal_id' => $goalId,
                'goal_date' => $dateStr,
                'week_number' => $weekInfo['week_number'],
                'week_start_date' => $weekInfo['week_start'],
                'daily_goal' => $daily,
                'weekly_goal' => $weekly,
                'monthly_goal' => $monthlyGoal
            ];
            
            $cursor->add(new DateInterval('P1D'));
        }
        
        $this->insertDailyGoalsBatch($dailyGoalsData);
    }

    /**
     * Contar dias úteis (segunda a sábado) entre duas datas inclusivas
     */
    private function countWorkingDaysMonSat(DateTime $start, DateTime $end) {
        $count = 0;
        $cursor = clone $start;
        while ($cursor <= $end) {
            $dow = intval($cursor->format('w')); // 0=Dom, 6=Sáb
            if ($dow >= 1 && $dow <= 6) {
                $count++;
            }
            $cursor->add(new DateInterval('P1D'));
        }
        return $count;
    }
    
    private function listWorkingDatesMonSat(DateTime $start, DateTime $end, array $holidaysMap = []) {
        $dates = [];
        $cursor = clone $start;
        while ($cursor <= $end) {
            $dow = intval($cursor->format('w')); // 0=Dom, 6=Sáb
            $d = $cursor->format('Y-m-d');
            if ($dow >= 1 && $dow <= 6 && !isset($holidaysMap[$d])) {
                $dates[] = $d;
            }
            $cursor->add(new DateInterval('P1D'));
        }
        return $dates;
    }
    
    private function getHolidaysMap(DateTime $start, DateTime $end) {
        // Consulta opcional à tabela 'holidays' (date, description). Se a tabela não existir, retorna vazio.
        $map = [];
        try {
            $stmt = $this->conn->prepare("SELECT holiday_date FROM holidays WHERE holiday_date BETWEEN :s AND :e");
            $stmt->bindValue(':s', $start->format('Y-m-d'));
            $stmt->bindValue(':e', $end->format('Y-m-d'));
            if ($stmt->execute()) {
                foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $d) {
                    $map[$d] = true;
                }
            }
        } catch (Exception $e) {
            // Silencioso: tabela pode não existir neste ambiente
        }
        return $map;
    }
    
    /**
     * Calcular informações da semana (segunda-feira como início)
     */
    public function getWeekInfo($date) {
        $current = clone $date;
        
        // Encontrar a segunda-feira da semana
        $dayOfWeek = $current->format('N'); // 1 = segunda, 7 = domingo
        $daysToSubtract = $dayOfWeek - 1;
        $weekStart = clone $current;
        $weekStart->sub(new DateInterval("P{$daysToSubtract}D"));
        
        // Calcular número da semana no ano
        $weekNumber = (int)$weekStart->format('W');
        
        return [
            'week_number' => $weekNumber,
            'week_start' => $weekStart->format('Y-m-d')
        ];
    }
    
    /**
     * Calcular meta mensal
     */
    private function calculateMonthlyGoal($currentDate, $startDate, $endDate, $totalGoal, $goalType = 'production') {
        // Verificar se os parâmetros são strings ou objetos DateTime
        if (is_string($startDate)) {
            $start = new DateTime($startDate);
        } else {
            $start = clone $startDate;
        }
        
        if (is_string($endDate)) {
            $end = new DateTime($endDate);
        } else {
            $end = clone $endDate;
        }
        
        // $currentDate já é um objeto DateTime
        $current = clone $currentDate;
        
        // Primeiro e último dia do mês atual
        $monthStart = new DateTime($current->format('Y-m-01'));
        $monthEnd = new DateTime($current->format('Y-m-t'));
        
        // Ajustar para o período da meta
        if ($monthStart < $start) $monthStart = clone $start;
        if ($monthEnd > $end) $monthEnd = clone $end;
        
        // Calcular dias no mês dentro do período
        $monthDays = $monthStart->diff($monthEnd)->days + 1;
        $totalDays = $start->diff($end)->days + 1;

        // Produção passa a considerar todos os dias (mesma regra proporcional)
        return round(($monthDays / $totalDays) * $totalGoal, 2);
    }
    
    /**
     * Calcular meta semanal
     */
    private function calculateWeeklyGoal($currentDate, $startDate, $endDate, $totalGoal, $goalType = 'production') {
        // Verificar se os parâmetros são strings ou objetos DateTime
        if (is_string($startDate)) {
            $start = new DateTime($startDate);
        } else {
            $start = clone $startDate;
        }
        
        if (is_string($endDate)) {
            $end = new DateTime($endDate);
        } else {
            $end = clone $endDate;
        }
        
        $weekInfo = $this->getWeekInfo($currentDate);
        
        // Primeiro e último dia da semana
        $weekStart = new DateTime($weekInfo['week_start']);
        $weekEnd = clone $weekStart;
        $weekEnd->add(new DateInterval('P6D'));
        
        // Ajustar para o período da meta
        if ($weekStart < $start) $weekStart = clone $start;
        if ($weekEnd > $end) $weekEnd = clone $end;
        
        // Calcular dias na semana dentro do período
        $weekDays = $weekStart->diff($weekEnd)->days + 1;
        $totalDays = $start->diff($end)->days + 1;

        // Produção passa a considerar todos os dias (mesma regra proporcional)
        return round(($weekDays / $totalDays) * $totalGoal, 2);
    }
    
    /**
     * Inserir metas diárias em lote
     */
    private function insertDailyGoalsBatch($dailyGoalsData) {
        $sql = "INSERT INTO daily_goals (goal_id, goal_date, week_number, week_start_date, daily_goal, weekly_goal, monthly_goal) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($sql);
        
        foreach ($dailyGoalsData as $data) {
            $stmt->execute([
                $data['goal_id'],
                $data['goal_date'],
                $data['week_number'],
                $data['week_start_date'],
                $data['daily_goal'],
                $data['weekly_goal'],
                $data['monthly_goal']
            ]);
        }
    }

    /**
     * Buscar metas diárias de uma meta (para histórico/rollback)
     */
    private function fetchDailyGoals($goalId) {
        $sql = "SELECT goal_date, week_number, week_start_date, daily_goal, weekly_goal, monthly_goal 
                FROM daily_goals WHERE goal_id = ? ORDER BY goal_date ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$goalId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Reverter metas diárias para o último snapshot de auditoria
     */
    public function rollbackDailyGoalsFromLastSnapshot($goalId) {
        // Buscar último log de UPDATE para essa meta
        $sql = "SELECT id, old_values, new_values FROM audit_logs 
                WHERE table_name = 'daily_goals' AND record_id = :rid AND action = 'UPDATE' 
                ORDER BY created_at DESC, id DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':rid', $goalId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new Exception('Nenhum snapshot de metas diárias encontrado para rollback.');
        }
        $snapshot = json_decode($row['old_values'] ?: '[]', true);
        if (!is_array($snapshot)) {
            throw new Exception('Snapshot inválido para rollback.');
        }
        
        $this->conn->beginTransaction();
        try {
            // Apagar metas atuais
            $this->deleteDailyGoals($goalId);
            // Recriar a partir do snapshot
            $batch = [];
            foreach ($snapshot as $dg) {
                $batch[] = [
                    'goal_id' => $goalId,
                    'goal_date' => $dg['goal_date'],
                    'week_number' => $dg['week_number'],
                    'week_start_date' => $dg['week_start_date'],
                    'daily_goal' => $dg['daily_goal'],
                    'weekly_goal' => $dg['weekly_goal'],
                    'monthly_goal' => $dg['monthly_goal'],
                ];
            }
            if (!empty($batch)) {
                $this->insertDailyGoalsBatch($batch);
            }
            $this->conn->commit();
            
            // Registrar auditoria da reversão
            if (class_exists('AuditLog')) {
                $audit = new AuditLog($this->conn);
                $userId = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 1;
                $currentValues = $this->fetchDailyGoals($goalId);
                $audit->logUpdate('daily_goals', $goalId, $row['new_values'] ? json_decode($row['new_values'], true) : null, $currentValues, $userId);
            }
            return true;
        } catch (Exception $e) {
            $this->conn->rollback();
            throw $e;
        }
    }
    
    /**
     * Remover metas diárias de uma meta
     */
    private function deleteDailyGoals($goalId) {
        $sql = "DELETE FROM daily_goals WHERE goal_id = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([$goalId]);
    }
    
    /**
     * Buscar metas por período
     */
    public function findByPeriod($startDate, $endDate, $frontId = null, $clientId = null) {
        $sql = "SELECT g.*, 
                       f.name as front_name, f.code as front_code,
                       c.name as client_name, c.code as client_code
                FROM {$this->table} g
                LEFT JOIN fronts f ON g.front_id = f.id
                LEFT JOIN clients c ON g.client_id = c.id
                WHERE g.start_date <= :end_date AND g.end_date >= :start_date";
        
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        if ($frontId) {
            $sql .= " AND g.front_id = :front_id";
            $params['front_id'] = $frontId;
        }
        
        if ($clientId) {
            $sql .= " AND g.client_id = :client_id";
            $params['client_id'] = $clientId;
        }
        
        $sql .= " ORDER BY g.start_date ASC";
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar resumo de metas por mês
     */
    public function getMonthlyGoalsSummary($year, $month, $frontId = null, $clientId = null, $goalType = null) {
        $monthDate = new DateTime(sprintf('%04d-%02d-01', $year, $month));
        $monthStart = $monthDate->format('Y-m-01');
        $monthEnd = $monthDate->format('Y-m-t');
        
        $sql = "SELECT 
                    g.id,
                    g.start_date,
                    g.end_date,
                    g.total_goal,
                    g.goal_type,
                    g.front_id,
                    g.client_id,
                    f.name as front_name,
                    f.code as front_code,
                    c.name as client_name,
                    c.code as client_code
                FROM goals g
                JOIN fronts f ON g.front_id = f.id
                LEFT JOIN clients c ON g.client_id = c.id
                WHERE g.start_date <= :month_end 
                  AND g.end_date >= :month_start";
        
        $params = [
            'month_start' => $monthStart,
            'month_end' => $monthEnd
        ];
        
        if ($frontId) {
            $sql .= " AND g.front_id = :front_id";
            $params['front_id'] = $frontId;
        }
        if ($clientId) {
            $sql .= " AND g.client_id = :client_id";
            $params['client_id'] = $clientId;
        }
        if ($goalType) {
            $sql .= " AND g.goal_type = :goal_type";
            $params['goal_type'] = $goalType;
        }
        
        $sql .= " ORDER BY f.name, c.name, g.start_date";
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        $summaryMap = [];
        foreach ($rows as $row) {
            $k = $row['front_id'] . '|' . ($row['client_id'] ?? 'null');
            if (!isset($summaryMap[$k])) {
                $summaryMap[$k] = [
                    'front_id' => $row['front_id'],
                    'front_name' => $row['front_name'],
                    'front_code' => $row['front_code'] ?? null,
                    'client_id' => $row['client_id'],
                    'client_name' => $row['client_name'],
                    'client_code' => $row['client_code'] ?? null,
                    'monthly_total' => 0,
                    'weekly_total' => 0
                ];
            }
            $type = $row['goal_type'] ?: 'production';
            $monthlyValue = $this->calculateMonthlyGoal($monthDate, $row['start_date'], $row['end_date'], $row['total_goal'], $type);
            if ($monthlyValue > 0) {
                $summaryMap[$k]['monthly_total'] += $monthlyValue;
            }
        }
        
        // Calcular média semanal com base nas metas diárias consolidadas (entrega pós-política)
        $daysInMonth = (int)$monthDate->format('t');
        $useAccurateWeekly = ($goalType === 'delivery') && ($monthDate >= new DateTime('2026-03-01'));
        if ($useAccurateWeekly) {
            $sqlW = "SELECT g.front_id, g.client_id, dg.week_start_date, SUM(dg.daily_goal) as week_sum
                     FROM daily_goals dg
                     JOIN goals g ON dg.goal_id = g.id
                     WHERE dg.goal_date BETWEEN :start AND :end
                       AND g.goal_type = 'delivery'";
            if ($frontId) $sqlW .= " AND g.front_id = :front_id";
            if ($clientId) $sqlW .= " AND g.client_id = :client_id";
            $sqlW .= " GROUP BY g.front_id, g.client_id, dg.week_start_date";
            $stmtW = $this->conn->prepare($sqlW);
            $stmtW->bindValue(':start', $monthStart);
            $stmtW->bindValue(':end', $monthEnd);
            if ($frontId) $stmtW->bindValue(':front_id', $frontId);
            if ($clientId) $stmtW->bindValue(':client_id', $clientId);
            $stmtW->execute();
            $rowsW = $stmtW->fetchAll();
            $agg = [];
            foreach ($rowsW as $r) {
                $k = $r['front_id'] . '|' . ($r['client_id'] ?? 'null');
                if (!isset($agg[$k])) $agg[$k] = ['sum' => 0.0, 'count' => 0];
                $agg[$k]['sum'] += (float)$r['week_sum'];
                $agg[$k]['count'] += 1;
            }
            foreach ($summaryMap as $k => $item) {
                if (isset($agg[$k]) && $agg[$k]['count'] > 0) {
                    $summaryMap[$k]['weekly_total'] = round($agg[$k]['sum'] / $agg[$k]['count'], 2);
                } else {
                    $summaryMap[$k]['weekly_total'] = $daysInMonth > 0 ? round(($item['monthly_total'] * 7) / $daysInMonth, 2) : 0;
                }
            }
        } else {
            foreach ($summaryMap as $k => $item) {
                $summaryMap[$k]['weekly_total'] = $daysInMonth > 0 ? round(($item['monthly_total'] * 7) / $daysInMonth, 2) : 0;
            }
        }
        
        $result = array_values(array_filter($summaryMap, function($item) {
            return ($item['monthly_total'] ?? 0) > 0;
        }));
        
        usort($result, function($a, $b) {
            $fa = $a['front_name'] ?? '';
            $fb = $b['front_name'] ?? '';
            if ($fa === $fb) {
                return strcmp($a['client_name'] ?? '', $b['client_name'] ?? '');
            }
            return strcmp($fa, $fb);
        });
        
        return $result;
    }
    
    /**
     * Buscar metas diárias por período
     */
    public function getDailyGoals($startDate, $endDate, $frontId = null, $clientId = null, $goalType = null) {
        $sql = "SELECT dg.*, 
                       g.front_id, g.client_id,
                       f.name as front_name, f.code as front_code,
                       c.name as client_name, c.code as client_code
                FROM daily_goals dg
        JOIN goals g ON dg.goal_id = g.id
        JOIN fronts f ON g.front_id = f.id
        LEFT JOIN clients c ON g.client_id = c.id
                WHERE dg.goal_date BETWEEN :start_date AND :end_date";
        
        $params = [
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
        
        if ($frontId) {
            $sql .= " AND g.front_id = :front_id";
            $params['front_id'] = $frontId;
        }
        
        if ($clientId) {
            $sql .= " AND g.client_id = :client_id";
            $params['client_id'] = $clientId;
        }

        if ($goalType) {
            $sql .= " AND g.goal_type = :goal_type";
            $params['goal_type'] = $goalType;
        }
        
        $sql .= " ORDER BY dg.goal_date ASC, f.name, c.name";
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Buscar metas com seus períodos específicos para cálculo semanal
     */
    public function getGoalsWithPeriods($year, $month, $frontId = null, $clientId = null, $goalType = null) {
        // Criar datas do primeiro e último dia do mês
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        
        $sql = "SELECT 
                    g.id,
                    g.start_date,
                    g.end_date,
                    g.total_goal,
                    g.goal_type,
                    f.name as front_name,
                    c.name as client_name,
                    g.front_id,
                    g.client_id
                FROM goals g
        JOIN fronts f ON g.front_id = f.id
        LEFT JOIN clients c ON g.client_id = c.id
                WHERE g.start_date <= :month_end 
                  AND g.end_date >= :month_start";
        
        $params = [
            'month_start' => $monthStart,
            'month_end' => $monthEnd
        ];
        
        if ($frontId) {
            $sql .= " AND g.front_id = :front_id";
            $params['front_id'] = $frontId;
        }
        
        if ($clientId) {
            $sql .= " AND g.client_id = :client_id";
            $params['client_id'] = $clientId;
        }

        if ($goalType) {
            $sql .= " AND g.goal_type = :goal_type";
            $params['goal_type'] = $goalType;
        }
        
        $sql .= " ORDER BY f.name, c.name, g.start_date";
        
        $stmt = $this->conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    public function computeMonthlyValueForGoalRow($monthDate, $row) {
        $type = $row['goal_type'] ?? 'production';
        return $this->calculateMonthlyGoal(
            $monthDate instanceof DateTime ? $monthDate : new DateTime($monthDate),
            $row['start_date'],
            $row['end_date'],
            $row['total_goal'],
            $type
        );
    }
    
    /**
     * Buscar frentes que têm metas em um mês específico
     */
    public function getFrontsWithGoalsInMonth($year, $month, $goalType = null) {
        $sql = "SELECT DISTINCT f.id, f.name, f.code
                FROM fronts f
                INNER JOIN goals g ON f.id = g.front_id
                WHERE f.active = 1
                AND (
                    (YEAR(g.start_date) = :year1 AND MONTH(g.start_date) = :month1)
                    OR (YEAR(g.end_date) = :year2 AND MONTH(g.end_date) = :month2)
                    OR (g.start_date <= :month_start AND g.end_date >= :month_end)
                )";

        if ($goalType) {
            $sql .= " AND g.goal_type = :goal_type";
        }

        $sql .= " ORDER BY f.name";
        
        $monthStart = sprintf('%04d-%02d-01', $year, $month);
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':year1', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month1', $month, PDO::PARAM_INT);
        $stmt->bindValue(':year2', $year, PDO::PARAM_INT);
        $stmt->bindValue(':month2', $month, PDO::PARAM_INT);
        $stmt->bindValue(':month_start', $monthStart);
        $stmt->bindValue(':month_end', $monthEnd);
        if ($goalType) {
            $stmt->bindValue(':goal_type', $goalType);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Buscar estatísticas anuais das metas
     */
    public function getYearlyStats($year) {
        $sql = "SELECT 
                    COUNT(*) as total_goals,
                    COALESCE(SUM(g.total_goal), 0) as total_value,
                    COALESCE(AVG(g.total_goal), 0) as avg_goal,
                    COALESCE(MIN(g.total_goal), 0) as min_goal,
                    COALESCE(MAX(g.total_goal), 0) as max_goal,
                    COUNT(DISTINCT g.front_id) as total_fronts,
                    COUNT(DISTINCT g.client_id) as total_clients
                FROM goals g
                WHERE YEAR(g.start_date) = ? OR YEAR(g.end_date) = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $year]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar metas por mês do ano
     */
    public function getMonthlyGoalsForYear($year) {
        $sql = "SELECT 
                    month,
                    MONTHNAME(STR_TO_DATE(month, '%m')) as month_name,
                    COALESCE(SUM(monthly_goal), 0) as total_monthly_goal,
                    COUNT(goal_id) as goals_count
                FROM (
                    SELECT DISTINCT 
                        goal_id,
                        MONTH(goal_date) as month,
                        monthly_goal
                    FROM daily_goals
                    WHERE YEAR(goal_date) = ?
                ) unique_monthly_goals
                GROUP BY month
                ORDER BY month";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getMonthlyGoalsForYearByType($year, $goalType) {
        $sql = "SELECT 
                    month,
                    MONTHNAME(STR_TO_DATE(month, '%m')) as month_name,
                    COALESCE(SUM(monthly_goal), 0) as total_monthly_goal,
                    COUNT(goal_id) as goals_count
                FROM (
                    SELECT DISTINCT 
                        dg.goal_id,
                        MONTH(dg.goal_date) as month,
                        dg.monthly_goal
                    FROM daily_goals dg
                    JOIN goals g ON g.id = dg.goal_id
                    WHERE YEAR(dg.goal_date) = ?
                      AND g.goal_type = ?
                ) unique_monthly_goals
                GROUP BY month
                ORDER BY month";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $goalType]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar metas por frente no ano
     */
    public function getGoalsByFrontForYear($year) {
        $sql = "SELECT 
                    f.id,
                    f.name as front_name,
                    f.code as front_code,
                    COUNT(DISTINCT g.client_id) as clients_count,
                    COALESCE(SUM(g.total_goal), 0) as total_value,
                    COALESCE(AVG(g.total_goal), 0) as avg_goal
                FROM fronts f
                JOIN goals g ON f.id = g.front_id
                WHERE (YEAR(g.start_date) = ? OR YEAR(g.end_date) = ?)
                AND f.active = 1
                GROUP BY f.id, f.name, f.code
                ORDER BY total_value DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $year]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getGoalsByFrontForYearByType($year, $goalType) {
        $sql = "SELECT 
                    f.id,
                    f.name as front_name,
                    f.code as front_code,
                    COUNT(DISTINCT g.client_id) as clients_count,
                    COALESCE(SUM(g.total_goal), 0) as total_value,
                    COALESCE(AVG(g.total_goal), 0) as avg_goal
                FROM fronts f
                JOIN goals g ON f.id = g.front_id
                WHERE (YEAR(g.start_date) = ? OR YEAR(g.end_date) = ?)
                  AND g.goal_type = ?
                GROUP BY f.id, f.name, f.code
                ORDER BY total_value DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $year, $goalType]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar metas por cliente no ano
     */
    public function getGoalsByClientForYear($year) {
        $sql = "SELECT 
                    c.id,
                    c.name as client_name,
                    c.code as client_code,
                    COUNT(DISTINCT g.front_id) as fronts_count,
                    COALESCE(SUM(g.total_goal), 0) as total_value,
                    COALESCE(AVG(g.total_goal), 0) as avg_goal
                FROM clients c
                JOIN goals g ON c.id = g.client_id
                WHERE (YEAR(g.start_date) = ? OR YEAR(g.end_date) = ?)
                AND c.active = 1
                GROUP BY c.id, c.name, c.code
                ORDER BY total_value DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $year]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar comparação entre anos
     */
    public function getYearComparison($currentYear, $previousYear) {
        $sql = "SELECT 
                    'current' as period,
                    ? as year,
                    COUNT(*) as total_goals,
                    COALESCE(SUM(g.total_goal), 0) as total_value,
                    COALESCE(AVG(g.total_goal), 0) as avg_goal
                FROM goals g
                WHERE YEAR(g.start_date) = ? OR YEAR(g.end_date) = ?
                
                UNION ALL
                
                SELECT 
                    'previous' as period,
                    ? as year,
                    COUNT(*) as total_goals,
                    COALESCE(SUM(g.total_goal), 0) as total_value,
                    COALESCE(AVG(g.total_goal), 0) as avg_goal
                FROM goals g
                WHERE YEAR(g.start_date) = ? OR YEAR(g.end_date) = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$currentYear, $currentYear, $currentYear, $previousYear, $previousYear, $previousYear]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar top 5 metas do ano
     */
    public function getTopGoalsForYear($year, $limit = 5) {
        $sql = "SELECT 
                    g.id,
                    g.total_goal,
                    g.start_date,
                    g.end_date,
                    f.name as front_name,
                    c.name as client_name,
                    DATEDIFF(g.end_date, g.start_date) + 1 as duration_days
                FROM goals g
        JOIN fronts f ON g.front_id = f.id
        LEFT JOIN clients c ON g.client_id = c.id
                WHERE YEAR(g.start_date) = ? OR YEAR(g.end_date) = ?
                ORDER BY g.total_goal DESC
                LIMIT ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$year, $year, $limit]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Buscar anos disponíveis com metas
     */
    public function getAvailableYears() {
        $sql = "SELECT DISTINCT YEAR(start_date) as year
                FROM goals
                UNION
                SELECT DISTINCT YEAR(end_date) as year
                FROM goals
                ORDER BY year DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
?>
