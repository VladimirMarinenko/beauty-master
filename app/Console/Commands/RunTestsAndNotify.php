<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunTestsAndNotify extends Command
{
    protected $signature = 'tests:notify';
    protected $description = 'Run tests and send result to Telegram channel';

    public function handle(): int
    {
        $token  = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (!$token || !$chatId) {
            $this->error('Telegram credentials are not configured.');
            return self::FAILURE;
        }

        $this->info('Running tests...');

        $process = new \Symfony\Component\Process\Process(
            ['php', 'artisan', 'test', '--compact'],
            base_path()
        );
        $process->setTimeout(600);
        $process->run();

        $output   = trim($process->getOutput() . $process->getErrorOutput());
        $exitCode = $process->getExitCode();
        $ok = $exitCode === 0;

        // Сводка: последняя строка вида "Tests: X passed (Y assertions)"
        $summary = 'Tests finished';
        if (preg_match('/Tests:\s+.*$/m', $output, $m)) {
            $summary = trim($m[0]);
        }

        $emoji  = $ok ? '✅' : '❌';
        $status = $ok ? 'Tests passed' : 'Tests FAILED';

        // Если упали — добавляем хвост вывода для разбора
        $text = "{$emoji} <b>{$status}</b>\n"
            . '<i>' . now()->format('d.m.Y H:i') . '</i>' . "\n\n";

        if ($ok) {
            $text .= '<code>' . htmlspecialchars($summary, ENT_QUOTES, 'UTF-8') . '</code>';
        } else {
            $tail = mb_substr($output, -3000);
            $text .= '<pre>' . htmlspecialchars($tail, ENT_QUOTES, 'UTF-8') . '</pre>';
        }

        try {
            $response = Http::timeout(15)->post(
                "https://api.telegram.org/bot{$token}/sendMessage",
                [
                    'chat_id'    => $chatId,
                    'text'       => $text,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true,
                ]
            );

            if (!$response->ok()) {
                Log::error('tests:notify — Telegram error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                $this->error('Telegram responded ' . $response->status());
                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            Log::error('tests:notify — HTTP error', ['error' => $e->getMessage()]);
            $this->error('HTTP error: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info($ok ? 'Tests passed.' : 'Tests failed.');
        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
