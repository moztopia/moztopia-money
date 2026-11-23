<?php

declare(strict_types=1);

it('keeps every declared public signature and property independent of the private engine', function () {
    $root = dirname(__DIR__, 2).'/src/';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($files as $file) {
        $path = $file->getPathname();
        if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($path, '/Internal/')) {
            continue;
        }
        $name = 'Moztopia\\Money\\'.str_replace('/', '\\', substr($path, strlen($root), -4));
        $class = new ReflectionClass($name);
        foreach ($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->name !== $name) {
                continue;
            }
            expect((string) $method->getReturnType())->not->toContain('Brick\\');
            if (!$method->isConstructor()) {
                expect($method->getReturnType())->not->toBeNull();
            }
            foreach ($method->getParameters() as $parameter) {
                expect((string) $parameter->getType())->not->toContain('Brick\\');
            }
        }
        foreach ($class->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            expect((string) $property->getType())->not->toContain('Brick\\');
        }
    }
});

it('prevents float literals and numeric float casts throughout owned monetary source', function () {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));
    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $floatingTokens = array_filter(
            token_get_all(file_get_contents($file->getPathname())),
            fn (array|string $token): bool => is_array($token) && in_array($token[0], [T_DNUMBER, T_DOUBLE_CAST], true)
        );
        expect($floatingTokens)->toBeEmpty();
    }
});
