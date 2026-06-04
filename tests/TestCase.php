<?php

namespace Hadimazalan\ApprovalWorkflow\Tests;

use Hadimazalan\ApprovalWorkflow\ApprovalWorkflowServiceProvider;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalAction;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalDelegation;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalInstance;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The test approvable model and a tiny user model.
        $this->createUserTable();
        $this->createApprovableTable();

        // Run the package migration against the in-memory sqlite db.
        $migration = require __DIR__ . '/../database/migrations/create_approval_workflow_tables.php.stub';
        $migration->up();
    }

    protected function getPackageProviders($app): array
    {
        return [ApprovalWorkflowServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('approval-workflow.approver_model', UserStub::class);
        $app['config']->set('approval-workflow.otp.provider', NullOtpStub::class);
    }

    protected function createUserTable(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });
    }

    protected function createApprovableTable(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });
    }
}

class UserStub extends Model
{
    protected $table = 'users';
    protected $guarded = [];
    public $timestamps = true;
}

class ClaimStub extends Model
{
    protected $table = 'claims';
    protected $guarded = [];
    public $timestamps = true;

    public function approvalInstance()
    {
        return $this->morphOne(ApprovalInstance::class, 'approvable');
    }
}

class NullOtpStub extends \Hadimazalan\ApprovalWorkflow\Otp\NullOtpChallengeProvider
{
    public function enabled(\Hadimazalan\ApprovalWorkflow\Models\ApprovalStep $step): bool
    {
        return (bool) $step->otp_required;
    }
}
