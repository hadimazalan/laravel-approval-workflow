# OTP Providers

The package supports optional one-time password (OTP) challenges for high-value approval steps. When enabled on a level, the approver must provide a valid OTP code before the action (approve or reject) is accepted.

## The contract

```php
namespace Hadimazalan\ApprovalWorkflow\Contracts;

use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

interface OtpChallengeProvider
{
    public function issue(object $approver, ApprovalStep $step): string;
    public function verify(ApprovalStep $step, object $approver, string $code): bool;
    public function enabled(ApprovalStep $step): bool;
}
```

| Method | Returns | Description |
|---|---|---|
| `issue()` | `string` | Generate and deliver a challenge to the approver. Return an identifier or token. |
| `verify()` | `bool` | Check whether the submitted code matches the active challenge for this step + approver. |
| `enabled()` | `bool` | Whether OTP is currently enforced for this step. Allows runtime toggling. |

## Default: no-op provider

`Hadimazalan\ApprovalWorkflow\Otp\NullOtpChallengeProvider`

- `issue()` returns `''`
- `verify()` returns `true` (any code is accepted)
- `enabled()` returns `false`

OTP is **disabled by default**. Setting `->requireOtp()` on a level has no effect until you bind a real provider.

## Enabling OTP on a level

```php
Approval::for($claim)
    ->level('Finance')
    ->requireOtp()
    ->start();
```

This sets `otp_required = true` on the step. When the approver tries to approve or reject, the workflow calls your provider's `enabled()` and, if `true`, demands a valid OTP code.

## Writing a custom OTP provider

### Example: cache-based provider

This provider stores codes in Laravel's cache and sends them via email or SMS. You would integrate with your preferred delivery mechanism.

```php
namespace App\Workflow\Otp;

use Hadimazalan\ApprovalWorkflow\Contracts\OtpChallengeProvider;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CacheOtpProvider implements OtpChallengeProvider
{
    public function __construct(
        protected int $length = 6,
        protected int $ttl = 300,
    ) {}

    public function issue(object $approver, ApprovalStep $step): string
    {
        $code = $this->generateCode();
        $key = $this->cacheKey($step, $approver);

        Cache::put($key, $code, now()->addSeconds($this->ttl));

        // Send the code to the approver via your preferred channel
        // Mail::to($approver)->send(new OtpMail($code));
        // or SMS, WhatsApp, etc.

        return $key;
    }

    public function verify(ApprovalStep $step, object $approver, string $code): bool
    {
        $key = $this->cacheKey($step, $approver);
        $stored = Cache::get($key);

        if ($stored && hash_equals($stored, $code)) {
            Cache::forget($key);
            return true;
        }

        return false;
    }

    public function enabled(ApprovalStep $step): bool
    {
        return (bool) $step->otp_required;
    }

    protected function generateCode(): string
    {
        return str_pad((string) random_int(0, 10 ** $this->length - 1), $this->length, '0', STR_PAD_LEFT);
    }

    protected function cacheKey(ApprovalStep $step, object $approver): string
    {
        return "approval_otp:{$step->id}:{$approver->getKey()}";
    }
}
```

### Example: TOTP-based provider

For organisations that already use time-based one-time passwords (e.g., Google Authenticator):

```php
namespace App\Workflow\Otp;

use Hadimazalan\ApprovalWorkflow\Contracts\OtpChallengeProvider;
use Hadimazalan\ApprovalWorkflow\Models\ApprovalStep;

class TotpOtpProvider implements OtpChallengeProvider
{
    public function issue(object $approver, ApprovalStep $step): string
    {
        // TOTP doesn't require issuing — the user generates codes from their
        // authenticator app. Return a no-op token.
        return '';
    }

    public function verify(ApprovalStep $step, object $approver, string $code): bool
    {
        // Verify against the user's registered TOTP secret
        return $approver->verifyTotp($code);
    }

    public function enabled(ApprovalStep $step): bool
    {
        return (bool) $step->otp_required;
    }
}
```

## Registering a custom provider

Update `config/approval-workflow.php`:

```php
'otp' => [
    'provider' => App\Workflow\Otp\CacheOtpProvider::class,
    'length'   => 6,
    'ttl'      => 300, // seconds
],
```

The `length` and `ttl` values from config are available to your provider via the container or constructor arguments — bind them as needed.

You can also resolve the provider dynamically by binding the `OtpChallengeProvider` interface in the container, which is how the service provider resolves it internally.

## Using OTP in approval flows

When OTP is enforced on the current step, the approver must supply a valid code:

```php
use Hadimazalan\ApprovalWorkflow\Facades\Approval;

// The manager should issue the OTP before the approver submits it.
// This can be done via your own controller endpoint or API route.

// Then approve with the OTP:
Approval::approve($instance, $user, remarks: 'Verified', otp: '483920');

// Reject also requires OTP if the step has it enabled:
Approval::reject($instance, $user, remarks: 'Not valid', otp: '483920');
```

If the code is missing or invalid, the workflow throws `RuntimeException('An OTP code is required to perform this action.')` or `RuntimeException('Invalid OTP code.')` and records an `otp_failed` audit action.

## How OTP integrates with the workflow

The flow inside `ApprovalManager::approve()` and `reject()`:

1. The step's `otp_required` flag is checked.
2. The provider's `enabled()` is called. If `false`, OTP is skipped entirely.
3. If enabled and the code is missing, an exception is thrown.
4. If enabled and the code is present, `verify()` is called.
5. On verification failure, an `otp_failed` audit action is recorded and an exception is thrown.
6. On success, the approval or rejection proceeds normally.

This two-step check (`otp_required` + `enabled()`) allows you to toggle OTP enforcement at runtime without changing workflow definitions. For example, you might disable OTP during business hours and enable it for after-hours approvals.