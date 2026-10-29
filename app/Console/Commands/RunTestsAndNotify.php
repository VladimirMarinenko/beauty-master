<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
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

        // Сбрасываем кэш конфига, чтобы дочерний процесс не прочитал
        // закэшированные боевые значения (mysql/beauty_master)
        try {
            Artisan::call('config:clear');
        } catch (\Throwable $e) {
            Log::warning('tests:notify — config:clear failed', ['error' => $e->getMessage()]);
        }

        $this->info('Running tests...');

        $cleanEnv = [
            'APP_ENV'          => 'testing',
            'APP_KEY'          => (string) config('app.key'),
            'DB_CONNECTION'    => 'sqlite',
            'DB_DATABASE'      => ':memory:',
            'SESSION_DRIVER'   => 'array',
            'CACHE_STORE'      => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER'      => 'array',
            'BCRYPT_ROUNDS'    => '4',
            'PATH'             => getenv('PATH') ?: '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
            'HOME'             => getenv('HOME') ?: '/tmp',
        ];

        $process = new \Symfony\Component\Process\Process(
            ['php', 'artisan', 'test', '--compact', '--env=testing'],
            base_path(),
            $cleanEnv
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
                    'chat_id'                  => $chatId,
                    'text'                     => $text,
                    'parse_mode'               => 'HTML',
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
