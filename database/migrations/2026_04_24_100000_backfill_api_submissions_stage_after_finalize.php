<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Бэкфилл stage для api_submissions, финализированных до фикса maybeFinalize.
 *
 * До правки ModerationService::maybeFinalize оставлял submission.stage
 * в 'awaiting_moderation' даже после перехода status='ready'. Из-за этого
 * они висели в админском списке «требует действий».
 *
 * Приводим в порядок:
 *   status='ready' AND stage IN (awaiting_moderation, in_moderation, classifying, inbox_buffered)
 *       →  stage = 'moderated'       если items_accepted > 0
 *       →  stage = 'rejected_all'    если items_accepted = 0
 */
return new class extends Migration {
    public function up(): void
    {
        $staleStages = ['inbox_buffered', 'classifying', 'awaiting_moderation', 'in_moderation'];

        DB::table('api_submissions')
            ->where('status', 'ready')
            ->whereIn('stage', $staleStages)
            ->where('items_accepted', '>', 0)
            ->update(['stage' => 'moderated']);

        DB::table('api_submissions')
            ->where('status', 'ready')
            ->whereIn('stage', $staleStages)
            ->where('items_accepted', 0)
            ->update(['stage' => 'rejected_all']);
    }

    public function down(): void
    {
        // Обратный откат не имеет смысла — stage нёс неверное значение.
    }
};
