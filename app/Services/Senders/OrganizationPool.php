<?php

namespace App\Services\Senders;

use App\Models\ClientOrganization;

/**
 * Пул «неиспользованных» организаций из выгрузки ExportBase: те, чьего ИНН ещё
 * нет в client_organizations. Дубликаты ИНН внутри файла отбрасываются, порядок
 * сохраняется. Используется и помощником, и генератором адресов.
 */
class OrganizationPool
{
    public function __construct(
        private readonly OrganizationExcelParser $parser,
    ) {
    }

    /**
     * @return array<int,array<string,string|null>>
     */
    public function unused(string $excelPath): array
    {
        $orgs = $this->parser->parse($excelPath);

        $existing = ClientOrganization::query()
            ->whereNotNull('inn')
            ->pluck('inn')
            ->map(static fn ($inn) => (string) $inn)
            ->flip();

        $seen = [];
        $pool = [];
        foreach ($orgs as $org) {
            $inn = (string) ($org['inn'] ?? '');
            if ($inn === '' || $existing->has($inn) || isset($seen[$inn])) {
                continue;
            }
            $seen[$inn] = true;
            $pool[] = $org;
        }

        return $pool;
    }
}
