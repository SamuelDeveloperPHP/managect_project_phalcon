<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\ReleaseVersion;

final class VersionsController extends ControllerBase
{
    protected bool $requiresAuthentication = true;
    protected bool $requiresAdmin = true;

    public function indexAction(): void
    {
        $records = ReleaseVersion::find([
            'order' => 'released_at DESC, id DESC',
            'limit' => 100,
        ]);

        $versions = [];
        foreach ($records as $record) {
            $notes = [
                'implemented' => $this->notes($record->implemented_notes),
                'fixed' => $this->notes($record->fixed_notes),
                'updated' => $this->notes($record->updated_notes),
            ];

            if ($notes['implemented'] === [] && $notes['fixed'] === [] && $notes['updated'] === []) {
                $notes['updated'] = [(string) ($record->commit_message ?: 'Publicação de versão registrada.')];
            }

            $versions[] = [
                'model' => $record,
                'notes' => $notes,
            ];
        }

        $this->view->setVars([
            'auth' => $this->session->get('auth'),
            'csrfToken' => $this->csrfToken(),
            'pageTitle' => 'Versões publicadas',
            'versions' => $versions,
        ]);
    }

    private function notes(mixed $value): array
    {
        $decoded = json_decode((string) $value, true);
        if (!is_array($decoded)) {
            return [];
        }

        $notes = [];
        foreach ($decoded as $note) {
            $text = trim((string) $note);
            if ($text !== '') {
                $notes[] = $text;
            }
        }

        return $notes;
    }
}
