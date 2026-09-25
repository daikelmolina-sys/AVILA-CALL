<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatModerationService;
use Illuminate\Console\Command;

class PurgeEndedClassDataCommand extends Command
{
    protected $signature = 'avila:purge-ended-class-data {--hours=1 : Hours to wait after the class ends}';
    protected $description = 'Purge chat and moderation audit records after a class has been ended for the retention window';

    public function handle(ChatModerationService $chatService): int
    {
        $hours = (int)$this->option('hours');
        if ($hours < 1) {
            $this->error('El período posterior a la clase debe ser de al menos 1 hora.');
            return Command::FAILURE;
        }

        $result = $chatService->purgeEndedClassMessagesAndLogs($hours);

        $this->info("Purga posterior a clase finalizada ({$hours} hora(s)) completada.");
        $this->info("✓ Clases elegibles: {$result['eligible_classes']}");
        $this->info("✓ Mensajes de chat eliminados: {$result['deleted_messages']}");
        $this->info("✓ Registros de auditoría eliminados: {$result['deleted_logs']}");

        return Command::SUCCESS;
    }
}
