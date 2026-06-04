<?php

namespace Hadimazalan\ApprovalWorkflow;

use Hadimazalan\ApprovalWorkflow\Audit\DatabaseAuditLogger;
use Hadimazalan\ApprovalWorkflow\Contracts\AuditLogger;
use Hadimazalan\ApprovalWorkflow\Contracts\ApproverResolver;
use Hadimazalan\ApprovalWorkflow\Contracts\NotificationChannel;
use Hadimazalan\ApprovalWorkflow\Contracts\OtpChallengeProvider;
use Hadimazalan\ApprovalWorkflow\Otp\NullOtpChallengeProvider;
use Hadimazalan\ApprovalWorkflow\Resolvers\ConfiguredApproverResolver;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class ApprovalWorkflowServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/approval-workflow.php',
            'approval-workflow'
        );

        $this->app->singleton('approval', function (Application $app) {
            $config = $app['config']->get('approval-workflow');

            $manager = new ApprovalManager(
                resolver: $app->make(ApproverResolver::class),
                otp: $app->make(OtpChallengeProvider::class),
                audit: $app->make(AuditLogger::class),
                channels: $this->buildChannels($app, $config),
                config: $config,
            );

            return $manager;
        });

        $this->app->singleton(ApprovalManager::class, fn (Application $app) => $app->make('approval'));

        $this->app->bind(ApproverResolver::class, function (Application $app) {
            $class = $app['config']->get('approval-workflow.resolver', ConfiguredApproverResolver::class);

            return $app->make($class);
        });

        $this->app->bind(OtpChallengeProvider::class, function (Application $app) {
            $class = $app['config']->get('approval-workflow.otp.provider', NullOtpChallengeProvider::class);

            return $app->make($class);
        });

        $this->app->bind(AuditLogger::class, function (Application $app) {
            $class = $app['config']->get('approval-workflow.audit.logger', DatabaseAuditLogger::class);

            return $app->make($class);
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/approval-workflow.php' => config_path('approval-workflow.php'),
            ], 'approval-workflow-config');

            $this->publishes([
                __DIR__ . '/../database/migrations/create_approval_workflow_tables.php.stub' => database_path('migrations/' . date('Y_m_d_His') . '_create_approval_workflow_tables.php'),
            ], 'approval-workflow-migrations');

            $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        }
    }

    /**
     * Build the named channel registry from config.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, NotificationChannel>
     */
    protected function buildChannels(Application $app, array $config): array
    {
        $channels = [];

        foreach (($config['channels'] ?? []) as $name => $definition) {
            $driver = $definition['driver'] ?? null;

            if (! $driver) {
                continue;
            }

            // Driver may be a class name string OR a closure registered in
            // the container under the "approval-workflow.channels.$name" key.
            if (is_string($driver) && class_exists($driver)) {
                $instance = $app->make($driver);
            } else {
                $instance = $app->make("approval-workflow.channels.{$name}");
            }

            if ($instance instanceof NotificationChannel) {
                $channels[$name] = $instance;
            }
        }

        return $channels;
    }
}
