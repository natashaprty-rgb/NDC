<?php
$routes = json_decode(shell_exec('php artisan route:list --json'), true);
$out = "# HTTP endpoint reference\n\nNDC-SMS is a server-rendered web application; all endpoints below return HTML pages or redirects (exports return CSV/PDF, downloads return files). There is no public JSON API in Version 1.\n\n";
$out .= "All endpoints except sign-in, password reset, language switch and `/up` require an authenticated, active session. State-changing requests (POST/PUT/DELETE) require the CSRF token that every form includes. *Permission* lists the route-level permission middleware; record-level policy checks are additionally applied inside controllers (see `03-roles-permissions.md`).\n\nRegenerate with `php docs/dev/generate-endpoints.php` (or inspect `php artisan route:list`).\n";
$groups = [];
foreach ($routes as $r) {
    if (str_starts_with($r['uri'], '_') || str_starts_with($r['uri'], 'storage')) continue;
    $seg = explode('/', $r['uri'])[0] ?: '/';
    if ($seg === 'admin') $seg = 'admin/'.(explode('/', $r['uri'])[1] ?? '');
    $perm = implode(', ', array_map(fn($m) => '`'.substr($m, 4).'`', array_filter((array) $r['middleware'], fn($m) => str_starts_with($m, 'can:'))));
    $thr = implode(', ', array_map(fn($m) => substr($m, 9), array_filter((array) $r['middleware'], fn($m) => str_starts_with($m, 'throttle:'))));
    $groups[$seg][] = sprintf("| %s | `/%s` | %s | %s | %s |", str_replace(['|HEAD', '|'], ['', '/'], $r['method']), ltrim($r['uri'], '/'), $r['name'] ? '`'.$r['name'].'`' : '', $perm ?: '—', $thr ?: '');
}
ksort($groups);
foreach ($groups as $g => $rows) {
    $out .= "\n## /{$g}\n\n| Method | URI | Name | Permission | Rate limit |\n|---|---|---|---|---|\n".implode("\n", $rows)."\n";
}
file_put_contents('docs/11-endpoints.md', $out);
echo count($routes), " routes\n";
