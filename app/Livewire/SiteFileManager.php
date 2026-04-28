<?php

namespace App\Livewire;

use App\Models\Site;
use App\Services\Files\SiteFileManagerService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Throwable;

class SiteFileManager extends Component
{
    public Site $record;

    public string $currentDirectory = '';

    public string $selectedFile = '';

    public string $editorContents = '';

    public bool $loading = false;

    public function mount(Site $site): void
    {
        $this->record = $site;
        $this->currentDirectory = '';
        $this->refreshBrowser();
    }

    public function refreshBrowser(): void
    {
        $this->loading = true;

        try {
            $this->fileManager()->browse($this->record->fresh(['server']), $this->currentDirectory);
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to load files')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->loading = false;
        }
    }

    public function navigate(string $relativePath = ''): void
    {
        $this->currentDirectory = $relativePath;
        $this->selectedFile = '';
        $this->editorContents = '';
        $this->refreshBrowser();
    }

    public function openItem(string $relativePath, string $type = 'file'): void
    {
        if ($type === 'directory') {
            $this->navigate($relativePath);

            return;
        }

        $this->loading = true;

        try {
            $file = $this->fileManager()->read($this->record->fresh(['server']), $relativePath);
            $this->selectedFile = $relativePath;
            $directory = str_replace('\\', '/', dirname($relativePath));
            $this->currentDirectory = $directory === '.' ? '' : $directory;
            $this->editorContents = (string) ($file['contents'] ?? '');
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to open file')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->loading = false;
        }
    }

    public function saveFile(): void
    {
        if (blank($this->selectedFile)) {
            Notification::make()
                ->title('Choose a file first')
                ->body('Open a file from the browser before saving it.')
                ->warning()
                ->send();

            return;
        }

        try {
            $this->fileManager()->save($this->record->fresh(['server']), $this->selectedFile, $this->editorContents);

            Notification::make()
                ->title('File saved')
                ->body(sprintf('%s was updated successfully.', $this->selectedFile))
                ->success()
                ->send();
        } catch (Throwable $throwable) {
            Notification::make()
                ->title('Unable to save file')
                ->body($throwable->getMessage())
                ->danger()
                ->send();
        }
    }

    public function render(): View
    {
        $browser = $this->fileManager()->browse($this->record->fresh(['server']), $this->currentDirectory);

        return view('livewire.site-file-manager', [
            'site' => $this->record,
            'browser' => $browser,
            'breadcrumbs' => $browser['breadcrumbs'] ?? [],
            'items' => $browser['items'] ?? [],
            'rootPath' => $browser['root_path'] ?? $this->record->current_release_path ?? $this->record->deploy_path,
        ]);
    }

    protected function fileManager(): SiteFileManagerService
    {
        return app(SiteFileManagerService::class);
    }
}
