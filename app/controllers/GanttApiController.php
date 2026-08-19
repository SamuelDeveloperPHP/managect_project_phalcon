<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GanttTask;
use App\Models\Project;
use Phalcon\Mvc\Dispatcher;
use Throwable;

final class GanttApiController extends ControllerBase
{
    protected bool $requiresAuthentication = true;
    protected bool $requiresAdmin = false;

    private const MAX_PAYLOAD_BYTES = 1048576; // 1 MB
    private const MAX_TASKS = 500;
    private const MAX_DURATION_DAYS = 3650;
    private const MAX_LEVEL = 20;

    public function beforeExecuteRoute(Dispatcher $dispatcher): bool
    {
        $this->view->disable();
        return parent::beforeExecuteRoute($dispatcher);
    }

    public function indexAction(int $id)
    {
        try {
            $project = $this->findProject($id);
            return $this->ok(['project' => $this->project($project)]);
        } catch (Throwable $e) {
            return $this->fail('gantt.load', $e);
        }
    }

    public function saveAction(int $id)
    {
        try {
            $project = $this->findProject($id);

            if (!$this->hasPermission('can_manage_projects')) {
                throw new \RuntimeException('Você não possui permissão para alterar o cronograma de projetos.');
            }

            if (!$this->hasValidCsrfToken()) {
                throw new \RuntimeException('Token de segurança inválido.');
            }

            $payload = $this->payload();
            $tasks = $payload['tasks'] ?? [];

            if (!is_array($tasks) || count($tasks) === 0) {
                throw new \RuntimeException('Inclua ao menos uma tarefa no Gantt.');
            }

            if (count($tasks) > self::MAX_TASKS) {
                throw new \RuntimeException('O cronograma excede o limite de ' . self::MAX_TASKS . ' tarefas.');
            }

            $companyId = (int)$project->company_id;
            $auth = $this->session->get('auth');
            $userId = is_array($auth) ? (int)$auth['id'] : null;
            $savedTasks = 0;

            $this->db->begin();
            $this->db->execute('DELETE FROM gantt_tasks WHERE project_id = ? AND company_id = ?', [$id, $companyId]);

            foreach (array_values($tasks) as $index => $task) {
                if (!is_array($task)) {
                    throw new \RuntimeException('Uma tarefa do Gantt possui formato inválido.');
                }

                $this->saveTask($id, $companyId, $task, $index, $userId);
                $savedTasks++;
            }

            $this->audit('gantt_saved', 'gantt_tasks', null, 'Cronograma Gantt atualizado', [
                'project_id' => $id,
                'tasks' => $savedTasks,
            ]);

            $this->db->commit();

            return $this->ok([
                'project' => $this->project($project),
                'message' => 'Cronograma salvo com sucesso.',
            ]);
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }

            return $this->fail('gantt.save', $e, 422);
        }
    }

    private function payload(): array
    {
        $rawBody = (string)$this->request->getRawBody();
        if ($rawBody === '') {
            throw new \RuntimeException('Payload JSON vazio.');
        }

        if (strlen($rawBody) > self::MAX_PAYLOAD_BYTES) {
            throw new \RuntimeException('Payload do Gantt excede o limite permitido.');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new \RuntimeException('Payload JSON inválido.');
        }

        return $payload;
    }

    private function saveTask(int $projectId, int $companyId, array $data, int $index, ?int $userId): void
    {
        $name = trim((string)($data['name'] ?? ''));

        if ($name === '') {
            $name = 'Nova tarefa ' . ($index + 1);
        }

        $start = $this->millisToDateTime((int)($data['start'] ?? 0));
        $end = $this->millisToDateTime((int)($data['end'] ?? 0));

        if ($start === null || $end === null) {
            throw new \RuntimeException('Toda tarefa precisa ter data inicial e final válidas.');
        }

        $startTimestamp = strtotime($start);
        $endTimestamp = strtotime($end);
        if ($startTimestamp === false || $endTimestamp === false || $endTimestamp < $startTimestamp) {
            throw new \RuntimeException('A data final da tarefa não pode ser anterior à data inicial.');
        }

        $progress = max(0, min(100, (int)($data['progress'] ?? 0)));
        $duration = max(1, min(self::MAX_DURATION_DAYS, (int)($data['duration'] ?? 1)));

        $task = new GanttTask();
        $task->assign([
            'project_id' => $projectId,
            'company_id' => $companyId,
            'code' => $this->cleanText($data['code'] ?? null, 80),
            'name' => $this->cleanText($name, 190),
            'description' => $this->cleanText($data['description'] ?? null, 4000),
            'level' => max(0, min(self::MAX_LEVEL, (int)($data['level'] ?? 0))),
            'status' => $this->statusValue($data['status'] ?? 'STATUS_ACTIVE'),
            'progress' => $progress,
            'start_at' => $start,
            'end_at' => $end,
            'duration' => $duration,
            'depends' => $this->cleanText($data['depends'] ?? '', 255) ?? '',
            'sort_order' => $index,
            'collapsed' => !empty($data['collapsed']) ? 1 : 0,
            'start_is_milestone' => !empty($data['startIsMilestone']) ? 1 : 0,
            'end_is_milestone' => !empty($data['endIsMilestone']) ? 1 : 0,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        if (!$task->create()) {
            throw new \RuntimeException('Não foi possível salvar uma tarefa do Gantt.');
        }
    }

    private function project(Project $project): array
    {
        $projectId = (int)$project->id;
        $companyId = (int)$project->company_id;
        $rows = GanttTask::find([
            'conditions' => 'project_id = :project_id: AND company_id = :company_id:',
            'bind' => ['project_id' => $projectId, 'company_id' => $companyId],
            'order' => 'sort_order ASC, id ASC',
        ]);
        $tasks = [];

        foreach ($rows as $task) {
            $tasks[] = $this->item($task);
        }

        if ($tasks === []) {
            $tasks = $this->defaultTasks();
        }

        return [
            'tasks' => $tasks,
            'selectedRow' => 0,
            'deletedTaskIds' => [],
            'resources' => [
                ['id' => 'tmp_1', 'name' => 'Desenvolvedor'],
                ['id' => 'tmp_2', 'name' => 'Gerente de Projeto'],
                ['id' => 'tmp_3', 'name' => 'Analista de QA'],
                ['id' => 'tmp_4', 'name' => 'Designer UX/UI'],
            ],
            'roles' => [
                ['id' => 'tmp_r1', 'name' => 'Responsável'],
                ['id' => 'tmp_r2', 'name' => 'Apoiador'],
                ['id' => 'tmp_r3', 'name' => 'Revisor'],
            ],
            'canWrite' => $this->hasPermission('can_manage_projects'),
            'canAdd' => $this->hasPermission('can_manage_projects'),
            'canWriteOnParent' => $this->hasPermission('can_manage_projects'),
            'canDelete' => $this->hasPermission('can_manage_projects'),
            'canSeeCriticalPath' => true,
            'canAddIssue' => false,
            'cannotCloseTaskIfIssueOpen' => false,
            'zoom' => 'w3',
        ];
    }

    private function item(GanttTask $task): array
    {
        $statusColors = [
            'STATUS_ACTIVE'    => '#3aaf85',
            'STATUS_DONE'      => '#6EBEF4',
            'STATUS_FAILED'    => '#763A96',
            'STATUS_SUSPENDED' => '#f9c154',
            'STATUS_WAITING'   => '#F79136',
            'STATUS_UNDEFINED' => '#dededf',
        ];

        $status = (string)$task->status;
        $color  = $statusColors[$status] ?? '#3aaf85';

        return [
            'id'                => (int)$task->id,
            'name'              => (string)$task->name,
            'progress'          => (int)$task->progress,
            'progressByWorklog' => false,
            'relevance'         => 0,
            'type'              => '',
            'typeId'            => '',
            'description'       => (string)($task->description ?? ''),
            'code'              => (string)($task->code ?? ''),
            'level'             => (int)$task->level,
            'status'            => $status,
            'color'             => $color,
            'depends'           => (string)$task->depends,
            'canWrite'          => $this->hasPermission('can_manage_projects'),
            'canAdd'            => $this->hasPermission('can_manage_projects'),
            'canDelete'         => $this->hasPermission('can_manage_projects'),
            'start'             => $this->dateTimeToMillis((string)$task->start_at, false),
            'duration'          => (int)$task->duration,
            'end'               => $this->dateTimeToMillis((string)$task->end_at, true),
            'startIsMilestone'  => (bool)$task->start_is_milestone,
            'endIsMilestone'    => (bool)$task->end_is_milestone,
            'collapsed'         => (bool)$task->collapsed,
            'assigs'            => [],
            'hasChild'          => false,
        ];
    }

    private function defaultTasks(): array
    {
        $start = strtotime('today') * 1000;
        $end = (strtotime('+4 days 23:59:59') * 1000) + 999;

        return [[
            'id' => -1,
            'name' => 'Projeto inicial',
            'progress' => 0,
            'progressByWorklog' => false,
            'relevance' => 0,
            'type' => '',
            'typeId' => '',
            'description' => 'Primeira tarefa do cronograma.',
            'code' => 'PRJ-001',
            'level' => 0,
            'status' => 'STATUS_ACTIVE',
            'depends' => '',
            'canWrite' => $this->hasPermission('can_manage_projects'),
            'canAdd' => $this->hasPermission('can_manage_projects'),
            'canDelete' => $this->hasPermission('can_manage_projects'),
            'start' => $start,
            'duration' => 5,
            'end' => $end,
            'startIsMilestone' => false,
            'endIsMilestone' => false,
            'collapsed' => false,
            'assigs' => [],
            'hasChild' => false,
        ]];
    }

    private function findProject(int $id): Project
    {
        if ($this->isMasterUser()) {
            $project = Project::findFirst([
                'conditions' => 'id = :id: AND deleted_at IS NULL',
                'bind' => ['id' => $id],
            ]);
        } else {
            $companyId = $this->currentCompanyId();
            $project = Project::findFirst([
                'conditions' => 'id = :id: AND company_id = :company_id: AND deleted_at IS NULL',
                'bind' => ['id' => $id, 'company_id' => $companyId],
            ]);
        }

        if (!$project instanceof Project) {
            throw new \RuntimeException('Projeto não encontrado.');
        }

        $this->requireCompanyAccess((int)$project->company_id);

        return $project;
    }

    private function millisToDateTime(int $millis): ?string
    {
        if ($millis <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', (int)floor($millis / 1000));
    }

    private function dateTimeToMillis(string $dateTime, bool $endOfDay): int
    {
        $timestamp = strtotime($dateTime);

        if ($timestamp === false) {
            $timestamp = time();
        }

        $millis = $timestamp * 1000;

        return $endOfDay ? $millis + 999 : $millis;
    }

    private function cleanText(mixed $value, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string)$value);

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $limit);
    }

    private function statusValue(mixed $value): string
    {
        $status = (string)$value;
        $allowed = [
            'STATUS_ACTIVE',
            'STATUS_DONE',
            'STATUS_FAILED',
            'STATUS_SUSPENDED',
            'STATUS_WAITING',
            'STATUS_UNDEFINED',
        ];

        return in_array($status, $allowed, true) ? $status : 'STATUS_ACTIVE';
    }

    private function ok(array $data, int $status = 200)
    {
        return $this->response->setStatusCode($status)->setJsonContent(['success' => true] + $data);
    }

    private function fail(string $context, Throwable $e, int $status = 500)
    {
        $this->logError($context, $e);

        return $this->response
            ->setStatusCode($status)
            ->setJsonContent([
                'success' => false,
                'message' => $status === 500 ? 'Ocorreu um erro interno.' : $e->getMessage(),
            ]);
    }
}
