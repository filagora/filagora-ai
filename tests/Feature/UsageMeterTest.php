<?php

declare(strict_types=1);

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filagora\Ai\Contracts\BillingAnchor;
use Filagora\Ai\Models\AiChatUsage;
use Filagora\Ai\Tests\Support\HostAccess;
use Filagora\Ai\Tests\Support\TestUser;
use Filagora\Ai\Usage\CalendarMonth;
use Filagora\Ai\Usage\UsageMeterService;
use Illuminate\Contracts\Auth\Authenticatable;

beforeEach(function () {
    HostAccess::$granted = [];
    $this->member = TestUser::make('Member');
    $this->billedOn = function (?string $date, ?array &$asked = null): void {
        $asked = [];
        app()->instance(BillingAnchor::class, new class($date, $asked) implements BillingAnchor
        {
            public function __construct(private readonly ?string $date, private array &$asked) {}

            public function for(Authenticatable $user, array $preferEntitlements): ?CarbonInterface
            {
                $this->asked = $preferEntitlements;

                return $this->date === null ? null : CarbonImmutable::parse($this->date);
            }
        });
    };
    $this->window = fn (string $now): array => app(UsageMeterService::class)->window($this->member, CarbonImmutable::parse($now));
    $this->usage = fn (TestUser $user, string $context, string $at, array $with = []) => tap(AiChatUsage::query()->create($with + [
        'user_id' => $user->id, 'provider' => 'fake', 'model' => 'm', 'context' => $context,
        'input_tokens' => 1, 'output_tokens' => 1, 'estimated_cost_usd' => 0.1, 'cost_basis' => 'reported', 'succeeded' => true,
    ]), fn (AiChatUsage $row) => $row->forceFill(['created_at' => CarbonImmutable::parse($at)])->save());
});

// ---- the window

it('is bound to the calendar month until a host binds its billing dates', function () {
    expect(app(BillingAnchor::class))->toBeInstanceOf(CalendarMonth::class);

    [$from, $until] = ($this->window)('2026-11-18 10:00:00');

    expect($from->toDateString())->toBe('2026-11-01')->and($until->toDateString())->toBe('2026-12-01');
});

it('counts a monthly member from their billing date', function () {
    ($this->billedOn)('2026-10-15 09:30:00');

    [$from, $until] = ($this->window)('2026-11-20 12:00:00');

    expect($from->toDateTimeString())->toBe('2026-11-15 09:30:00')->and($until->toDateTimeString())->toBe('2026-12-15 09:30:00');
});

it('starts the new window on the second the period renews', function () {
    ($this->billedOn)('2026-10-15 09:30:00');

    expect(($this->window)('2026-11-15 09:30:00')[0]->toDateTimeString())->toBe('2026-11-15 09:30:00')
        ->and(($this->window)('2026-11-15 09:29:59')[0]->toDateTimeString())->toBe('2026-10-15 09:30:00');
});

it('still resets monthly for a yearly plan', function () {
    ($this->billedOn)('2026-03-10 00:00:00');

    [$from, $until] = ($this->window)('2026-08-25 00:00:00');

    expect($from->toDateString())->toBe('2026-08-10')->and($until->toDateString())->toBe('2026-09-10');
});

it('lands on the right month when the renewal has not been recorded for months', function () {
    ($this->billedOn)('2026-08-05 00:00:00');

    [$from, $until] = ($this->window)('2026-11-20 00:00:00');

    expect($from->toDateString())->toBe('2026-11-05')->and($until->toDateString())->toBe('2026-12-05');
});

it('does not let a billing date on the 31st drift to the 28th', function () {
    ($this->billedOn)('2026-01-31 00:00:00');

    expect(($this->window)('2026-03-02 00:00:00')[0]->toDateString())->toBe('2026-02-28')
        ->and(($this->window)('2026-03-31 00:00:00')[0]->toDateString())->toBe('2026-03-31');
});

it('counts the calendar month when the billing date is in the future', function () {
    ($this->billedOn)('2027-01-01 00:00:00');

    expect(($this->window)('2026-11-18 00:00:00')[0]->toDateString())->toBe('2026-11-01');
});

it('tells the anchor which access the plan that sets the date should grant', function () {
    ($this->billedOn)('2026-10-20 00:00:00', $asked);

    ($this->window)('2026-11-25 00:00:00');

    expect($asked)->toBe(['ai_chat.access', 'operator.ai.thread_assist']);
});

// ---- what is counted

it('counts this person\'s chat and thread answers in the window only', function () {
    $other = TestUser::make('Other');
    ($this->usage)($this->member, 'ai_chat_reply', '2026-11-03');
    ($this->usage)($this->member, 'thread_assist', '2026-11-10');
    ($this->usage)($this->member, 'ai_chat_reply', '2026-10-30');                       // last window
    ($this->usage)($other, 'ai_chat_reply', '2026-11-04');                              // someone else
    ($this->usage)($this->member, 'plugin_security_scan', '2026-11-05');                // not a person's feature
    ($this->usage)($this->member, 'ai_chat_reply', '2026-11-06', ['succeeded' => false]);   // produced nothing
    ($this->usage)($this->member, 'ai_chat_reply', '2026-11-07', ['succeeded' => false, 'cost_basis' => 'estimated_tokens']); // walked away: paid for

    $meter = app(UsageMeterService::class)->forUser($this->member, CarbonImmutable::parse('2026-11-18 12:00:00'));

    expect($meter->answers)->toBe(3);
});

it('counts the features the host names', function () {
    config(['filagora-ai.usage.features' => ['summaries']]);
    ($this->usage)($this->member, 'summaries', '2026-11-03');
    ($this->usage)($this->member, 'ai_chat_reply', '2026-11-03');

    expect(app(UsageMeterService::class)->forUser($this->member, CarbonImmutable::parse('2026-11-18'))->answers)->toBe(1);
});

// ---- who sees it

it('says nothing to someone with no AI access and no use', function () {
    expect(app(UsageMeterService::class)->forUser($this->member))->toBeNull();
});

it('shows the meter to someone with access before their first answer', function () {
    HostAccess::$granted[$this->member->id] = ['ai_chat.access'];

    expect(app(UsageMeterService::class)->forUser($this->member)->answers)->toBe(0);
});

it('shows it to staff with thread assist, on the calendar month', function () {
    HostAccess::$granted[$this->member->id] = ['operator.ai.thread_assist'];

    $meter = app(UsageMeterService::class)->forUser($this->member, CarbonImmutable::parse('2026-11-18'));

    expect($meter)->not->toBeNull()->and($meter->from->toDateString())->toBe('2026-11-01');
});

it('shows it to someone who has used the AI even after losing access', function () {
    ($this->usage)($this->member, 'ai_chat_reply', now()->toDateString());

    expect(app(UsageMeterService::class)->forUser($this->member)->answers)->toBe(1);
});

// ---- percentage

it('has no percentage without an allowance', function () {
    config(['filagora-ai.chat.allowance_usd' => null]);
    HostAccess::$granted[$this->member->id] = ['ai_chat.access'];
    ($this->usage)($this->member, 'ai_chat_reply', now()->toDateString(), ['estimated_cost_usd' => 0.5]);

    $meter = app(UsageMeterService::class)->forUser($this->member);

    expect($meter->percent)->toBeNull()->and($meter->hasAllowance())->toBeFalse();
});

it('shows the share of the allowance spent, chat and thread answers drawing on one', function () {
    config(['filagora-ai.chat.allowance_usd' => 2.0]);
    ($this->usage)($this->member, 'ai_chat_reply', now()->toDateString(), ['estimated_cost_usd' => 0.5]);
    ($this->usage)($this->member, 'thread_assist', now()->toDateString(), ['estimated_cost_usd' => 0.25]);

    $meter = app(UsageMeterService::class)->forUser($this->member);

    expect($meter->percent)->toBe(37)->and($meter->hasAllowance())->toBeTrue();
});

it('caps a percentage past the allowance at a sensible number', function () {
    config(['filagora-ai.chat.allowance_usd' => 0.01]);
    ($this->usage)($this->member, 'ai_chat_reply', now()->toDateString(), ['estimated_cost_usd' => 50.0]);

    expect(app(UsageMeterService::class)->forUser($this->member)->percent)->toBe(999);
});

it('treats an allowance of zero as none', function () {
    config(['filagora-ai.chat.allowance_usd' => 0]);
    ($this->usage)($this->member, 'ai_chat_reply', now()->toDateString());

    expect(app(UsageMeterService::class)->forUser($this->member)->percent)->toBeNull();
});
