<?php

/*
 * This demo is generic and public. It must not depend on, mount, seed or mention a site-specific
 * profile package: those belong to the application built for that site, not to the worked example
 * that every other site learns from. If this fails, remove the reference; do not loosen the test.
 */

const SITE_PROFILE_PATTERN = '/zen[-_]?cart/i';

/**
 * @return list<string>
 */
function guardedFiles(): array
{
    $root = dirname(__DIR__, 2);
    $files = [];

    foreach (['composer.json', 'composer.lock', 'README.md', 'AGENTS.md', 'CLAUDE.md', '.env.example', 'phpunit.xml', 'package.json'] as $file) {
        if (is_file("$root/$file")) {
            $files[] = "$root/$file";
        }
    }

    // The DDEV project's own settings, not its certificates, snapshots or generated folders.
    foreach (glob("$root/.ddev/*.{yaml,yml,sh}", GLOB_BRACE) ?: [] as $file) {
        $files[] = $file;
    }

    foreach (['bin', 'app', 'bootstrap', 'config', 'database', 'resources', 'routes', 'tests'] as $directory) {
        if (! is_dir("$root/$directory")) {
            continue;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$root/$directory", FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getSize() < 1_000_000 && $file->getPathname() !== __FILE__ && ! str_contains($file->getPathname(), '/bootstrap/cache/')) {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

it('does not depend on, mount, seed or mention a site-specific profile package', function () {
    $root = dirname(__DIR__, 2);
    $found = [];

    foreach (guardedFiles() as $file) {
        $contents = file_get_contents($file);

        if ($contents !== false && preg_match(SITE_PROFILE_PATTERN, $contents) === 1) {
            $found[] = ltrim(substr($file, strlen($root)), '/');
        }
    }

    expect($found)->toBe([], 'The demo must stay free of any site-specific profile; these files mention one: '.implode(', ', $found));
});

it('looks at the places a profile could be wired in', function () {
    $names = array_map('basename', guardedFiles());

    expect($names)->toContain('composer.json', 'composer.lock', 'AdminPanelProvider.php', 'DemoSeeder.php', 'dev-link', 'docker-compose.dev-link.yaml');
});

it('would notice a profile package named in composer.json', function () {
    $sample = '{"require": {"filagora/filagora-'.'zencart": "^0.3"}}';

    expect(preg_match(SITE_PROFILE_PATTERN, $sample))->toBe(1)
        ->and(preg_match(SITE_PROFILE_PATTERN, '{"require": {"filagora/filagora-memberships": "^0.1"}}'))->toBe(0);
});
