<?php

namespace Filagora\Ai\Tests\Feature;

use Filagora\Ai\Models\AiChatUsage;
use Filagora\Ai\Tests\Support\TestUser;
use Filagora\Ai\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

class AiUsageReportCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reports_spend_by_feature_and_by_user_and_flags_what_weakens_the_figures(): void
    {
        $big = TestUser::make('bigspender');
        $small = TestUser::make('smallspender');

        $this->usage($big, 'thread_assist', 0.50);
        $this->usage($big, 'ai_chat_reply', 0.10);
        $this->usage($small, 'ai_chat_reply', 0.05, basis: 'fallback_price');
        $this->usage(null, 'plugin_security_scan', 0.20);
        $this->usage($small, 'ai_chat_reply', 0.01, basis: 'estimated_tokens', succeeded: false);
        // Outside the window.
        $this->usage($big, 'ai_chat_reply', 99.0, daysAgo: 60);

        $exit = Artisan::call('ai:usage-report', ['--days' => 30]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('5 calls, about $0.86', $output);
        $this->assertStringContainsString('thread_assist', $output);
        $this->assertMatchesRegularExpression('/bigspender \(#\d+\)\s*\|\s*2\s*\|\s*\$0\.6000/', $output);
        $this->assertStringContainsString('1 calls were costed at the fallback price', $output);
        $this->assertStringContainsString('1 calls were cut off before finishing', $output);
        $this->assertStringContainsString('1 calls have no user', $output);
        $this->assertStringNotContainsString('99.0', $output);
    }

    public function test_it_rejects_a_nonsense_period(): void
    {
        $this->artisan('ai:usage-report', ['--days' => '0'])->expectsOutputToContain('--days must be')->assertExitCode(1);
        $this->artisan('ai:usage-report', ['--top' => 'x'])->expectsOutputToContain('--top must be')->assertExitCode(1);
    }

    public function test_an_empty_period_reports_zero_without_error(): void
    {
        $this->artisan('ai:usage-report')->expectsOutputToContain('0 calls, about $0.00')->assertExitCode(0);
    }

    private function usage(?TestUser $user, string $context, float $cost, string $basis = 'reported', bool $succeeded = true, int $daysAgo = 1): void
    {
        AiChatUsage::query()->create([
            'user_id' => $user?->id,
            'provider' => 'anthropic',
            'model' => 'claude-sonnet-5',
            'context' => $context,
            'input_tokens' => 100,
            'output_tokens' => 10,
            'estimated_cost_usd' => $cost,
            'cost_basis' => $basis,
            'succeeded' => $succeeded,
        ])->forceFill(['created_at' => now()->subDays($daysAgo)])->save();
    }
}
