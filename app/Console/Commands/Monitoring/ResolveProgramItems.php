<?php

namespace App\Console\Commands\Monitoring;

use App\Models\Monitoring\MonitoringProgram;
use App\Models\Monitoring\ProgramItem;
use App\Services\Monitoring\ProductIdentityResolver;
use Illuminate\Console\Command;

/**
 * Пересопоставить позиции программы с каноническими товарами (после правки правил
 * ProductIdentityResolver). По умолчанию — pending/ambiguous; --all — все активные.
 */
class ResolveProgramItems extends Command
{
    protected $signature = 'monitoring:resolve {program} {--all}';

    protected $description = 'Мониторинг цен: пересопоставить позиции программы с каноническими товарами';

    public function handle(ProductIdentityResolver $resolver): int
    {
        $program = MonitoringProgram::find((int) $this->argument('program'));
        if (!$program) {
            $this->error('Program not found.');

            return self::FAILURE;
        }
        $q = ProgramItem::query()->where('program_id', $program->id)->where('active', true)->orderBy('id');
        if (!$this->option('all')) {
            $q->whereIn('match_status', [ProgramItem::MATCH_PENDING, ProgramItem::MATCH_AMBIGUOUS]);
        }
        $n = 0;
        foreach ($q->cursor() as $item) {
            $item->forceFill(['product_id' => null, 'match_status' => ProgramItem::MATCH_PENDING])->save();
            $resolver->resolve($item);
            $n++;
        }
        $program->refreshCounters();
        $this->info("Resolved {$n}; matched {$program->items_matched}, ambiguous {$program->items_ambiguous}.");

        return self::SUCCESS;
    }
}
