<?php

namespace App\Jobs;

use App\Exceptions\ServerNotInstalledException;
use App\Server;
use App\Services\User\ServerDeploymentService;
use DateTime;
use HCGCloud\Pterodactyl\Pterodactyl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AsyncServerDeployment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Pterodactyl
     */
    private $pterodactyl;

    /**
     * Server model to be deployed
     *
     * @var Server
     */
    protected $server;

    /**
     * Config used in server deployment
     *
     * @var array
     */
    protected $config;

    /**
     * @var string
     */
    protected $billingPeriod;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $retryAfter = 30;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 50;

    /**
     * Create a new job instance.
     *
     * @param Server $server
     * @param string $billingPeriod
     * @param array  $config
     */
    public function __construct(Server $server, string $billingPeriod, array $config)
    {
        $this->server = $server;
        $this->billingPeriod = $billingPeriod;
        $this->config = $config;
    }

    /**
     * Execute the job.
     *
     * @param Pterodactyl             $pterodactyl
     * @param ServerDeploymentService $deploymentService
     *
     * @return void
     * @throws ServerNotInstalledException
     */
    public function handle(Pterodactyl $pterodactyl, ServerDeploymentService $deploymentService)
    {
        $this->pterodactyl = $pterodactyl;

        if (!$this->serverInstalled()) {
            throw new ServerNotInstalledException;
        }

        $deploymentService->handle($this->server, $this->billingPeriod, $this->config);
    }

    /**
     * Checks if server has finished game installation
     *
     * @return bool
     */
    protected function serverInstalled()
    {
        $resource = $this->pterodactyl->server($this->server->panel_id);

        // Waiting installation if container is NOT installed
        return $resource->container['installed'];
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return DateTime
     */
    public function retryUntil()
    {
        return now()->addMinutes(5);
    }
}
