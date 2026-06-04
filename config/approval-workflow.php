<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Table Names
    |--------------------------------------------------------------------------
    |
    | Customize the database tables used by the package. You almost never
    | need to change these unless you are integrating into a legacy
    | database that already has tables with the same name.
    |
    */

    'tables' => [
        'instances'   => 'approval_instances',
        'steps'       => 'approval_steps',
        'actions'     => 'approval_actions',
        'delegations' => 'approval_delegations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Connection
    |--------------------------------------------------------------------------
    |
    | The connection used by the package's models. If null, the default
    | application connection is used.
    |
    */

    'connection' => null,

    /*
    |--------------------------------------------------------------------------
    | Approver Resolver
    |--------------------------------------------------------------------------
    |
    | The class that resolves approvers for a given workflow level. The
    | default implementation supports explicit user IDs, role names,
    | department names, closures, and delegating to a custom class.
    |
    */

    'resolver' => Hadimazalan\ApprovalWorkflow\Resolvers\ConfiguredApproverResolver::class,

    /*
    |--------------------------------------------------------------------------
    | Approver Model
    |--------------------------------------------------------------------------
    |
    | The Eloquent model that represents a user capable of approving. Used by
    | the default approver resolver to look up users by ID, role, etc.
    |
    */

    'approver_model' => null,

    /*
    |--------------------------------------------------------------------------
    | Notification Channels
    |--------------------------------------------------------------------------
    |
    | A map of named channels. The keys (e.g. "email", "whatsapp") are the
    | values you pass to ->notifyBy([...]). Out of the box we ship "email"
    | and a generic "callback" channel suitable for WhatsApp / SMS / push.
    |
    */

    'channels' => [
        'email' => [
            'driver' => Hadimazalan\ApprovalWorkflow\Notifications\MailNotificationChannel::class,
        ],
        'callback' => [
            'driver' => Hadimazalan\ApprovalWorkflow\Notifications\CallbackNotificationChannel::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Channel Aliases
    |--------------------------------------------------------------------------
    |
    | Map of friendly channel names to actual channel names. The fluent
    | ->notifyBy(['whatsapp']) call will be translated using this map.
    |
    */

    'channel_aliases' => [
        'whatsapp' => 'callback',
        'sms'      => 'callback',
        'push'     => 'callback',
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP Challenge Provider
    |--------------------------------------------------------------------------
    |
    | The class that issues and verifies OTP challenges. The default
    | implementation is a no-op (OTP is not enforced). Bind your own
    | implementation to enable OTP per-level or per-workflow.
    |
    */

    'otp' => [
        'provider' => Hadimazalan\ApprovalWorkflow\Otp\NullOtpChallengeProvider::class,
        'length'   => 6,
        'ttl'      => 300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Logger
    |--------------------------------------------------------------------------
    |
    | The class that records immutable approval events. The default writes
    | ApprovalAction rows to the database.
    |
    */

    'audit' => [
        'logger' => Hadimazalan\ApprovalWorkflow\Audit\DatabaseAuditLogger::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA Defaults
    |--------------------------------------------------------------------------
    |
    | The default SLA window (in hours) for each step. May be overridden
    | per-level in the builder. Apps are responsible for scheduling
    | reminders/escalations.
    |
    */

    'sla' => [
        'default_hours' => 48,
        'timezone'      => 'UTC',
    ],
];
