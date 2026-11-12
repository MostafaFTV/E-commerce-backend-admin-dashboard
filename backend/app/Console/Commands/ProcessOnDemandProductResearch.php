<?php

namespace App\Console\Commands;

use App\Services\ShoppingAssistant\ProductResearchFactPublisher;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\ShoppingAssistant\GapGptProductResearch;
use App\Services\ShoppingAssistant\ProductResearchEvidenceVerifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessOnDemandProductResearch extends Command
{
    protected $signature = 'assistant:research-pending {--once : Process at most one pending product}';
    protected $description = 'Research only customer-requested products with persistent results';

    public function handle(GapGptProductResearch $research, ProductResearchEvidenceVerifier $verifier, ProductResearchFactPublisher $publisher): int
    {
        if (! config('shopping_assistant_research.enabled', false)) {
            $this->line('Research disabled.');
            return self::SUCCESS;
        }

        // A bounded claim loop prevents one scheduler invocation from running forever.
        // The daily cap is checked under a DB lock immediately before every claim.
        $dailyCap = config('shopping_assistant_research.max_daily_requests', 0);
        if (! is_int($dailyCap) || $dailyCap < 1) {
            $this->warn('Research paused: a positive daily request cap is required.');
            return self::SUCCESS;
        }

        $processed = 0;
        $maxPerRun = $this->option('once') ? 1 : 5;

        while ($processed < $maxPerRun) {
            // Serialize the daily counter and the row claim with the existing budget lock.
            // Count *all* attempted dispatches, including failed and unknown outcomes.
            // No network request can happen before this claim succeeds.
            $claimedRow = DB::transaction(function () use ($dailyCap): ?object {
                if (! DB::table('shopping_assistant_budget_locks')
                    ->where('id', 1)->lockForUpdate()->first()) {
                    throw new \RuntimeException('Research quota lock is unavailable.');
                }

                $now = now();
                // Durable ledger survives product deletion and research-row cascade deletes.
                $attemptedToday = DB::table('shopping_assistant_usage_logs')
                    ->where('provider', 'gapgpt_research')
                    ->where('created_at', '>=', $now->copy()->startOfDay())
                    ->where('created_at', '<', $now->copy()->addDay()->startOfDay())
                    ->count();
                if ($attemptedToday >= $dailyCap) {
                    return null;
                }

                $row = DB::table('shopping_assistant_research_requests')
                    ->where('status', 'pending')->orderBy('id')->first();
                if ($row === null) {
                    return null;
                }

                $claimed = DB::table('shopping_assistant_research_requests')
                    ->where('id', $row->id)->where('status', 'pending')
                    ->update([
                        'status' => 'running', 'attempts' => DB::raw('attempts + 1'),
                        'started_at' => $now, 'updated_at' => $now,
                    ]);

                if ($claimed !== 1) {
                    return null;
                }

                // Reserve a durable attempt BEFORE any external call. Never mark a
                // failed/unknown dispatch free or silently estimate web-search fees.
                DB::table('shopping_assistant_usage_logs')->insert([
                    'conversation_fingerprint' => hash('sha256', 'research:' . $row->id),
                    'provider' => 'gapgpt_research',
                    'model' => (string) config('shopping_assistant.model', ''),
                    'input_tokens' => 0,
                    'output_tokens' => 0,
                    'estimated_cost_usd' => null,
                    'status' => 'attempted',
                    'dispatch_state' => 'unknown',
                    'created_at' => $now,
                ]);

                return $row;
            }, 3);
            if ($claimedRow === null) {
                break;
            }
            $row = $claimedRow;
            $processed++;
            $product = Product::query()->whereKey($row->product_id)
                ->where('status', ProductStatus::ACTIVE->value)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->first();
            if (! $product || trim((string) $product->getRawOriginal('brand')) === '') {
                $this->finish((int) $row->id, 'skipped', ['reason' => 'Product is not research-eligible']);
                continue;
            }

            // Use catalog name as SEARCH QUERY when no official manufacturer model exists.
            // This is NOT a verified model identifier and must never be auto-approved.
            $names = $product->getRawOriginal('name');
            $decoded = is_string($names) ? json_decode($names, true) : null;
            $label = is_array($decoded) ? (string) ($decoded['en'] ?? $decoded['fa'] ?? '') : (string) $names;
            $label = trim($label);
            if ($label === '') {
                $this->finish((int) $row->id, 'skipped', ['reason' => 'Product name missing']);
                continue;
            }
            try {
                $brand = trim((string) $product->getRawOriginal('brand'));
                $result = $research->research($brand, mb_substr($label, 0, 180));
                // Never treat a product title as a confirmed manufacturer model.
                $assessment = $verifier->assess($brand, $label, $result);
                $publication = $publisher->publish($product, $result);
                $this->finish((int) $row->id, $publication['approved'] ? 'completed' : 'needs_review', [
                    'research' => $result,
                    'assessment' => $assessment,
                    'publication' => $publication,
                    'product_id' => $product->id,
                    'product_updated_at' => $product->updated_at?->toIso8601String(),
                ]);
            } catch (Throwable $e) {
                Log::warning('Shopping assistant research failed', [
                    'product_id' => $product->id, 'exception' => get_class($e),
                ]);
                $this->finish((int) $row->id, 'failed', [
                    'error_type' => get_class($e),
                    'failure_category' => \App\Services\ShoppingAssistant\ResearchFailureClassifier::classify($e),
                    'failure_stage' => \App\Services\ShoppingAssistant\ResearchFailureClassifier::stage($e),
                ]);
            }
        }

        $this->line("Research requests processed: {$processed}");
        return self::SUCCESS;
    }

    private function finish(int $id, string $status, array $result): void
    {
        DB::table('shopping_assistant_research_requests')
            ->where('id', $id)->where('status', 'running')
            ->update([
                'status' => $status,
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'completed_at' => now(), 'updated_at' => now(),
            ]);
    }
}