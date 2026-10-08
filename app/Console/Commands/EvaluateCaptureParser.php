<?php

namespace App\Console\Commands;

use App\Exceptions\CaptureParserFailure;
use App\Services\CaptureActions;
use App\Services\OpenAICaptureClient;
use App\Services\ParserEvaluation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class EvaluateCaptureParser extends Command
{
    protected $signature = 'parser:eval {--live : Make billable API calls using synthetic fixtures only} {--case=* : Run only these fixture IDs} {--model= : Override the model for this run only}';

    protected $description = 'Evaluate capture proposals against scope examples without writing work records';

    public function handle(ParserEvaluation $evaluation, OpenAICaptureClient $client): int
    {
        $fixtures = $evaluation->fixtures();
        $ids = $this->option('case');
        $cases = collect($fixtures['cases']);
        if (array_diff($ids, $cases->pluck('id')->all())) {
            $this->error('Unknown fixture ID. Run parser:eval to list available IDs.');

            return self::FAILURE;
        }
        $cases = $cases->filter(fn ($case) => ! $ids || in_array($case['id'], $ids, true))->values();
        $model = $this->option('model') ?: config('chart.capture.model');
        if (! preg_match('/^[a-zA-Z0-9._:-]{1,100}$/', $model)) {
            $this->error('Invalid model identifier.');

            return self::FAILURE;
        }
        $this->info('Model: '.$model.' | '.$cases->count().' cases | synthetic context only; no captures, tasks, projects, notes or action logs will be created.');
        $this->line('Deferred capabilities are expected to stay in triage. Passing these cases does not mean those scope features are implemented.');
        if (! $this->option('live')) {
            $this->table(['Case', 'Current expectation', 'Scope capability'], $cases->map(fn ($case) => [$case['id'], implode(', ', array_column($case['expected'], 'type')), $case['capability']])->all());
            $this->warn('Listing only. No API request was made and model quality has not been tested. Use --live to run these cases.');

            return self::SUCCESS;
        }
        if (blank(config('chart.capture.key'))) {
            $this->error((new CaptureParserFailure('missing_key'))->getMessage());

            return self::FAILURE;
        }
        $report = ['model' => $model, 'created_at' => now()->toIso8601String(), 'prompt_sha256' => hash('sha256', app(CaptureActions::class)->prompt()), 'fixture_sha256' => hash_file('sha256', base_path('tests/parser/fixtures.yaml')), 'cases' => []];
        foreach ($cases as $case) {
            $context = array_replace($fixtures['context'], $case['context'] ?? []);
            try {
                $result = $client->generate($case['text'], $context, $model);
                $grade = $evaluation->grade($case, $result['parsed']);
                $report['cases'][] = ['id' => $case['id'], 'text' => $case['text'], 'context' => $context, 'expected' => $case['expected'], 'capability' => $case['capability']] + $grade + $result;
                $this->line(($grade['passed'] ? 'PASS ' : 'FAIL ').$case['id'].' | '.$result['duration_ms'].' ms | '.($result['usage']['input_tokens'] ?? '?').' input / '.($result['usage']['output_tokens'] ?? '?').' output tokens');
                foreach ($grade['differences'] as $difference) {
                    $this->warn($difference);
                }
            } catch (CaptureParserFailure $exception) {
                $report['cases'][] = ['id' => $case['id'], 'passed' => false, 'error' => $exception->getMessage(), 'reason' => $exception->reason, 'usage' => $exception->usage];
                $this->error($case['id'].': '.$exception->getMessage());
                break;
            }
        }
        $passed = collect($report['cases'])->where('passed', true)->count();
        $report['summary'] = ['passed' => $passed, 'attempted' => count($report['cases']), 'selected' => $cases->count()];
        $directory = storage_path('app/private/parser-evals');
        File::ensureDirectoryExists($directory, 0700);
        $path = $directory.'/'.now()->format('Ymd-His').'-'.Str::uuid().'.json';
        File::put($path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        chmod($path, 0600);
        $this->info("{$passed}/{$cases->count()} selected cases passed. Report: {$path}");

        return $passed === $cases->count() ? self::SUCCESS : self::FAILURE;
    }
}
