<?php

declare(strict_types=1);

return static function (PDO $pdo): void {
    $projectCode = 'ESTOQUE-001';
    $adminId = $pdo
        ->query("SELECT id FROM users WHERE email = 'admin@phalcon.local' LIMIT 1")
        ->fetchColumn();
    $adminId = $adminId !== false ? (int)$adminId : null;

    $projectStatement = $pdo->prepare(
        'SELECT id, start_date
         FROM projects
         WHERE code = :code
         ORDER BY id
         LIMIT 1'
    );
    $projectStatement->execute(['code' => $projectCode]);
    $project = $projectStatement->fetch();

    $nextMonday = new DateTimeImmutable('monday this week');
    if ($nextMonday < new DateTimeImmutable('today')) {
        $nextMonday = $nextMonday->modify('+7 days');
    }
    $projectStart = $project && !empty($project['start_date'])
        ? new DateTimeImmutable((string)$project['start_date'])
        : $nextMonday;

    $addBusinessDays = static function (DateTimeImmutable $date, int $days): DateTimeImmutable {
        $result = $date;
        $remaining = $days;

        while ($remaining > 0) {
            $result = $result->modify('+1 day');
            if ((int)$result->format('N') <= 5) {
                $remaining--;
            }
        }

        return $result;
    };

    $projectEnd = $addBusinessDays($projectStart, 24);
    $projectData = [
        'name' => 'Desenvolver um controle de estoque',
        'code' => $projectCode,
        'client' => 'Projeto demonstrativo',
        'description' => 'Implantação de um controle de estoque com cadastros, movimentações, inventário, relatórios e homologação.',
        'status' => 'in_progress',
        'priority' => 'high',
        'leader_id' => $adminId,
        'start_date' => $projectStart->format('Y-m-d'),
        'deadline' => $projectEnd->format('Y-m-d'),
        'budget' => 45000.00,
        'created_by' => $adminId,
        'updated_by' => $adminId,
    ];

    if ($project === false) {
        $insertProject = $pdo->prepare(
            'INSERT INTO projects (
                name, code, client, description, status, priority, leader_id,
                start_date, deadline, budget, created_by, updated_by
            ) VALUES (
                :name, :code, :client, :description, :status, :priority, :leader_id,
                :start_date, :deadline, :budget, :created_by, :updated_by
            )'
        );
        $insertProject->execute($projectData);
        $projectId = (int)$pdo->lastInsertId();
    } else {
        $projectId = (int)$project['id'];
        $updateProject = $pdo->prepare(
            'UPDATE projects SET
                name = :name,
                client = :client,
                description = :description,
                status = :status,
                priority = :priority,
                leader_id = :leader_id,
                start_date = :start_date,
                deadline = :deadline,
                budget = :budget,
                updated_by = :updated_by,
                deleted_at = NULL
             WHERE id = :id'
        );
        $updateProject->execute([
            'name' => $projectData['name'],
            'client' => $projectData['client'],
            'description' => $projectData['description'],
            'status' => $projectData['status'],
            'priority' => $projectData['priority'],
            'leader_id' => $projectData['leader_id'],
            'start_date' => $projectData['start_date'],
            'deadline' => $projectData['deadline'],
            'budget' => $projectData['budget'],
            'updated_by' => $projectData['updated_by'],
            'id' => $projectId,
        ]);
    }

    if ($adminId !== null) {
        $memberStatement = $pdo->prepare(
            'INSERT INTO project_members (project_id, user_id)
             VALUES (:project_id, :user_id)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
        );
        $memberStatement->execute([
            'project_id' => $projectId,
            'user_id' => $adminId,
        ]);
    }

    $taskBlueprints = [
        ['EST-000', 'Desenvolver um controle de estoque', 'Projeto completo, do levantamento à implantação.', 0, 'STATUS_ACTIVE', 35, 0, 24, 25, ''],
        ['EST-010', 'Levantar requisitos e regras do estoque', 'Mapear produtos, depósitos, unidades, níveis mínimos e fluxos de movimentação.', 1, 'STATUS_DONE', 100, 0, 2, 3, ''],
        ['EST-020', 'Modelar dados e arquitetura', 'Definir entidades, integrações e regras de consistência.', 1, 'STATUS_DONE', 100, 3, 5, 3, '2'],
        ['EST-030', 'Implementar cadastros básicos', 'Criar cadastros de produtos, categorias, fornecedores e depósitos.', 1, 'STATUS_ACTIVE', 60, 6, 9, 4, '3'],
        ['EST-040', 'Implementar entradas e saídas', 'Registrar movimentações, saldos e histórico por depósito.', 1, 'STATUS_WAITING', 0, 10, 14, 5, '4'],
        ['EST-050', 'Criar inventário e relatórios', 'Disponibilizar contagem, divergências, posição e giro de estoque.', 1, 'STATUS_WAITING', 0, 15, 18, 4, '5'],
        ['EST-060', 'Testar e homologar', 'Executar testes funcionais e validar o fluxo com os usuários.', 1, 'STATUS_WAITING', 0, 19, 22, 4, '6'],
        ['EST-070', 'Implantar controle de estoque', 'Marco de disponibilização do novo controle.', 1, 'STATUS_WAITING', 0, 23, 23, 1, '7'],
    ];

    $findTask = $pdo->prepare(
        'SELECT id FROM gantt_tasks
         WHERE project_id = :project_id AND code = :code
         ORDER BY id
         LIMIT 1'
    );
    $insertTask = $pdo->prepare(
        'INSERT INTO gantt_tasks (
            project_id, code, name, description, level, status, progress,
            start_at, end_at, duration, depends, sort_order, collapsed,
            start_is_milestone, end_is_milestone, created_by, updated_by
        ) VALUES (
            :project_id, :code, :name, :description, :level, :status, :progress,
            :start_at, :end_at, :duration, :depends, :sort_order, 0,
            :start_is_milestone, :end_is_milestone, :created_by, :updated_by
        )'
    );
    $updateTask = $pdo->prepare(
        'UPDATE gantt_tasks SET
            name = :name,
            description = :description,
            level = :level,
            status = :status,
            progress = :progress,
            start_at = :start_at,
            end_at = :end_at,
            duration = :duration,
            depends = :depends,
            sort_order = :sort_order,
            collapsed = 0,
            start_is_milestone = :start_is_milestone,
            end_is_milestone = :end_is_milestone,
            updated_by = :updated_by
         WHERE id = :id'
    );

    foreach ($taskBlueprints as $sortOrder => $task) {
        [$code, $name, $description, $level, $status, $progress, $startOffset, $endOffset, $duration, $depends] = $task;
        $startAt = $addBusinessDays($projectStart, $startOffset)->setTime(0, 0);
        $endAt = $addBusinessDays($projectStart, $endOffset)->setTime(23, 59, 59);
        $isMilestone = $code === 'EST-070' ? 1 : 0;

        $taskData = [
            'project_id' => $projectId,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'level' => $level,
            'status' => $status,
            'progress' => $progress,
            'start_at' => $startAt->format('Y-m-d H:i:s'),
            'end_at' => $endAt->format('Y-m-d H:i:s'),
            'duration' => $duration,
            'depends' => $depends,
            'sort_order' => $sortOrder,
            'start_is_milestone' => $isMilestone,
            'end_is_milestone' => $isMilestone,
            'created_by' => $adminId,
            'updated_by' => $adminId,
        ];

        $findTask->execute([
            'project_id' => $projectId,
            'code' => $code,
        ]);
        $taskId = $findTask->fetchColumn();

        if ($taskId === false) {
            $insertTask->execute($taskData);
            continue;
        }

        unset($taskData['project_id'], $taskData['code'], $taskData['created_by']);
        $taskData['id'] = (int)$taskId;
        $updateTask->execute($taskData);
    }
};
