<?php

namespace App\Filament\Concerns;

use App\Filament\Plugins\MonacoEditor\CustomMonacoEditor;
use Filament\Infolists\Components\ViewEntry;

trait HasMonacoSql
{
    // Shared Monaco configuration for SQL editing.

    protected static function sqlEditor(
        string $field,
        string $label,
        string $height = '350px',
        ?string $helperText = null,
        string $language = 'sql',
        string $theme = 'vs-dark',
    ): CustomMonacoEditor {
        $editor = CustomMonacoEditor::make($field)
            ->label($label)
            ->language($language)
            ->theme($theme)
            ->height($height)
            ->columnSpanFull();

        if ($helperText !== null) {
            $editor->helperText($helperText);
        }

        return $editor;
    }


    // Shared Monaco configuration for read-only SQL display in infolists.

    protected static function sqlViewer(
        string $field,
        string $label,
        string $height = '350px',
        ?string $helperText = null,
        string $language = 'sql',
        string $theme = 'vs-dark',
    ): ViewEntry {
        $viewer = ViewEntry::make($field)
            ->label($label)
            ->view('filament.infolists.entries.monaco-sql-viewer', [
                'language' => $language,
                'theme' => $theme,
                'height' => $height,
            ])
            ->columnSpanFull();

        if ($helperText !== null) {
            $viewer->helperText($helperText);
        }

        return $viewer;
    }
}
