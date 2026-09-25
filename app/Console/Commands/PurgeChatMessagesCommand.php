<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatModerationService;
use Illuminate\Console\Command;

class PurgeChatMessagesCommand extends Command
{
    protected $signature = 'avila:purge-chat {--days=30 : Retention window in days}';
    protected $description = 'Purge chat messages and moderation logs older than specified retention days';

    public function handle(ChatModerationService $chatService): int
    {
        $days = (int)$this->option('days');
        if ($days < 1) {
            $this->error('El período de retención debe ser de al menos 1 día.');
            return Command::FAILURE;
        }

        $this->info("Iniciando purga de mensajes de chat y registros con más de {$days} días de antigüedad...");

        $result = $chatService->purgeOldMessagesAndLogs($days);

        $this->info("✓ Fecha límite de retención: {$result['threshold_date']}");
        $this->info("✓ Mensajes de chat eliminados: {$result['deleted_messages']}");
        $this->info("✓ Registros de moderación eliminados: {$result['deleted_logs']}");

        return Command::SUCCESS;
    }
}
