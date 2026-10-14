<?php

namespace App\Services\Monitoring\Web;

/**
 * Минимальный парсер robots.txt (порт Content Engine): группы User-agent / Allow /
 * Disallow, самое длинное совпадение побеждает. Мы ходим с UA браузера, поэтому
 * для нас действует группа «*».
 */
final class RobotsTxt
{
    /** @var array<int, array{agents: array<int,string>, rules: array<int, array{allow: bool, path: string}>}> */
    private array $groups = [];

    /** @var array<int, string> */
    private array $sitemaps = [];

    public static function parse(string $content): self
    {
        $robots = new self();
        $idx = null;
        $lastWasAgent = false;
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);
            if ($key === 'user-agent') {
                if (!$lastWasAgent || $idx === null) {
                    $robots->groups[] = ['agents' => [], 'rules' => []];
                    $idx = array_key_last($robots->groups);
                }
                $robots->groups[$idx]['agents'][] = strtolower($value);
                $lastWasAgent = true;
                continue;
            }
            $lastWasAgent = false;
            if ($key === 'sitemap') {
                $robots->sitemaps[] = $value;
                continue;
            }
            if (($key === 'allow' || $key === 'disallow') && $idx !== null) {
                $robots->groups[$idx]['rules'][] = ['allow' => $key === 'allow', 'path' => $value];
            }
        }

        return $robots;
    }

    public function isAllowed(string $path, string $userAgent = 'iqotbot'): bool
    {
        $group = $this->groupFor(strtolower($userAgent));
        if ($group === null) {
            return true;
        }
        $best = null;
        $bestLen = -1;
        foreach ($group['rules'] as $rule) {
            if ($rule['path'] === '') {
                continue;
            }
            if ($this->matches($rule['path'], $path) && strlen($rule['path']) > $bestLen) {
                $best = $rule;
                $bestLen = strlen($rule['path']);
            }
        }

        return $best === null ? true : $best['allow'];
    }

    /** @return array<int, string> */
    public function sitemaps(): array
    {
        return $this->sitemaps;
    }

    private function groupFor(string $ua): ?array
    {
        $star = null;
        foreach ($this->groups as $group) {
            foreach ($group['agents'] as $agent) {
                if ($agent !== '*' && str_contains($ua, $agent)) {
                    return $group;
                }
                if ($agent === '*') {
                    $star = $group;
                }
            }
        }

        return $star;
    }

    private function matches(string $pattern, string $path): bool
    {
        $regex = '~^' . str_replace(['\*', '\$'], ['.*', '$'], preg_quote($pattern, '~')) . '~';

        return (bool) preg_match($regex, $path);
    }
}
