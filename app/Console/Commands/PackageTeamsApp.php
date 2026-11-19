<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Builds the Microsoft Teams app package — the zip an administrator uploads
 * in the Teams admin centre to publish the tab to the organisation.
 *
 * The manifest has to carry the live host and the Entra application ID, so it
 * is generated from the template in resources/teams rather than kept as a
 * hand-edited file that silently goes stale.
 */
class PackageTeamsApp extends Command
{
    protected $signature = 'teams:package
                            {--output= : Where to write the zip (defaults to storage/app/teams)}';

    protected $description = 'Build the Microsoft Teams app package for upload to the Teams admin centre';

    public function handle(): int
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $host = (string) parse_url($appUrl, PHP_URL_HOST);
        $clientId = (string) config('services.microsoft.client_id');
        $appId = (string) config('services.teams.app_id');

        if (! $this->validate($appUrl, $host, $clientId, $appId)) {
            return self::FAILURE;
        }

        $source = resource_path('teams');

        $manifest = str_replace(
            ['{{APP_ID}}', '{{APP_URL}}', '{{APP_HOST}}', '{{APP_NAME}}', '{{CLIENT_ID}}'],
            [$appId, $appUrl, $host, (string) config('app.name'), $clientId],
            (string) file_get_contents($source.'/manifest.json'),
        );

        $path = $this->outputPath();

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->components->error("Could not write the package to {$path}.");

            return self::FAILURE;
        }

        // Teams requires these three at the root of the archive, named exactly
        // as the manifest's icons block refers to them.
        $zip->addFromString('manifest.json', $manifest);
        $zip->addFile($source.'/color.png', 'color.png');
        $zip->addFile($source.'/outline.png', 'outline.png');
        $zip->close();

        $this->components->info('Teams app package built.');
        $this->components->twoColumnDetail('Package', $path);
        $this->components->twoColumnDetail('Tab URL', $appUrl.'/teams');
        $this->components->twoColumnDetail('Entra resource', "api://{$host}/{$clientId}");

        $this->newLine();
        $this->line('  Upload it in Teams admin centre → Teams apps → Manage apps → Upload new app.');

        return self::SUCCESS;
    }

    /**
     * Fail loudly on the mistakes that produce a package Teams silently
     * refuses to install.
     */
    private function validate(string $appUrl, string $host, string $clientId, string $appId): bool
    {
        if ($host === '' || ! Str::startsWith($appUrl, 'https://')) {
            $this->components->error('APP_URL must be a public https:// address — Teams will not load a tab over http.');

            return false;
        }

        if ($clientId === '') {
            $this->components->error('MICROSOFT_CLIENT_ID is not set; the tab cannot sign anyone in without it.');

            return false;
        }

        if (! Str::isUuid($appId)) {
            $this->components->error('TEAMS_APP_ID must be a GUID that is unique to this Teams app.');
            $this->newLine();
            $this->line('  Add this to your .env (any fresh GUID works, but never reuse one):');
            $this->newLine();
            $this->line('      TEAMS_APP_ID='.Str::uuid()->toString());

            return false;
        }

        return true;
    }

    private function outputPath(): string
    {
        $directory = (string) ($this->option('output') ?: storage_path('app/teams'));

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        return $directory.'/'.Str::slug((string) config('app.name')).'-teams.zip';
    }
}
