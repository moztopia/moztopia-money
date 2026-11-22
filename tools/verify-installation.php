<?php

declare(strict_types=1);

// Execute in an isolated supported PHP/Composer runtime. All consumers are temporary.
$root = dirname(__DIR__);
$temporary = sys_get_temp_dir().'/moztopia-money-install-'.bin2hex(random_bytes(6));
mkdir($temporary, 0700, true);
$run = static function (array $arguments, string $directory) use ($temporary): void {
    $process = proc_open($arguments, [STDIN, STDOUT, STDERR], $pipes, $directory);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Installation probe command failed; inspect retained workspace '.$temporary);
    }
};
$write = static function (string $path, array $value): void {
    file_put_contents($path, json_encode($value, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
};
$run(['composer', 'archive', '--format=tar', '--dir='.$temporary, '--file=money', '--no-interaction'], $root);
$metadata = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
unset($metadata['require-dev'], $metadata['autoload-dev'], $metadata['scripts'], $metadata['config']);
$metadata['version'] = '0.0.0'; // Synthetic local artifact identity, never a published version.
$metadata['dist'] = ['type' => 'tar', 'url' => 'file://'.$temporary.'/money.tar', 'shasum' => sha1_file($temporary.'/money.tar')];
$repository = ['type' => 'package', 'package' => $metadata];
$laravel = in_array('--laravel', $argv, true);
if ($laravel) {
    $run(['composer', 'create-project', '--no-install', '--no-scripts', '--no-interaction', 'laravel/laravel', $temporary.'/consumer', '^13.0'], $temporary);
    $manifest = json_decode(file_get_contents($temporary.'/consumer/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    // Only runtime consumer packages: no SDK Testbench or application development tools.
    unset($manifest['require-dev']);
} else {
    mkdir($temporary.'/consumer');
    $manifest = ['name' => 'verification/standalone', 'require' => []];
}
$manifest['repositories'] = [$repository];
$manifest['require']['moztopia/money'] = '0.0.0';
$write($temporary.'/consumer/composer.json', $manifest);
$run(['composer', 'install', '--no-dev', '--prefer-dist', '--no-interaction', '--no-progress'], $temporary.'/consumer');
$run(['composer', 'check-platform-reqs', '--no-dev'], $temporary.'/consumer');
$installed = $temporary.'/consumer/vendor/moztopia/money';
foreach (['AGENTS.md', 'llms.txt', 'docs/AGENT_INTEGRATION.md', 'docs/CONSUMING_AGENTS.md', 'docs/QUICKSTART.md',
    'docs/API.md', 'docs/CONTRACTS.md', 'resources/currencies.json', 'src/Money.php'] as $file) {
    if (!is_file($installed.'/'.$file) || hash_file('sha256', $root.'/'.$file) !== hash_file('sha256', $installed.'/'.$file)) {
        throw new RuntimeException('Packaged source or agent documentation differs: '.$file);
    }
}
if (!$laravel) {
    putenv('MONEY_AUTOLOAD='.$temporary.'/consumer/vendor/autoload.php');
    $run([PHP_BINARY, $root.'/tests/Fixtures/standalone-smoke.php'], $root);
} else {
    if (!is_file($temporary.'/consumer/.env')) {
        copy($temporary.'/consumer/.env.example', $temporary.'/consumer/.env');
    }
    touch($temporary.'/consumer/database/database.sqlite');
    putenv('MONEY_LARAVEL_APP='.$temporary.'/consumer');
    putenv('APP_ENV=testing');
    putenv('CACHE_STORE=array');
    putenv('DB_CONNECTION=sqlite');
    putenv('DB_DATABASE='.$temporary.'/consumer/database/database.sqlite');
    $run([PHP_BINARY, 'artisan', 'package:discover', '--ansi'], $temporary.'/consumer');
    $run([PHP_BINARY, $root.'/tests/Fixtures/laravel-smoke.php'], $root);
    $run([PHP_BINARY, 'artisan', 'config:cache'], $temporary.'/consumer');
    $run([PHP_BINARY, $root.'/tests/Fixtures/laravel-smoke.php'], $root);
    $run([PHP_BINARY, 'artisan', 'config:clear'], $temporary.'/consumer');
}
echo "Immutable archive installation verified in ".$temporary."\n";
echo 'Archive SHA-256: '.hash_file('sha256', $temporary.'/money.tar')."\n";
