<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GanttTask;
use App\Models\Project;

/**
 * Painel analítico de um único projeto. Todos os agregados são calculados
 * a partir das tarefas já gravadas para o mesmo par empresa/projeto.
 */
final class ProjectOverviewController extends ControllerBase
{
    protected bool $requiresAuthentication = true;
    protected bool $requiresAdmin = false;

    public function projectAction(int $id)
    {
        $project = $this->findProject($id);
        $companyId = (int) $project->company_id;

        $rows = GanttTask::find([
            'conditions' => 'project_id = :project_id: AND company_id = :company_id:',
            'bind' => ['project_id' => $id, 'company_id' => $companyId],
            'order' => 'sort_order ASC, id ASC',
        ]);

        $tasks = iterator_to_array($rows);
        $summary = $this->summary($tasks);

        $this->view->setVars([
            'auth' => $this->session->get('auth'),
            'csrfToken' => $this->csrfToken(),
            'pageTitle' => 'Visão geral - ' . (string) $project->name,
            'project' => $project,
            'summary' => $summary,
        ]);
        $this->view->pick('projectOverview/project');
    }

    private function findProject(int $id): Project
    {
        if ($this->isMasterUser()) {
            $project = Project::findFirst([
                'conditions' => 'id = :id: AND deleted_at IS NULL',
                'bind' => ['id' => $id],
            ]);
        } else {
            $project = Project::findFirst([
                'conditions' => 'id = :id: AND company_id = :company_id: AND deleted_at IS NULL',
                'bind' => ['id' => $id, 'company_id' => $this->currentCompanyId()],
            ]);
        }

        if (!$project instanceof Project) {
            throw new \RuntimeException('Projeto não encontrado.');
        }

        $this->requireCompanyAccess((int) $project->company_id);

        return $project;
    }

    /** @param array<int, GanttTask> $tasks */
    private function summary(array $tasks): array
    {
        $statusLabels = [
            'STATUS_DONE' => 'Concluídas',
            'STATUS_ACTIVE' => 'Em andamento',
            'STATUS_WAITING' => 'Aguardando',
            'STATUS_SUSPENDED' => 'Suspensas',
            'STATUS_FAILED' => 'Com impedimento',
            'STATUS_UNDEFINED' => 'Não definidas',
        ];

        $leaves = $this->leafTasks($tasks);
        $statusCounts = array_fill_keys(array_keys($statusLabels), 0);
        $today = new \DateTimeImmutable('today');
        $nextWeek = $today->modify('+7 days')->setTime(23, 59, 59);
        $progressTotal = 0;
        $late = 0;
        $dueSoon = 0;
        $starts = [];
        $ends = [];

        foreach ($leaves as $task) {
            $status = (string) $task->status;
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
            $progressTotal += max(0, min(100, (int) $task->progress));

            $end = new \DateTimeImmutable((string) $task->end_at);
            $start = new \DateTimeImmutable((string) $task->start_at);
            $starts[] = $start;
            $ends[] = $end;

            if ((int) $task->progress < 100 && $end < $today) {
                $late++;
            }

            if ((int) $task->progress < 100 && $end >= $today && $end <= $nextWeek) {
                $dueSoon++;
            }
        }

        $total = count($leaves);
        $completed = $statusCounts['STATUS_DONE'];
        $progress = $total > 0 ? (int) round($progressTotal / $total) : 0;
        $upcoming = array_values(array_filter($leaves, static function (GanttTask $task) use ($today): bool {
            return (int) $task->progress < 100
                && new \DateTimeImmutable((string) $task->end_at) >= $today;
        }));
        usort($upcoming, static fn (GanttTask $a, GanttTask $b): int => strcmp((string) $a->end_at, (string) $b->end_at));

        return [
            'total' => $total,
            'completed' => $completed,
            'progress' => $progress,
            'late' => $late,
            'due_soon' => $dueSoon,
            'status_counts' => $statusCounts,
            'status_labels' => $statusLabels,
            'phases' => $this->phases($tasks, $leaves),
            'upcoming' => array_slice($upcoming, 0, 8),
            'tasks' => $leaves,
            'starts_at' => $starts === [] ? null : min($starts),
            'ends_at' => $ends === [] ? null : max($ends),
        ];
    }

    /** @param array<int, GanttTask> $tasks
     *  @return array<int, GanttTask> */
    private function leafTasks(array $tasks): array
    {
        $leaves = [];
        $total = count($tasks);

        foreach ($tasks as $index => $task) {
            $hasChild = isset($tasks[$index + 1])
                && (int) $tasks[$index + 1]->level > (int) $task->level;
            if (!$hasChild || $index === $total - 1) {
                $leaves[] = $task;
            }
        }

        return $leaves;
    }

    /** @param array<int, GanttTask> $tasks
     *  @param array<int, GanttTask> $leaves */
    private function phases(array $tasks, array $leaves): array
    {
        $leafIds = [];
        foreach ($leaves as $leaf) {
            $leafIds[(int) $leaf->id] = true;
        }

        $phases = [];
        foreach ($tasks as $index => $task) {
            if ((int) $task->level !== 0) {
                continue;
            }

            $children = [];
            for ($cursor = $index + 1; isset($tasks[$cursor]) && (int) $tasks[$cursor]->level > 0; $cursor++) {
                if (isset($leafIds[(int) $tasks[$cursor]->id])) {
                    $children[] = $tasks[$cursor];
                }
            }

            if ($children === [] && isset($leafIds[(int) $task->id])) {
                $children[] = $task;
            }

            $count = count($children);
            $done = count(array_filter($children, static fn (GanttTask $child): bool => (int) $child->progress >= 100));
            $average = $count > 0
                ? (int) round(array_sum(array_map(static fn (GanttTask $child): int => (int) $child->progress, $children)) / $count)
                : 0;

            $phases[] = [
                'code' => (string) ($task->code ?: '—'),
                'name' => (string) $task->name,
                'total' => $count,
                'done' => $done,
                'progress' => $average,
            ];
        }

        return $phases;
    }
}
