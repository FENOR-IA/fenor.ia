<?php

function loadApps(string $appsPath): array {
    $apps = [];
    foreach (['dev', 'hml', 'prd'] as $env) {
        $dir = "$appsPath/$env";
        if (!is_dir($dir)) continue;
        foreach (scandir($dir) as $app) {
            if ($app === '.' || $app === '..') continue;
            if (!is_dir("$dir/$app")) continue;
            $envFile = "$dir/$app/.env";
            $appEnv  = [];
            if (file_exists($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (strncmp(trim($line), '#', 1) === 0) continue;
                    [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                    $appEnv[trim($k)] = trim($v);
                }
            }
            $apps[$app]['name'] = $app;
            $apps[$app]['envs'][$env] = [
                'url'         => $appEnv['APP_URL']      ?? '',
                'schema'      => $appEnv['DB_SCHEMA']    ?? '',
                'terminal'    => $appEnv['TERMINAL_URL'] ?? '',
                'github_repo' => $appEnv['GITHUB_REPO']  ?? '',
                'path'        => "$dir/$app",
            ];
        }
    }
    ksort($apps);
    return array_values($apps);
}

function fenorClaudeConfigured(): bool {
    return trim(fenorSetting('CLAUDE_CODE_OAUTH_TOKEN', '')) !== ''
        || trim(fenorSetting('ANTHROPIC_API_KEY', '')) !== '';
}

function nextEnv(array $app): ?string {
    if (!isset($app['envs']['hml'])) return 'hml';
    if (!isset($app['envs']['prd'])) return 'prd';
    return null;
}

function envBadge(string $env): string {
    $map = ['dev' => 'badge-dev', 'hml' => 'badge-demo', 'prd' => 'badge-prd'];
    $cls = $map[$env] ?? 'badge-dev';
    return "<span class=\"badge $cls\">$env</span>";
}

function pipeline(array $app): string {
    $steps  = ['dev' => 'DEV', 'hml' => 'HML', 'prd' => 'PRD'];
    $parts  = [];
    $arrows = [];
    $out    = '<div class="pipeline">';
    $keys   = array_keys($steps);
    foreach ($keys as $i => $step) {
        $has  = isset($app['envs'][$step]);
        $cls  = $has ? 'step-done' : 'step-empty';
        $url  = $has ? ($app['envs'][$step]['url'] ?? '') : '';
        $label = $steps[$step];
        if ($has && $url) {
            $out .= "<a href=\"" . htmlspecialchars($url) . "\" target=\"_blank\" class=\"pipeline-step $cls\">$label ↗</a>";
        } else {
            $out .= "<div class=\"pipeline-step $cls\">$label</div>";
        }
        if ($i < count($keys) - 1) {
            $out .= '<div class="pipeline-arrow">→</div>';
        }
    }
    return $out . '</div>';
}
