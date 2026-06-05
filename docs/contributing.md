# Contributing

Thank you for considering contributing to this package! This guide covers setting up a development environment, running tests, and making changes.

## Development setup

### Clone and install

```bash
git clone git@github.com:hadimazalan/laravel-workflow.git
cd laravel-workflow
composer install
```

The package uses [Orchestra Testbench](https://github.com/orchestral/testbench) to simulate a Laravel application during testing — no separate Laravel installation is needed.

### Running tests

```bash
composer test
# or directly:
./vendor/bin/phpunit
```

All tests run against an in-memory SQLite database, so no external database is required.

### Test structure

Tests are in `tests/`:

| File | What it covers |
|---|---|
| `tests/Feature/StartApprovalWorkflowTest.php` | Starting workflows, approver resolution, SLA computation, audit recording |
| `tests/Feature/ApproveRejectWorkflowTest.php` | Approving through levels, rejection termination, authorization checks |
| `tests/Feature/DelegationAndOtpTest.php` | Delegation flow, OTP enforcement, OTP verification |

### Test stubs

The `tests/TestCase.php` provides:

- `UserStub` — a minimal user model stored in the `users` table
- `ClaimStub` — a minimal approvable model stored in the `claims` table, with an `approvalInstance()` morph-`One` relationship
- `NullOtpStub` — extends `NullOtpChallengeProvider` and overrides `enabled()` to respect `$step->otp_required`

Use these stubs when writing new tests. If your feature requires a different model shape, add new stubs to `TestCase.php`.

### Running a single test

```bash
./vendor/bin/phpunit --filter test_can_approve_through_all_levels
```

## Code style

This package follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding style.

Key conventions:

- **Namespace:** `Hadimazalan\ApprovalWorkflow`
- **Contracts** go in `src/Contracts/`
- **Models** go in `src/Models/`
- **Enums** go in `src/Enums/`
- **All models** use `$guarded = ['id']` (not `$fillable`)
- **All models** override `getTable()` and `getConnectionName()` to read from config
- **Method signatures** use typed parameters and return types throughout
- **Pattern:** `snake_case` for database columns, `camelCase` for PHP methods
- **No facades** inside the `src/` directory — inject contracts via constructor
- **No events** — audit logging is done directly via the `AuditLogger` contract

## Adding a new feature

1. **Start with a test.** Write a failing test that describes the expected behaviour.
2. **Implement the feature.** Keep it small and focused.
3. **Run the full suite.** Ensure no existing tests break.

```bash
./vendor/bin/phpunit
```

4. **Commit with a descriptive message.** Follow conventional commits:

```
feat: add support for parallel approval levels
fix: prevent double audit on delegation rollback
docs: clarify OTP provider contract
```

## Adding a new contract

If your feature introduces a new extension point:

1. Define the interface in `src/Contracts/`.
2. Provide a default implementation (no-op or sensible default).
3. Register the binding in `ApprovalWorkflowServiceProvider::register()`.
4. Add the config key with the default class in `config/approval-workflow.php`.
5. Document the contract and how to implement it.

## Modifying the database schema

1. Edit the migration stub in `database/migrations/create_approval_workflow_tables.php.stub`.
2. Update the relevant model's `$casts`, `getTable()`, and `getConnectionName()`.
3. Update the corresponding test assertions.
4. If adding a new table, update `config/approval-workflow.php` with a `tables` entry.

## Running the full test matrix

The package supports Laravel 10, 11, and 12. Your changes should not break any supported version.

The `composer.json` allows these versions:

```json
"require": {
    "illuminate/contracts": "^10.0|^11.0|^12.0",
    "illuminate/database": "^10.0|^11.0|^12.0",
    "illuminate/notifications": "^10.0|^11.0|^12.0",
    "illuminate/support": "^10.0|^11.0|^12.0"
}
```

To test against a specific version locally:

```bash
composer require laravel/framework:^11.0 --dev --with-all-dependencies
./vendor/bin/phpunit
```

Then reset:

```bash
composer install
```

## Submitting a pull request

1. Fork the repository.
2. Create a branch from `main` with a descriptive name: `feat/sla-escalation`, `fix/null-actor-audit`.
3. Write tests for your change.
4. Ensure all tests pass.
5. Open a pull request with a clear description of the problem and solution.

## Questions?

If you have questions about extending the package, open a [GitHub Discussion](https://github.com/hadimazalan/laravel-workflow/discussions) or check the contract interfaces in `src/Contracts/` — they are documented with inline examples.