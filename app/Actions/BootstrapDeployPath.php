<?php

namespace App\Actions;

use App\Models\Site;
use App\Services\Cpanel\CpanelSiteProvisioner;
use App\Services\Deployment\ReleaseManager;
use App\Services\Server\ServerProvisioner;
use App\Services\SSH\SshCommandRunner;
use RuntimeException;

class BootstrapDeployPath
{
    public function __construct(
        protected ReleaseManager $releaseManager,
        protected ServerProvisioner $serverProvisioner,
        protected CpanelSiteProvisioner $cpanelSiteProvisioner,
        protected SshCommandRunner $sshCommandRunner,
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function bootstrap(Site $site): array
    {
        if (blank($site->server)) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        if (blank($site->deploy_path)) {
            throw new RuntimeException('The site does not have a deploy path configured.');
        }

        $this->serverProvisioner->preflight($site->server, $site->deploy_path);

        return $this->bootstrapAfterPreflight($site);
    }

    /**
     * @return array<int, string>
     */
    public function bootstrapAfterPreflight(Site $site): array
    {
        if (blank($site->server)) {
            throw new RuntimeException('The site does not have a server configured.');
        }

        if (blank($site->deploy_path)) {
            throw new RuntimeException('The site does not have a deploy path configured.');
        }

        if ($site->server->connection_type === 'cpanel') {
            $this->cpanelSiteProvisioner->bootstrap($site);

            return [];
        }

        $commands = $this->releaseManager->bootstrapCommands($site);

        $this->sshCommandRunner->execute($site->server, $commands);

        return $commands;
    }
}
