<?php

namespace Database\Seeders;

use App\Models\Deployment;
use App\Models\CpanelWizardRun;
use App\Models\DeploymentStep;
use App\Models\AppSetting;
use App\Models\ServerHealthCheck;
use App\Models\ServerConnectionTest;
use App\Models\Server;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        AppSetting::query()->updateOrCreate([
            'id' => 1,
        ], [
            'app_name' => 'verityDeploy',
            'default_branch' => 'main',
            'default_web_root' => 'public',
            'default_php_version' => '8.3',
            'default_deploy_source' => 'git',
            'default_ssh_port' => 22,
            'github_webhook_path' => '/webhooks/github',
            'github_webhook_events' => 'push',
            'github_api_token' => null,
        ]);

        $user = User::query()->updateOrCreate([
            'email' => 'kmoses@monaksoft.com',
        ], [
            'name' => 'Test User',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $server = Server::query()->updateOrCreate([
            'ip_address' => '203.0.113.10',
        ], [
            'user_id' => $user->id,
            'name' => 'Production Server',
            'ssh_port' => 22,
            'ssh_user' => 'forge',
            'connection_type' => 'ssh_key',
            'ssh_key' => null,
            'sudo_password' => null,
            'status' => 'online',
            'metrics' => [
                'cpu_usage' => 0.12,
                'ram_usage' => '48%',
                'disk_free' => '63%',
                'uptime' => '5 days, 2:14',
            ],
            'last_connected_at' => now(),
            'notes' => 'Demo server for verityDeploy.',
        ]);

        $site = Site::query()->updateOrCreate([
            'server_id' => $server->id,
            'name' => 'veritydeploy-app',
        ], [
            'repository_url' => 'https://github.com/example/veritydeploy-app.git',
            'default_branch' => 'main',
            'deploy_path' => '/var/www/veritydeploy-app',
            'php_version' => '8.3',
            'web_root' => 'public',
            'deploy_source' => 'git',
            'webhook_secret' => 'demo-webhook-secret',
            'environment_variables' => [
                'APP_NAME' => 'verityDeploy Demo',
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'QUEUE_CONNECTION' => 'redis',
            ],
            'shared_files' => [
                [
                    'path' => 'storage/app/public/.gitignore',
                    'contents' => "*\n!.gitignore\n",
                ],
            ],
            'github_webhook_status' => 'needs-sync',
            'github_webhook_last_error' => null,
            'active' => true,
            'last_deployed_at' => now()->subHour(),
            'notes' => 'Demo site connected to the production server.',
        ]);

        $deployment = Deployment::query()->updateOrCreate([
            'site_id' => $site->id,
            'commit_hash' => 'demo-commit-001',
        ], [
            'triggered_by_user_id' => $user->id,
            'source' => 'manual',
            'status' => 'successful',
            'branch' => 'main',
            'release_path' => '/var/www/veritydeploy-app/releases/demo-001',
            'started_at' => now()->subHour(),
            'finished_at' => now()->subHour()->addMinutes(2),
            'exit_code' => 0,
            'output' => implode("\n\n", [
                '[Fetch latest code]' . "\n" . 'Already up to date.',
                '[Install dependencies]' . "\n" . 'Composer dependencies installed successfully.',
                '[Run migrations]' . "\n" . 'Nothing to migrate.',
                '[Restart queue workers]' . "\n" . 'Queue workers restarted.',
            ]),
            'error_message' => null,
        ]);

        $steps = [
            [
                'sequence' => 1,
                'label' => 'Fetch latest code',
                'command' => 'git fetch --all --prune',
                'status' => 'successful',
                'output' => 'Already up to date.',
            ],
            [
                'sequence' => 2,
                'label' => 'Install dependencies',
                'command' => 'composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader',
                'status' => 'successful',
                'output' => 'Composer dependencies installed successfully.',
            ],
            [
                'sequence' => 3,
                'label' => 'Run migrations',
                'command' => 'php artisan migrate --force',
                'status' => 'successful',
                'output' => 'Nothing to migrate.',
            ],
            [
                'sequence' => 4,
                'label' => 'Restart queue workers',
                'command' => 'php artisan queue:restart',
                'status' => 'successful',
                'output' => 'Queue workers restarted.',
            ],
        ];

        foreach ($steps as $step) {
            DeploymentStep::query()->updateOrCreate([
                'deployment_id' => $deployment->id,
                'sequence' => $step['sequence'],
            ], [
                'label' => $step['label'],
                'command' => $step['command'],
                'status' => $step['status'],
                'output' => $step['output'],
                'started_at' => now()->subHour()->addMinutes($step['sequence'] - 1),
                'finished_at' => now()->subHour()->addMinutes($step['sequence']),
                'exit_code' => 0,
            ]);
        }

        $cpanelServer = Server::query()->updateOrCreate([
            'ip_address' => '203.0.113.20',
        ], [
            'user_id' => $user->id,
            'name' => 'cPanel Demo Server',
            'ssh_port' => 22,
            'ssh_user' => 'demo',
            'connection_type' => 'cpanel',
            'ssh_key' => null,
            'sudo_password' => null,
            'cpanel_api_port' => 2083,
            'cpanel_api_token' => 'demo-cpanel-token',
            'status' => 'online',
            'metrics' => [
                'cpu_usage' => 0.08,
                'ram_usage' => '37%',
                'disk_free' => '71%',
                'uptime' => '3 days, 4:12',
            ],
            'last_connected_at' => now(),
            'notes' => 'Demo cPanel server for release-selector testing.',
        ]);

        $cpanelSite = Site::query()->updateOrCreate([
            'server_id' => $cpanelServer->id,
            'name' => 'cpanel-demo-app',
        ], [
            'repository_url' => 'https://github.com/example/cpanel-demo-app.git',
            'default_branch' => 'main',
            'deploy_path' => '/home/demo/cpanel-demo-app',
            'current_release_path' => '/home/demo/cpanel-demo-app/releases/20260328010101-2',
            'local_source_path' => '/workspace/cpanel-demo-app',
            'php_version' => '8.3',
            'web_root' => 'public',
            'deploy_source' => 'local',
            'webhook_secret' => 'demo-cpanel-webhook-secret',
            'environment_variables' => [
                'APP_NAME' => 'cPanel Demo',
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'QUEUE_CONNECTION' => 'database',
            ],
            'shared_files' => [
                [
                    'path' => 'storage/app/public/.gitignore',
                    'contents' => "*\n!.gitignore\n",
                ],
            ],
            'github_webhook_status' => 'needs-sync',
            'github_webhook_last_error' => null,
            'active' => true,
            'last_deployed_at' => now()->subMinutes(30),
            'notes' => 'Demo cPanel site with release history for selector testing.',
        ]);

        $cpanelDeployments = [
            [
                'commit_hash' => 'cpanel-demo-001',
                'status' => 'successful',
                'branch' => 'main',
                'release_path' => '/home/demo/cpanel-demo-app/releases/20260328000101-1',
                'started_at' => now()->subHours(2),
                'finished_at' => now()->subHours(2)->addMinutes(3),
                'output' => implode("\n\n", [
                    '[Package local source]' . "\n" . 'Archive created and uploaded.',
                    '[Activate release]' . "\n" . 'Release activated successfully.',
                ]),
                'error_message' => null,
            ],
            [
                'commit_hash' => 'cpanel-demo-002',
                'status' => 'successful',
                'branch' => 'main',
                'release_path' => '/home/demo/cpanel-demo-app/releases/20260328010101-2',
                'started_at' => now()->subMinutes(30),
                'finished_at' => now()->subMinutes(27),
                'output' => implode("\n\n", [
                    '[Package local source]' . "\n" . 'Archive created and uploaded.',
                    '[Sync shared runtime]' . "\n" . 'Shared files linked into the release.',
                    '[Activate release]' . "\n" . 'Release activated successfully.',
                ]),
                'error_message' => null,
            ],
        ];

        foreach ($cpanelDeployments as $cpanelDeploymentData) {
            $cpanelDeployment = Deployment::query()->updateOrCreate([
                'site_id' => $cpanelSite->id,
                'commit_hash' => $cpanelDeploymentData['commit_hash'],
            ], [
                'triggered_by_user_id' => $user->id,
                'source' => 'manual',
                'status' => $cpanelDeploymentData['status'],
                'branch' => $cpanelDeploymentData['branch'],
                'release_path' => $cpanelDeploymentData['release_path'],
                'started_at' => $cpanelDeploymentData['started_at'],
                'finished_at' => $cpanelDeploymentData['finished_at'],
                'exit_code' => 0,
                'output' => $cpanelDeploymentData['output'],
                'error_message' => $cpanelDeploymentData['error_message'],
            ]);

            DeploymentStep::query()->updateOrCreate([
                'deployment_id' => $cpanelDeployment->id,
                'sequence' => 1,
            ], [
                'label' => 'Package local source',
                'command' => 'tar -czf source.tar.gz .',
                'status' => 'successful',
                'output' => 'Archive created and uploaded.',
                'started_at' => $cpanelDeploymentData['started_at'],
                'finished_at' => $cpanelDeploymentData['started_at']->copy()->addMinute(),
                'exit_code' => 0,
            ]);

            DeploymentStep::query()->updateOrCreate([
                'deployment_id' => $cpanelDeployment->id,
                'sequence' => 2,
            ], [
                'label' => 'Activate release',
                'command' => 'ln -sfn release current',
                'status' => 'successful',
                'output' => 'Release activated successfully.',
                'started_at' => $cpanelDeploymentData['started_at']->copy()->addMinute(),
                'finished_at' => $cpanelDeploymentData['finished_at'],
                'exit_code' => 0,
            ]);
        }

        ServerConnectionTest::query()->updateOrCreate([
            'server_id' => $server->id,
            'command' => 'echo verityDeploy-connection-test',
        ], [
            'status' => 'successful',
            'output' => 'verityDeploy-connection-test',
            'error_message' => null,
            'exit_code' => 0,
            'tested_at' => now()->subDay(),
        ]);

        ServerHealthCheck::query()->updateOrCreate([
            'server_id' => $server->id,
            'tested_at' => now()->subHours(2),
        ], [
            'status' => 'successful',
            'output' => implode("\n\n", [
                'uptime:' . "\n" . ' 14:55:10 up 5 days, 2:14, 1 user, load average: 0.12, 0.10, 0.09',
                'free -m:' . "\n" . 'Mem:  7986  3851  4135',
                'df -h /:' . "\n" . '/dev/sda1  100G  37G  63G  37% /',
            ]),
            'metrics' => [
                'cpu_usage' => 0.12,
                'ram_usage' => '48%',
                'disk_free' => '63%',
                'uptime' => '5 days, 2:14',
            ],
            'error_message' => null,
            'exit_code' => 0,
        ]);

        ServerConnectionTest::query()->updateOrCreate([
            'server_id' => $cpanelServer->id,
            'command' => 'uapi Tokens list',
        ], [
            'status' => 'successful',
            'output' => 'Tokens listed successfully.',
            'error_message' => null,
            'exit_code' => 0,
            'tested_at' => now()->subHours(6),
        ]);

        ServerHealthCheck::query()->updateOrCreate([
            'server_id' => $cpanelServer->id,
            'tested_at' => now()->subHours(5),
        ], [
            'status' => 'successful',
            'output' => implode("\n\n", [
                'uptime:' . "\n" . ' 11:20:10 up 3 days, 4:12, 2 users, load average: 0.08, 0.05, 0.04',
                'free -m:' . "\n" . 'Mem:  7986  2950  5036',
                'df -h /home:' . "\n" . '/dev/sda1  100G  29G  71G  29% /home',
            ]),
            'metrics' => [
                'cpu_usage' => 0.08,
                'ram_usage' => '37%',
                'disk_free' => '71%',
                'uptime' => '3 days, 4:12',
            ],
            'error_message' => null,
            'exit_code' => 0,
        ]);

        CpanelWizardRun::query()->updateOrCreate([
            'server_id' => $cpanelServer->id,
            'site_id' => null,
            'wizard_type' => 'server_checks',
            'started_at' => now()->subHours(4),
        ], [
            'status' => 'successful',
            'steps' => [
                [
                    'step' => 'Discover SSH port',
                    'status' => 'successful',
                    'message' => 'The cPanel account SSH port is 22.',
                    'timestamp' => now()->subHours(4)->toDateTimeString(),
                ],
                [
                    'step' => 'Test cPanel API',
                    'status' => 'successful',
                    'message' => 'The cPanel API responded successfully.',
                    'timestamp' => now()->subHours(4)->addMinute()->toDateTimeString(),
                ],
                [
                    'step' => 'Server provisioning',
                    'status' => 'successful',
                    'message' => 'Server provisioning preflight completed.',
                    'timestamp' => now()->subHours(4)->addMinutes(2)->toDateTimeString(),
                ],
            ],
            'summary' => implode(PHP_EOL, [
                'Discover SSH port: The cPanel account SSH port is 22.',
                'Test cPanel API: The cPanel API responded successfully.',
                'Server provisioning: Server provisioning preflight completed.',
            ]),
            'error_message' => null,
            'finished_at' => now()->subHours(4)->addMinutes(2),
        ]);

        CpanelWizardRun::query()->updateOrCreate([
            'server_id' => $cpanelServer->id,
            'site_id' => $cpanelSite->id,
            'wizard_type' => 'site_bootstrap',
            'started_at' => now()->subMinutes(30),
        ], [
            'status' => 'successful',
            'steps' => [
                [
                    'step' => 'Discover SSH port',
                    'status' => 'successful',
                    'message' => 'The cPanel account SSH port is 22.',
                    'timestamp' => now()->subMinutes(30)->toDateTimeString(),
                ],
                [
                    'step' => 'Test cPanel API',
                    'status' => 'successful',
                    'message' => 'The cPanel API responded successfully.',
                    'timestamp' => now()->subMinutes(29)->toDateTimeString(),
                ],
                [
                    'step' => 'Server provisioning',
                    'status' => 'successful',
                    'message' => 'Server provisioning preflight completed.',
                    'timestamp' => now()->subMinutes(28)->toDateTimeString(),
                ],
                [
                    'step' => 'Bootstrap deploy path',
                    'status' => 'successful',
                    'message' => 'The deployment path was bootstrapped successfully.',
                    'timestamp' => now()->subMinutes(27)->toDateTimeString(),
                ],
            ],
            'summary' => implode(PHP_EOL, [
                'Discover SSH port: The cPanel account SSH port is 22.',
                'Test cPanel API: The cPanel API responded successfully.',
                'Server provisioning: Server provisioning preflight completed.',
                'Bootstrap deploy path: The deployment path was bootstrapped successfully.',
            ]),
            'error_message' => null,
            'finished_at' => now()->subMinutes(27),
        ]);
    }
}
