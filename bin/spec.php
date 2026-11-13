<?php

/**
 * Schemas der Plattform (Core C11, SP1): Quelle ist `spec/` im Repo EduKSX-Architektur, Core liefert sie unverändert
 * unter resources/spec/ aus. Dieses Werkzeug übernimmt sie und prüft, dass die Kopie unverändert ist.
 *
 *   php bin/spec.php update <Pfad zum Architektur-Repo> [<Git-Referenz, Standard: origin/main>]
 *   php bin/spec.php check                       (CI: Prüfsummen gegen resources/spec/SOURCE.json)
 */
$root = dirname(__DIR__);
$target = $root.'/resources/spec';
$sourceFile = $target.'/SOURCE.json';
$command = $argv[1] ?? '';

/** @return array<string, string> Pfad relativ zu resources/spec → sha256 */
function hashes(string $dir): array
{
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $file) {
        $rel = substr($file->getPathname(), strlen($dir) + 1);
        if ($rel !== 'SOURCE.json') {
            $out[str_replace('\\', '/', $rel)] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($out);

    return $out;
}

if ($command === 'check') {
    $source = json_decode((string) file_get_contents($sourceFile), true, flags: JSON_THROW_ON_ERROR);
    $actual = hashes($target);
    $problems = [];
    foreach ($source['files'] as $path => $hash) {
        if (! isset($actual[$path])) {
            $problems[] = "fehlt: {$path}";
        } elseif ($actual[$path] !== $hash) {
            $problems[] = "verändert: {$path}";
        }
    }
    foreach (array_diff_key($actual, $source['files']) as $path => $_) {
        $problems[] = "nicht aus der Quelle: {$path}";
    }
    if ($problems !== []) {
        fwrite(STDERR, "Schemas weichen von der Quelle ab (nur über «php bin/spec.php update» ändern):\n  ".implode("\n  ", $problems)."\n");
        exit(1);
    }
    echo 'Schemas unverändert ('.count($actual).' Dateien, Quelle '.$source['repository'].' '.$source['release'].' @ '.substr($source['commit'], 0, 12).")\n";
    exit(0);
}

if ($command === 'update') {
    $repo = $argv[2] ?? null;
    $ref = $argv[3] ?? 'origin/main';
    if ($repo === null || ! is_dir($repo.'/.git')) {
        fwrite(STDERR, "Aufruf: php bin/spec.php update <Pfad zum Architektur-Repo> [<Git-Referenz>]\n");
        exit(2);
    }
    $git = fn (string $args): string => trim((string) shell_exec('git -C '.escapeshellarg($repo).' '.$args));
    $commit = $git('log -1 --format=%H '.escapeshellarg($ref).' -- spec');
    if ($commit === '') {
        fwrite(STDERR, "Referenz «{$ref}» ohne spec/.\n");
        exit(1);
    }
    $release = $git('describe --tags --contains '.escapeshellarg($commit).' 2>/dev/null') ?: 'unveröffentlicht';
    $release = preg_replace('/[~^].*$/', '', $release);

    // nur spec/v<n>/ übernehmen (README bleibt im Architektur-Repo)
    foreach (glob($target.'/v*', GLOB_ONLYDIR) ?: [] as $old) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($old, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($old);
    }
    $files = array_filter(explode("\n", $git('ls-tree -r --name-only '.escapeshellarg($commit).' spec')), fn ($f) => preg_match('#^spec/v\d+/#', $f) === 1);
    foreach ($files as $file) {
        $dest = $target.'/'.substr($file, strlen('spec/'));
        @mkdir(dirname($dest), 0777, true);
        file_put_contents($dest, shell_exec('git -C '.escapeshellarg($repo).' show '.escapeshellarg($commit.':'.$file)));
    }
    $source = [
        'note' => 'Unverändert übernommen aus spec/ (Core C11). Nicht von Hand ändern: php bin/spec.php update',
        'repository' => 'MarkusKeller8200/EduKSX-Architektur',
        'release' => $release,
        'commit' => $commit,
        'date' => $git('log -1 --format=%cs '.escapeshellarg($commit)),
        'files' => hashes($target),
    ];
    file_put_contents($sourceFile, json_encode($source, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
    echo 'Übernommen: '.count($files)." Dateien aus {$release} @ ".substr($commit, 0, 12)."\n";
    exit(0);
}

fwrite(STDERR, "Aufruf: php bin/spec.php update <Pfad> [<Referenz>] | check\n");
exit(2);
