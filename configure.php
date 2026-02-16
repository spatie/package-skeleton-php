#!/usr/bin/env php
<?php

function supportsAnsi(): bool
{
    if (getenv('NO_COLOR') !== false) {
        return false;
    }

    if (PHP_OS_FAMILY === 'Windows') {
        return (function_exists('sapi_windows_vt100_support')
            && sapi_windows_vt100_support(STDOUT))
            || getenv('ANSICON') !== false
            || getenv('ConEmuANSI') === 'ON'
            || str_starts_with((string) getenv('TERM'), 'xterm');
    }

    return stream_isatty(STDOUT);
}

function ansi(string $text, string $code): string
{
    if (! supportsAnsi()) {
        return $text;
    }

    return "\033[{$code}m{$text}\033[0m";
}

function bold(string $text): string
{
    return ansi($text, '1');
}

function dim(string $text): string
{
    return ansi($text, '2');
}

function green(string $text): string
{
    return ansi($text, '32');
}

function yellow(string $text): string
{
    return ansi($text, '33');
}

function writeln(string $line): void
{
    echo $line.PHP_EOL;
}

function ask(string $question, string $default = ''): string
{
    $prompt = bold($question);

    if ($default) {
        $prompt .= ' '.dim("({$default})");
    }

    $answer = readline('  '.$prompt.': ');

    if (! $answer) {
        return $default;
    }

    return $answer;
}

function askWithOptions(string $question, array $options, string $default = ''): string
{
    $suggestions = implode('/', array_map(
        fn (string $option) => $option === $default ? strtoupper($option) : $option,
        $options,
    ));

    $answer = ask("{$question} ({$suggestions})");

    $validOptions = implode(', ', $options);

    while (! in_array($answer, $options)) {
        if ($default && $answer === '') {
            $answer = $default;

            break;
        }

        writeln(PHP_EOL."  Please pick one of the following options: {$validOptions}");

        $answer = ask("{$question} ({$suggestions})");
    }

    if (! $answer) {
        $answer = $default;
    }

    return $answer;
}

function confirm(string $question, bool $default = false): bool
{
    $answer = ask($question.' '.($default ? 'Y/n' : 'y/N'));

    if (! $answer) {
        return $default;
    }

    return strtolower($answer) === 'y';
}

function run(string $command): string
{
    return trim(shell_exec($command) ?? '');
}

function str_after(string $subject, string $search): string
{
    $pos = strrpos($subject, $search);

    if ($pos === false) {
        return $subject;
    }

    return substr($subject, $pos + strlen($search));
}

function slugify(string $subject): string
{
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $subject), '-'));
}

function title_case(string $subject): string
{
    return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $subject)));
}

function replace_in_file(string $file, array $replacements): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        str_replace(
            array_keys($replacements),
            array_values($replacements),
            $contents
        )
    );
}

function removeReadmeParagraphs(string $file): void
{
    $contents = file_get_contents($file);

    file_put_contents(
        $file,
        preg_replace('/<!--delete-->.*<!--\/delete-->/s', '', $contents) ?: $contents
    );
}

function normalizePath(string $path): string
{
    return str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function getFilesWithPlaceholders(): array
{
    $directory = new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($directory);

    $skipDirs = ['.git', 'vendor', 'node_modules'];
    $scriptBasename = basename(__FILE__);
    $placeholders = [':author', ':vendor', ':package', 'VendorName', 'skeleton', 'Skeleton', 'vendor_name', 'vendor_slug', 'author@domain.com'];

    $files = [];

    foreach ($iterator as $file) {
        if (! $file->isFile()) {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = str_replace(__DIR__.DIRECTORY_SEPARATOR, '', $path);

        foreach ($skipDirs as $skipDir) {
            if (str_starts_with($relativePath, $skipDir.DIRECTORY_SEPARATOR)) {
                continue 2;
            }
        }

        if ($file->getBasename() === $scriptBasename) {
            continue;
        }

        $contents = file_get_contents($path);
        foreach ($placeholders as $placeholder) {
            if (stripos($contents, $placeholder) !== false) {
                $files[] = $path;
                break;
            }
        }
    }

    return $files;
}

function setupTestingLibrary(string $testingLibrary): void
{
    if ($testingLibrary === 'pest') {
        unlink(__DIR__.'/tests/ExampleTestPhpunit.php');
        unlink(__DIR__.'/.github/workflows/run-tests-phpunit.yml');

        rename(
            from: __DIR__.'/tests/ExampleTestPest.php',
            to: __DIR__.'/tests/ExampleTest.php'
        );

        rename(
            from: __DIR__.'/.github/workflows/run-tests-pest.yml',
            to: __DIR__.'/.github/workflows/run-tests.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_testing' => '"pestphp/pest": "^4.0"',
            ':scripts_testing' => '"test": "vendor/bin/pest",
            "test-coverage": "vendor/bin/pest --coverage"',
            ':plugins_testing' => '"pestphp/pest-plugin": true',
        ]);
    } elseif ($testingLibrary === 'phpunit') {
        unlink(__DIR__.'/tests/ExampleTestPest.php');
        unlink(__DIR__.'/tests/ArchTest.php');
        unlink(__DIR__.'/tests/Pest.php');
        unlink(__DIR__.'/.github/workflows/run-tests-pest.yml');

        rename(
            from: __DIR__.'/tests/ExampleTestPhpunit.php',
            to: __DIR__.'/tests/ExampleTest.php'
        );

        rename(
            from: __DIR__.'/.github/workflows/run-tests-phpunit.yml',
            to: __DIR__.'/.github/workflows/run-tests.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_testing' => '"phpunit/phpunit": "^10.3.2"',
            ':scripts_testing' => '"test": "vendor/bin/phpunit",
            "test-coverage": "vendor/bin/phpunit --coverage"',
            ':plugins_testing,' => '', // We need to remove the comma here as well, since there's nothing to add
        ]);
    }
}

function setupCodeStyleLibrary(string $codeStyleLibrary): void
{
    if ($codeStyleLibrary === 'pint') {
        unlink(__DIR__.'/.github/workflows/fix-php-code-style-issues-cs-fixer.yml');

        rename(
            from: __DIR__.'/.github/workflows/fix-php-code-style-issues-pint.yml',
            to: __DIR__.'/.github/workflows/fix-php-code-style-issues.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_codestyle' => '"laravel/pint": "^1.0"',
            ':scripts_codestyle' => '"format": "vendor/bin/pint"',
            ':plugins_testing' => '',
        ]);

        unlink(__DIR__.'/.php-cs-fixer.dist.php');
    } elseif ($codeStyleLibrary === 'cs fixer') {
        unlink(__DIR__.'/.github/workflows/fix-php-code-style-issues-pint.yml');

        rename(
            from: __DIR__.'/.github/workflows/fix-php-code-style-issues-cs-fixer.yml',
            to: __DIR__.'/.github/workflows/fix-php-code-style-issues.yml'
        );

        replace_in_file(__DIR__.'/composer.json', [
            ':require_dev_codestyle' => '"friendsofphp/php-cs-fixer": "^3.21.1"',
            ':scripts_codestyle' => '"format": "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --allow-risky=yes"',
            ':plugins_testing' => '',
        ]);
    }
}

writeln('');

$logoLines = [
    '  ███████ ██████   █████  ████████ ████ ████████',
    '  ██      ██   ██ ██   ██    ██     ██  ██',
    '  ███████ ██████  ███████    ██     ██  ██████',
    '       ██ ██      ██   ██    ██     ██  ██',
    '  ███████ ██      ██   ██    ██    ████ ████████',
];

$gradientColors = [
    '38;2;100;200;225',
    '38;2;62;170;200',
    '38;2;35;140;175',
    '38;2;25;117;147',
    '38;2;15;90;115',
];

foreach ($logoLines as $i => $line) {
    writeln(supportsAnsi() ? "\033[{$gradientColors[$i]}m{$line}\033[0m" : $line);
}

writeln('');

if (supportsAnsi()) {
    writeln("  \033[48;2;25;117;147m\033[97m ✦ PHP Package Skeleton :: spatie.be ✦ \033[0m");
} else {
    writeln('  ✦ PHP Package Skeleton :: spatie.be ✦');
}

writeln('');
writeln('  Thanks for using the Spatie PHP package skeleton!');
writeln('  Let\'s get your new package configured.');
writeln('');

writeln(bold('  Author'));
writeln(dim('  Used for composer.json credits and the README.'));
writeln('');

$gitName = run('git config user.name');
$authorName = ask('Author name', $gitName);

$gitEmail = run('git config user.email');
$authorEmail = ask('Author email', $gitEmail);

$usernameGuess = explode(':', run('git config remote.origin.url'))[1] ?? '';
$usernameGuess = dirname($usernameGuess);
$usernameGuess = basename($usernameGuess);
$authorUsername = ask('Author username', $usernameGuess);

writeln('');
writeln(bold('  Vendor'));
writeln(dim('  The vendor is your brand on Packagist, e.g. in spatie/ray the vendor is "spatie".'));
writeln('');

$vendorName = ask('Vendor name', $authorUsername);
$vendorSlug = slugify($vendorName);
writeln('');
writeln(dim('  The PHP namespace prefix for your package, e.g. Spatie\\Ray.'));
$vendorNamespace = ucwords($vendorName);
$vendorNamespace = ask('Vendor namespace', $vendorNamespace);

writeln('');
writeln(bold('  Package'));
writeln('');

$currentDirectory = getcwd();
$folderName = basename($currentDirectory);

$packageName = ask('Package name', $folderName);
$packageSlug = slugify($packageName);

$className = title_case($packageName);
$className = ask('Class name', $className);
$description = ask('Package description', "This is my package {$packageSlug}");

writeln('');
writeln(bold('  Tooling'));
writeln('');

writeln(dim('  Pest is a testing framework with a focus on simplicity. PHPUnit is the classic choice.'));
$testingLibrary = askWithOptions(
    'Which testing library do you want to use?',
    ['pest', 'phpunit'],
    'pest',
);

writeln('');
writeln(dim('  Pint is Laravel\'s opinionated code formatter. CS Fixer offers more configuration options.'));
$codeStyleLibrary = askWithOptions(
    'Which code style library do you want to use?',
    ['pint', 'cs fixer'],
    'pint',
);

writeln('');
writeln(bold('  Summary'));
writeln('');
writeln("  Author      {$authorName} ({$authorUsername}, {$authorEmail})");
writeln("  Vendor      {$vendorName} ({$vendorSlug})");
writeln("  Package     {$packageSlug}");
writeln("  Description {$description}");
writeln("  Namespace   {$vendorNamespace}\\{$className}");
writeln("  Class       {$className}");
writeln('');
writeln('  Tooling');
writeln("  Testing     {$testingLibrary}");
writeln("  Code style  {$codeStyleLibrary}");
writeln('');

if (! confirm('Modify files?', true)) {
    exit(1);
}

writeln('');

$files = getFilesWithPlaceholders();

foreach ($files as $file) {
    replace_in_file($file, [
        ':author_name' => $authorName,
        ':author_username' => $authorUsername,
        'author@domain.com' => $authorEmail,
        ':vendor_name' => $vendorName,
        ':vendor_slug' => $vendorSlug,
        'VendorName' => $vendorNamespace,
        ':package_name' => $packageName,
        ':package_slug' => $packageSlug,
        'Skeleton' => $className,
        ':package_description' => $description,
    ]);

    match (true) {
        str_contains($file, normalizePath('src/SkeletonClass.php')) => rename($file, normalizePath('./src/'.$className.'Class.php')),
        str_contains($file, 'README.md') => removeReadmeParagraphs($file),
        default => null,
    };
}

writeln(green('  ✓ Updated '.count($files).' files'));

setupTestingLibrary($testingLibrary);
writeln(green("  ✓ Configured {$testingLibrary} for testing"));

setupCodeStyleLibrary($codeStyleLibrary);
writeln(green("  ✓ Configured {$codeStyleLibrary} for code style"));

confirm('Execute `composer install` and run tests?', true) && run('composer install && composer test');

writeln('');
confirm('Let this script delete itself?', true) && unlink(__FILE__);

writeln('');
writeln(green(bold('  ✨ You\'re all set! Happy building!')));
writeln('');
