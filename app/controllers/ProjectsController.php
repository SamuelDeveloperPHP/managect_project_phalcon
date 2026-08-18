<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\GanttTask;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMember;
use App\Models\User;
use Phalcon\Http\Request\FileInterface;
use Throwable;

final class ProjectsController extends ControllerBase
{
    protected bool $requiresAuthentication = true;
    protected bool $requiresAdmin = false;

    /** Extensões aceitas em anexos de projeto. Barreira principal contra upload de código executável (RCE). */
    private const ALLOWED_UPLOAD_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'zip',
    ];

    /** MIME types perigosos: rejeitados mesmo quando a extensão parece inofensiva. */
    private const BLOCKED_UPLOAD_MIMES = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml',
        'text/x-php', 'application/x-php', 'application/x-httpd-php',
        'application/x-httpd-php-source', 'text/x-python', 'text/x-perl',
        'application/x-sh', 'application/x-executable', 'application/x-msdownload',
        'application/x-dosexec',
    ];

    private const MAX_UPLOAD_BYTES = 10485760; // 10 MB

    /** Conteúdo do .htaccess que desativa execução de código na árvore de uploads (defesa em profundidade). */
    private const UPLOAD_HTACCESS = <<<'HTACCESS'
# Pasta de uploads: conteúdo enviado por usuários. NUNCA executar como código.
# Remove a associação de handler/execução de qualquer script conhecido.
RemoveHandler .php .phtml .phar .pht .php3 .php4 .php5 .php7 .phps .cgi .pl .py .asp .aspx .jsp .sh
RemoveType .php .phtml .phar .pht .php3 .php4 .php5 .php7 .phps
# Nega acesso HTTP a qualquer arquivo executável/perigoso (independe de mod_php).
<FilesMatch "(?i)\.(php[0-9]?|phtml|phar|pht|phps|cgi|pl|py|asp|aspx|jsp|sh|htaccess|htpasswd)$">
    Require all denied
</FilesMatch>
# Sem execução CGI e sem listagem de diretório.
Options -ExecCGI -Indexes
HTACCESS;

    public function indexAction(): void
    {
        $companyId = $this->currentCompanyId();
        $rows = Project::find([
            'conditions' => 'company_id = :company_id: AND deleted_at IS NULL',
            'bind' => ['company_id' => $companyId],
            'order' => 'id DESC',
        ]);

        $projects = [];
        foreach ($rows as $project) {
            $projects[] = $this->projectItem($project);
        }

        $this->view->setVars([
            'auth' => $this->session->get('auth'),
            'csrfToken' => $this->csrfToken(),
            'pageTitle' => 'Projetos',
            'projects' => $projects,
        ]);
    }

    public function createAction()
    {
        if (!$this->hasPermission('can_manage_projects')) {
            $this->flashSession->error('Você não possui permissão para criar projetos.');
            return $this->response->redirect('/projects');
        }

        $this->formView(new Project(), [], []);
    }

    public function editAction(int $id)
    {
        if (!$this->hasPermission('can_manage_projects')) {
            $this->flashSession->error('Você não possui permissão para editar projetos.');
            return $this->response->redirect('/projects');
        }

        $project = $this->findProject($id);
        $memberIds = $this->memberIds($id);
        $companyId = $this->currentCompanyId();

        $files = ProjectFile::find([
            'conditions' => 'project_id = :project_id: AND company_id = :company_id:',
            'bind' => ['project_id' => $id, 'company_id' => $companyId],
            'order' => 'id DESC',
        ]);

        $this->formView($project, $memberIds, iterator_to_array($files));
    }

    public function storeAction()
    {
        if (!$this->hasPermission('can_manage_projects')) {
            $this->flashSession->error('Sem permissão.');
            return $this->response->redirect('/projects');
        }

        return $this->saveProject(new Project(), true);
    }

    public function updateAction(int $id)
    {
        if (!$this->hasPermission('can_manage_projects')) {
            $this->flashSession->error('Sem permissão.');
            return $this->response->redirect('/projects');
        }

        return $this->saveProject($this->findProject($id), false);
    }

    public function deleteAction(int $id)
    {
        try {
            if (!$this->hasPermission('can_manage_projects')) {
                throw new \RuntimeException('Você não possui permissão para remover projetos.');
            }

            if (!$this->hasValidCsrfToken()) {
                throw new \RuntimeException('Token de segurança inválido.');
            }

            $project = $this->findProject($id);
            $project->deleted_at = date('Y-m-d H:i:s');

            $this->db->begin();
            if (!$project->save()) {
                throw new \RuntimeException('Não foi possível remover o projeto.');
            }
            $this->audit('project_deleted', 'projects', $id, 'Projeto removido', ['name' => $project->name]);
            $this->db->commit();

            $this->flashSession->success('Projeto removido.');
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            $this->logError('projects.delete', $e);
            $this->flashSession->error($e->getMessage());
        }

        return $this->response->redirect('/projects');
    }

    private function saveProject(Project $project, bool $creating)
    {
        try {
            if (!$this->hasValidCsrfToken()) {
                throw new \RuntimeException('Token de segurança inválido.');
            }

            $name = trim((string)$this->request->getPost('name'));
            if (mb_strlen($name) < 3) {
                throw new \RuntimeException('Informe um nome de projeto válido.');
            }

            $companyId = $this->currentCompanyId();
            $auth = $this->session->get('auth');
            $userId = is_array($auth) ? (int)$auth['id'] : null;
            $leaderId = (int)$this->request->getPost('leader_id');

            // Aceita apenas líder/responsáveis que pertençam à empresa atual (previne IDOR cross-tenant).
            $memberIds = array_values(array_unique(array_filter(array_map('intval', (array)$this->request->getPost('member_ids')))));
            if ($leaderId > 0) {
                $memberIds[] = $leaderId;
            }
            $memberIds = $this->filterCompanyUserIds($memberIds, $companyId);
            if ($leaderId > 0 && !in_array($leaderId, $memberIds, true)) {
                $leaderId = 0;
            }

            $project->assign([
                'company_id' => $companyId,
                'name' => $this->cleanText($name, 190),
                'code' => $this->cleanText($this->request->getPost('code'), 80),
                'client' => $this->cleanText($this->request->getPost('client'), 190),
                'description' => $this->cleanText($this->request->getPost('description'), 4000),
                'status' => $this->choice((string)$this->request->getPost('status'), ['planning', 'in_progress', 'completed', 'paused'], 'in_progress'),
                'priority' => $this->choice((string)$this->request->getPost('priority'), ['low', 'medium', 'high'], 'medium'),
                'leader_id' => $leaderId > 0 ? $leaderId : null,
                'start_date' => $this->dateValue($this->request->getPost('start_date')),
                'deadline' => $this->dateValue($this->request->getPost('deadline')),
                'budget' => $this->moneyValue($this->request->getPost('budget')),
                'updated_by' => $userId,
            ]);

            if ($creating) {
                $project->created_by = $userId;
            }

            $this->db->begin();
            if ($creating ? !$project->create() : !$project->save()) {
                throw new \RuntimeException('Não foi possível salvar o projeto.');
            }

            $projectId = (int)$project->id;
            $this->syncMembers($projectId, $memberIds);
            $this->saveUploads($project, $userId, $companyId);

            $this->audit($creating ? 'project_created' : 'project_updated', 'projects', $projectId, $creating ? 'Projeto criado' : 'Projeto atualizado', [
                'name' => $project->name,
                'members' => count($memberIds),
            ]);

            $this->db->commit();
            $this->flashSession->success($creating ? 'Projeto criado com sucesso.' : 'Projeto atualizado.');

            return $this->response->redirect('/projects');
        } catch (Throwable $e) {
            if ($this->db->isUnderTransaction()) {
                $this->db->rollback();
            }
            $this->logError($creating ? 'projects.create' : 'projects.update', $e);
            $this->flashSession->error($e->getMessage());

            return $this->response->redirect($creating ? '/projects/create' : '/projects/' . (int)$project->id . '/edit');
        }
    }

    private function formView(Project $project, array $memberIds, array $files): void
    {
        $companyId = $this->currentCompanyId();
        $users = User::find([
            'conditions' => 'company_id = :company_id: AND deleted_at IS NULL AND is_active = 1',
            'bind' => ['company_id' => $companyId],
            'order' => 'name ASC',
        ]);

        $this->view->pick('projects/form');
        $this->view->setVars([
            'auth' => $this->session->get('auth'),
            'csrfToken' => $this->csrfToken(),
            'pageTitle' => ((int)$project->id > 0 ? 'Editar projeto' : 'Novo projeto'),
            'project' => $project,
            'users' => iterator_to_array($users),
            'memberIds' => $memberIds,
            'files' => $files,
        ]);
    }

    private function projectItem(Project $project): array
    {
        $projectId = (int)$project->id;
        $companyId = $this->currentCompanyId();

        $tasksTotal = (int)GanttTask::count([
            'conditions' => 'project_id = :project_id: AND company_id = :company_id:',
            'bind' => ['project_id' => $projectId, 'company_id' => $companyId],
        ]);
        $tasksDone = (int)GanttTask::count([
            'conditions' => 'project_id = :project_id: AND company_id = :company_id: AND progress >= 100',
            'bind' => ['project_id' => $projectId, 'company_id' => $companyId],
        ]);
        $progress = 0;
        if ($tasksTotal > 0) {
            $row = $this->db->fetchOne(
                'SELECT AVG(progress) AS progress FROM gantt_tasks WHERE project_id = ? AND company_id = ?',
                \Phalcon\Db\Enum::FETCH_ASSOC,
                [$projectId, $companyId]
            );
            $progress = (int)round((float)($row['progress'] ?? 0));
        }

        return [
            'model' => $project,
            'leader' => $this->userName($project->leader_id ? (int)$project->leader_id : null),
            'members' => $this->members($projectId),
            'files_count' => (int)ProjectFile::count(['conditions' => 'project_id = :project_id: AND company_id = :company_id:', 'bind' => ['project_id' => $projectId, 'company_id' => $companyId]]),
            'tasks_done' => $tasksDone,
            'tasks_total' => $tasksTotal,
            'progress' => max(0, min(100, $progress)),
        ];
    }

    private function syncMembers(int $projectId, array $memberIds): void
    {
        $this->db->execute('DELETE FROM project_members WHERE project_id = ?', [$projectId]);
        foreach ($memberIds as $userId) {
            if ($userId <= 0) {
                continue;
            }
            $member = new ProjectMember();
            $member->assign(['project_id' => $projectId, 'user_id' => $userId]);
            if (!$member->create()) {
                throw new \RuntimeException('Não foi possível salvar responsáveis do projeto.');
            }
        }
    }

    private function saveUploads(Project $project, ?int $userId, int $companyId): void
    {
        if (!$this->request->hasFiles(true)) {
            return;
        }

        $projectId = (int)$project->id;
        $basePath = dirname(__DIR__, 2) . '/public/uploads/projects/' . $projectId;
        if (!is_dir($basePath) && !mkdir($basePath, 0775, true) && !is_dir($basePath)) {
            throw new \RuntimeException('Não foi possível preparar a pasta de anexos.');
        }

        $this->ensureUploadGuard();

        foreach ($this->request->getUploadedFiles(true) as $file) {
            if (!$file instanceof FileInterface || $file->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            $original = $this->cleanFileName($file->getName());
            if ($original === '') {
                continue;
            }

            $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
            if (!in_array($extension, self::ALLOWED_UPLOAD_EXTENSIONS, true)) {
                throw new \RuntimeException('Tipo de arquivo não permitido (.' . $extension . '). Envie imagem, PDF, Office, TXT, CSV ou ZIP.');
            }

            if ((int)$file->getSize() > self::MAX_UPLOAD_BYTES) {
                throw new \RuntimeException('O arquivo "' . $original . '" excede o limite de 10 MB.');
            }

            $detectedMime = $this->detectMime((string)$file->getTempName());
            if ($detectedMime !== '' && in_array($detectedMime, self::BLOCKED_UPLOAD_MIMES, true)) {
                throw new \RuntimeException('Conteúdo não permitido no arquivo "' . $original . '".');
            }

            $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
            $target = $basePath . '/' . $storedName;
            $publicPath = '/uploads/projects/' . $projectId . '/' . $storedName;

            if (!$file->moveTo($target)) {
                throw new \RuntimeException('Não foi possível enviar o arquivo ' . $original . '.');
            }

            if ($file->getKey() === 'project_image') {
                $project->image_path = $publicPath;
                if (!$project->save()) {
                    throw new \RuntimeException('Não foi possível salvar a imagem do projeto.');
                }
                continue;
            }

            $attachment = new ProjectFile();
            $attachment->assign([
                'project_id' => $projectId,
                'company_id' => $companyId,
                'original_name' => $original,
                'stored_name' => $storedName,
                'file_path' => $publicPath,
                'mime_type' => $this->cleanText($file->getType(), 120),
                'file_size' => (int)$file->getSize(),
                'uploaded_by' => $userId,
            ]);
            if (!$attachment->create()) {
                throw new \RuntimeException('Não foi possível registrar um anexo.');
            }
        }
    }

    private function findProject(int $id): Project
    {
        $companyId = $this->currentCompanyId();
        $project = Project::findFirst([
            'conditions' => 'id = :id: AND company_id = :company_id: AND deleted_at IS NULL',
            'bind' => ['id' => $id, 'company_id' => $companyId],
        ]);

        if (!$project instanceof Project) {
            throw new \RuntimeException('Projeto não encontrado.');
        }

        return $project;
    }

    private function memberIds(int $projectId): array
    {
        $rows = ProjectMember::find(['conditions' => 'project_id = :project_id:', 'bind' => ['project_id' => $projectId]]);
        $ids = [];
        foreach ($rows as $row) {
            $ids[] = (int)$row->user_id;
        }
        return $ids;
    }

    /** Mantém apenas IDs de usuários ativos que pertencem à empresa informada. */
    private function filterCompanyUserIds(array $ids, int $companyId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn (int $id): bool => $id > 0
        )));

        if ($ids === []) {
            return [];
        }

        $rows = User::find([
            'conditions' => 'company_id = :cid: AND deleted_at IS NULL AND id IN ({ids:array})',
            'bind' => ['cid' => $companyId, 'ids' => $ids],
        ]);

        $valid = [];
        foreach ($rows as $user) {
            $valid[] = (int) $user->id;
        }

        return $valid;
    }

    private function members(int $projectId): array
    {
        $ids = $this->memberIds($projectId);
        $names = [];
        foreach ($ids as $id) {
            $name = $this->userName($id);
            if ($name !== null) {
                $names[] = $name;
            }
        }
        return $names;
    }

    private function userName(?int $id): ?string
    {
        if ($id === null || $id <= 0) {
            return null;
        }
        $companyId = $this->currentCompanyId();
        $user = User::findFirst(['conditions' => 'id = :id: AND company_id = :company_id:', 'bind' => ['id' => $id, 'company_id' => $companyId]]);
        return $user instanceof User ? (string)$user->name : null;
    }

    private function cleanText(mixed $value, int $limit): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    private function cleanFileName(string $name): string
    {
        $name = preg_replace('/[^A-Za-z0-9._ -]/', '', basename($name));
        return trim((string)$name);
    }

    /** Garante o .htaccess que impede execução de código na árvore de uploads. */
    private function ensureUploadGuard(): void
    {
        $uploadsRoot = dirname(__DIR__, 2) . '/public/uploads';
        $guard = $uploadsRoot . '/.htaccess';

        if (is_file($guard)) {
            return;
        }

        if (!is_dir($uploadsRoot)) {
            @mkdir($uploadsRoot, 0775, true);
        }

        @file_put_contents($guard, self::UPLOAD_HTACCESS);
    }

    /** Detecta o MIME real pelo conteúdo (magic bytes), independente do que o cliente informou. */
    private function detectMime(string $path): string
    {
        if ($path === '' || !is_readable($path) || !function_exists('finfo_open')) {
            return '';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return '';
        }

        $mime = (string) finfo_file($finfo, $path);
        finfo_close($finfo);

        return strtolower($mime);
    }

    private function dateValue(mixed $value): ?string
    {
        $text = trim((string)($value ?? ''));
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $text) ? $text : null;
    }

    private function moneyValue(mixed $value): ?float
    {
        $text = str_replace(',', '.', trim((string)($value ?? '')));
        return is_numeric($text) ? max(0, (float)$text) : null;
    }

    private function choice(string $value, array $allowed, string $fallback): string
    {
        return in_array($value, $allowed, true) ? $value : $fallback;
    }
}
