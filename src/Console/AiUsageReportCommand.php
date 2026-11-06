<?php

declare(strict_types=1);

namespace Filagora\Ai\Console;

use Filagora\Ai\Models\AiChatUsage;
use Filagora\Core\Support\UserModel;
use Illuminate\Console\Command;

/**
 * Where the chat-completion spend went: by feature and by user. Read-only.
 * It is the evidence for sizing membership allowances
 * to size allowances from measured cost.
 */
class AiUsageReportCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'ai:usage-report
        {--days=30 : How many days back to report}
        {--top=15 : How many users to list}';

    /**
     * @var string
     */
    protected $description = 'Report AI chat spend by feature and by user over a period. Read-only.';

    public function handle(): int
    {
        foreach (['days', 'top'] as $name) {
            if (! ctype_digit((string) $this->option($name)) || (int) $this->option($name) < 1) {
                $this->error("--{$name} must be a whole number of at least 1.");

                return self::FAILURE;
            }
        }

        $days = (int) $this->option('days');
        $since = now()->subDays($days);
        $window = fn () => AiChatUsage::query()->where('created_at', '>=', $since)->toBase();

        $totals = $window()
            ->selectRaw('count(*) as calls, coalesce(sum(estimated_cost_usd), 0) as cost, coalesce(sum(succeeded = 0), 0) as failed')
            ->selectRaw("coalesce(sum(cost_basis = 'fallback_price'), 0) as fallback, coalesce(sum(cost_basis = 'estimated_tokens'), 0) as abandoned")
            ->selectRaw("coalesce(sum(cost_basis = 'none' and succeeded = 1), 0) as uncosted")
            ->selectRaw('coalesce(sum(user_id is null), 0) as unattributed')
            ->first();

        if ($totals === null) {
            return self::SUCCESS;
        }

        $this->info(sprintf('AI chat usage, last %d days: %s calls, about $%s.', $days, number_format((int) $totals->calls), number_format((float) $totals->cost, 2)));

        $this->table(['Feature', 'Calls', 'Cost', 'Avg per call'], $window()
            ->selectRaw("coalesce(context, '(unknown)') as feature, count(*) as calls, coalesce(sum(estimated_cost_usd), 0) as cost")
            ->groupBy('feature')
            ->orderByDesc('cost')
            ->get()
            ->map(fn (object $row): array => [
                $row->feature,
                number_format((int) $row->calls),
                '$'.number_format((float) $row->cost, 4),
                '$'.number_format($row->calls > 0 ? (float) $row->cost / $row->calls : 0, 4),
            ])
            ->all());

        $users = $window()
            ->whereNotNull('user_id')
            ->selectRaw('user_id, count(*) as calls, coalesce(sum(estimated_cost_usd), 0) as cost')
            ->groupBy('user_id')
            ->orderByDesc('cost')
            ->limit((int) $this->option('top'))
            ->get();

        if ($users->isNotEmpty()) {
            $names = [];

            foreach (UserModel::class()::query()->whereKey($users->pluck('user_id')->all())->get() as $user) {
                $names[$user->getKey()] = $user->getAttribute(UserModel::titleAttribute());
            }

            $this->table(['User', 'Calls', 'Cost'], $users->map(fn (object $row): array => [
                ($names[$row->user_id] ?? 'deleted user')." (#{$row->user_id})",
                number_format((int) $row->calls),
                '$'.number_format((float) $row->cost, 4),
            ])->all());
        }

        // Things that make the figures above less than the whole truth.
        foreach ([
            'fallback' => '%s calls were costed at the fallback price because their model has no price configured (filagora-ai.chat.cost_per_million_tokens).',
            'abandoned' => '%s calls were cut off before finishing; their tokens are estimated from the text.',
            'uncosted' => '%s successful calls reported no token counts and are not in the cost.',
            'unattributed' => '%s calls have no user: system calls such as the plugin scanner, or calls recorded before users were tracked.',
        ] as $field => $note) {
            if ((int) $totals->{$field} > 0) {
                $this->line('  '.sprintf($note, number_format((int) $totals->{$field})));
            }
        }

        return self::SUCCESS;
    }
}
