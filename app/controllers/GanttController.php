<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Project;

final class GanttController extends ControllerBase
{
    protected bool $requiresAuthentication = true;
    protected bool $requiresAdmin = false;

    public function indexAction()
    {
        return $this->response->redirect('/projects');
    }

    public function projectAction(int $id)
    {
        $companyId = $this->currentCompanyId();
        $project = Project::findFirst([
            'conditions' => 'id = :id: AND company_id = :company_id: AND deleted_at IS NULL',
            'bind' => ['id' => $id, 'company_id' => $companyId],
        ]);

        if (!$project instanceof Project) {
            $this->flashSession->error('Projeto não encontrado.');
            return $this->response->redirect('/projects');
        }

        $this->view->setVars([
            'auth' => $this->session->get('auth'),
            'csrfToken' => $this->csrfToken(),
            'pageTitle' => 'Gantt - ' . (string)$project->name,
            'project' => $project,
            'ganttTemplates' => $this->loadGanttTemplates(),
        ]);
    }

    private function loadGanttTemplates(): string
    {
        $source = dirname(__DIR__, 2) . '/jQueryGantt-master/gantt.html';
        $html = is_file($source) ? (string)file_get_contents($source) : '';
        $start = strpos($html, '<div id="gantEditorTemplates"');
        $end = strpos($html, '$(document).on("change", "#load-file"', $start ?: 0);

        if ($html === '' || $start === false || $end === false) {
            return '<div id="gantEditorTemplates" style="display:none;"></div>';
        }

        $templates = substr($html, $start, $end - $start);
        $templates .= "\n</script>";
        $templates = $this->localizeTemplatesPtBr($templates);

        return str_replace('src="res/', 'src="/assets/jquery-gantt/res/', $templates);
    }

    private function localizeTemplatesPtBr(string $templates): string
    {
        return strtr($templates, [
            // Toolbar buttons
            'title="undo"'             => 'title="Desfazer" aria-label="Desfazer"',
            'title="redo"'             => 'title="Refazer" aria-label="Refazer"',
            'title="insert above"'     => 'title="Inserir tarefa acima" aria-label="Inserir tarefa acima"',
            'title="insert below"'     => 'title="Inserir tarefa abaixo" aria-label="Inserir tarefa abaixo"',
            'title="un-indent task"'   => 'title="Recuar tarefa" aria-label="Recuar tarefa"',
            'title="indent task"'      => 'title="Avançar tarefa" aria-label="Avançar tarefa"',
            'title="move up"'          => 'title="Mover para cima" aria-label="Mover para cima"',
            'title="move down"'        => 'title="Mover para baixo" aria-label="Mover para baixo"',
            'title="Elimina"'          => 'title="Excluir" aria-label="Excluir"',
            'title="EXPAND_ALL"'       => 'title="Expandir todas" aria-label="Expandir todas"',
            'title="COLLAPSE_ALL"'     => 'title="Recolher todas" aria-label="Recolher todas"',
            'title="zoom out"'         => 'title="Reduzir escala" aria-label="Reduzir escala"',
            'title="zoom in"'          => 'title="Ampliar escala" aria-label="Ampliar escala"',
            'title="Print"'            => 'title="Imprimir" aria-label="Imprimir"',
            'title="CRITICAL_PATH"'    => 'title="Caminho crítico" aria-label="Caminho crítico"',
            'title="FULLSCREEN"'       => 'title="Tela cheia" aria-label="Tela cheia"',
            'title="edit resources"'   => 'title="Editar recursos" aria-label="Editar recursos"',
            'title="Save">Save</button>' => 'title="Salvar">Salvar</button>',
            '>Load</label>'            => '>Carregar</label>',
            '<em>clear project</em>'   => '<em>Limpar projeto</em>',

            // Column headers (keeping original widths from gantt.html)
            '>code/short name</th>'    => '>Código</th>',
            '>name</th>'               => '>Tarefa</th>',
            '>start</th>'              => '>Início</th>',
            '>End</th>'                => '>Fim</th>',
            '>dur.</th>'               => '>Dur.</th>',
            '>depe.</th>'              => '>Dep.</th>',
            '>assignees</th>'          => '>Responsáveis</th>',

            // Milestone labels
            'title="Start date is a milestone."' => 'title="Data de início é um marco."',
            'title="End date is a milestone."'   => 'title="Data de término é um marco."',

            // Row placeholders
            'placeholder="code/short name"' => 'placeholder="Código"',
            'placeholder="name"'            => 'placeholder="Tarefa"',

            // Status labels
            'title="Active"'    => 'title="Ativa"',
            'title="Completed"' => 'title="Concluída"',
            'title="Failed"'    => 'title="Falhou"',
            'title="Suspended"' => 'title="Suspensa"',
            'title="Waiting"'   => 'title="Aguardando"',
            'title="Undefined"' => 'title="Não definida"',

            // Task editor modal
            '>Task editor</h2>'                          => '>Editar tarefa</h2>',
            '<label for="code">code/short name</label>'  => '<label for="code">Código</label>',
            '<label for="name" class="required">name</label>' => '<label for="name" class="required">Tarefa</label>',
            '<label for="start">start</label>'           => '<label for="start">Início</label>',
            '<label for="startIsMilestone">is milestone</label>' => '<label for="startIsMilestone">é marco</label>',
            'title="calendar"'                           => 'title="Calendário"',
            '<label for="end">End</label>'               => '<label for="end">Fim</label>',
            '<label for="endIsMilestone">is milestone</label>'   => '<label for="endIsMilestone">é marco</label>',
            '<label for="duration" class=" ">Days</label>'       => '<label for="duration" class=" ">Dias</label>',
            'title="Duration is in working days."'       => 'title="Duração em dias úteis."',
            '<label for="status" class=" ">status</label>'       => '<label for="status" class=" ">Status</label>',
            '>active</option>'      => '>Ativa</option>',
            '>suspended</option>'   => '>Suspensa</option>',
            '>completed</option>'   => '>Concluída</option>',
            '>failed</option>'      => '>Falhou</option>',
            '>undefined</option>'   => '>Não definida</option>',
            '<label>progress</label>'                    => '<label>Progresso</label>',
            '<label for="description">Description</label>' => '<label for="description">Descrição</label>',

            // Assignments section
            '<h2>Assignments</h2>'  => '<h2>Responsáveis</h2>',
            '>Role</th>'            => '>Função</th>',
            '>est.wklg.</th>'       => '>Trab. Est.</th>',
            '>Save</span>'          => '>Salvar</span>',
            '<h2>Project team</h2>' => '<h2>Equipe do projeto</h2>',
            '>Save</button>'        => '>Salvar</button>',
        ]);
    }
}
