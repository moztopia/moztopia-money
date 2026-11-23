<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

// Read this generator as executable tooling, not as a consumer integration API.
$root = dirname(__DIR__);
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src'));
$classes = [];
foreach ($files as $file) {
    $path = $file->getPathname();
    if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($path, '/Internal/')) {
        continue;
    }
    $classes[] = 'Moztopia\\Money\\'.str_replace('/', '\\', substr($path, strlen($root.'/src/'), -4));
}
sort($classes);
$type = static function (?ReflectionType $type, string $declaring): string {
    // PHP 8.5 resolves self during reflection; normalize older runtimes identically.
    return preg_replace_callback('/\bself\b/', static fn (): string => $declaring, (string) $type);
};
$summary = static function (string|false $comment): string {
    if ($comment === false) {
        return '';
    }
    preg_match('/\/\*\*\s*\R\s*\* (.+)/', $comment, $match);
    return $match[1] ?? '';
};
$output = "# Public API reference\n\nGenerated from declared public signatures by `php tools/api-reference.php`.\nRun with `--check` to detect drift. Internal classes and inherited framework\nmethods are excluded. Class types are fully qualified, with `self` resolved to\nthe declaring class consistently across PHP versions. `mixed` monetary inputs\ndeliberately reject floats at runtime, even when strict types are disabled. Read\n[CONTRACTS.md](CONTRACTS.md) and the [agent guide](AGENT_INTEGRATION.md) for semantics\nand failure policies. These are signature declarations, not standalone snippets.\n\n";
foreach ($classes as $name) {
    $class = new ReflectionClass($name);
    $output .= '## `'.$name."`\n\n".$summary($class->getDocComment())."\n\n```php\n";
    if ($class->isEnum()) {
        foreach ((new ReflectionEnum($name))->getCases() as $case) {
            $output .= 'case '.$case->name;
            if ($case instanceof ReflectionEnumBackedCase) {
                $output .= ' = '.var_export($case->getBackingValue(), true);
            }
            $output .= ";\n";
        }
    }
    foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
        if ($property->getDeclaringClass()->name !== $name) {
            continue;
        }
        $output .= 'public '.($property->isReadOnly() ? 'readonly ' : '').$type($property->getType(), $name).' $'.$property->name.";\n";
    }
    foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->name !== $name || ($class->isEnum() && in_array($method->name, ['cases', 'from', 'tryFrom'], true))) {
            continue;
        }
        $parameters = [];
        foreach ($method->getParameters() as $parameter) {
            $declaration = $type($parameter->getType(), $name).' '.($parameter->isVariadic() ? '...' : '').'$'.$parameter->name;
            if ($parameter->isDefaultValueAvailable()) {
                $default = $parameter->getDefaultValue();
                $declaration .= ' = '.match (true) {
                    $parameter->isDefaultValueConstant() => $parameter->getDefaultValueConstantName(),
                    $default instanceof UnitEnum => $default::class.'::'.$default->name,
                    is_object($default) => 'new '.$default::class.'()',
                    $default === [] => '[]',
                    default => var_export($default, true),
                };
            }
            $parameters[] = $declaration;
        }
        $return = $type($method->getReturnType(), $name);
        $output .= 'public '.($method->isStatic() ? 'static ' : '').'function '.$method->name.'('.implode(', ', $parameters).')'.($return === '' ? '' : ': '.$return).";\n";
    }
    $output .= "```\n\n";
}
$output .= "by-dolly\n";
$destination = $root.'/docs/API.md';
if (in_array('--check', $argv, true)) {
    if (!is_file($destination) || file_get_contents($destination) !== $output) {
        fwrite(STDERR, "Public API reference is stale. Run php tools/api-reference.php.\n");
        exit(1);
    }
    echo "Public API reference matches source.\n";
} else {
    file_put_contents($destination, $output);
    echo "Generated docs/API.md.\n";
}
