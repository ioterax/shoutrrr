<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Migrations\Migrator;
use UnexpectedValueException;

class MigrationManifest
{
    public function __construct(private Migrator $migrator) {}

    public function hash(): string
    {
        return hash('sha256', json_encode($this->files(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, string> */
    public function files(): array
    {
        $files = [];
        $names = [];
        $paths = array_unique([...$this->migrator->paths(), database_path('migrations')]);

        foreach ($paths as $path) {
            $this->relativePath($path);
            $migrations = str_ends_with($path, '.php') ? [$path] : glob($path.'/*_*.php');

            if ($migrations === false) {
                throw new UnexpectedValueException('Cannot enumerate migration files.');
            }

            foreach ($migrations as $migration) {
                $relative = $this->relativePath($migration);
                $name = $this->migrator->getMigrationName($migration);

                if (isset($names[$name]) && $names[$name] !== $relative) {
                    throw new UnexpectedValueException('Duplicate migration name: '.$name);
                }

                $names[$name] = $relative;
                $files[$relative] = $this->fileHash($migration);
            }
        }

        foreach ($this->schemaFiles() as $schema) {
            $files[$this->relativePath($schema)] = $this->fileHash($schema);
        }

        if ($names === []) {
            throw new UnexpectedValueException('No migrations found; web-only release is blocked.');
        }

        ksort($files, SORT_STRING);

        return $files;
    }

    /** @return list<string> */
    private function schemaFiles(): array
    {
        $path = database_path('schema');

        if (! file_exists($path) && ! is_link($path)) {
            return [];
        }

        $this->relativePath($path);
        $files = glob($path.'/*.sql');

        if (! is_dir($path) || $files === false) {
            throw new UnexpectedValueException('Cannot enumerate schema dumps.');
        }

        return $files;
    }

    private function relativePath(string $path): string
    {
        $resolved = realpath($path);
        $root = realpath(base_path()).DIRECTORY_SEPARATOR;

        if ($resolved === false || ! is_readable($resolved) || ! str_starts_with($resolved, $root)) {
            throw new UnexpectedValueException('Migration input must be readable inside the application image.');
        }

        return substr($resolved, strlen($root));
    }

    private function fileHash(string $path): string
    {
        $hash = hash_file('sha256', $path);

        if ($hash === false) {
            throw new UnexpectedValueException('Cannot hash migration input.');
        }

        return $hash;
    }
}
