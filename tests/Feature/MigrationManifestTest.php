<?php

use App\Support\MigrationManifest;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->manifestDirectory = storage_path('framework/testing/manifest-'.bin2hex(random_bytes(8)));
    $this->migrationDirectory = $this->manifestDirectory.'/migrations';
    $this->schemaDirectory = $this->manifestDirectory.'/schema';
    File::ensureDirectoryExists($this->migrationDirectory);
    File::ensureDirectoryExists($this->schemaDirectory);
    $this->originalDatabasePath = database_path();
    $this->app->useDatabasePath($this->manifestDirectory);
    File::put($this->manifestDirectory.'/migrations/2026_01_01_000000_example.php', '<?php throw new Exception("Never execute migrations while hashing");');
});

afterEach(function (): void {
    $this->app->useDatabasePath($this->originalDatabasePath);
    File::deleteDirectory($this->manifestDirectory);
});

it('detects changes additions removals and dumps without loading migration code', function (): void {
    $manifest = app(MigrationManifest::class);
    $original = $manifest->hash();
    $path = $this->manifestDirectory.'/migrations/2026_01_02_000000_new.php';
    File::put($path, '<?php // new');
    expect($manifest->hash())->not->toBe($original);
    File::delete($path);
    expect($manifest->hash())->toBe($original);
    File::append($this->manifestDirectory.'/migrations/2026_01_01_000000_example.php', ' // changed');
    expect($manifest->hash())->not->toBe($original);
    $changed = $manifest->hash();
    File::put($this->manifestDirectory.'/schema/pgsql-schema.sql', 'CREATE TABLE example (id int);');
    expect($manifest->hash())->not->toBe($changed);
});

it('includes registered package migrations and rejects duplicate migration names', function (): void {
    $manifest = app(MigrationManifest::class);
    $original = $manifest->hash();
    File::ensureDirectoryExists($this->manifestDirectory.'/package');
    File::put($this->manifestDirectory.'/package/2026_01_02_000000_package.php', '<?php');
    app(Migrator::class)->path($this->manifestDirectory.'/package');
    expect($manifest->hash())->not->toBe($original);

    File::put($this->manifestDirectory.'/package/2026_01_01_000000_example.php', '<?php');
    expect(fn () => $manifest->hash())->toThrow(RuntimeException::class, 'Duplicate migration name');
});

it('rejects registered paths missing from the immutable image', function (): void {
    app(Migrator::class)->path($this->manifestDirectory.'/missing');

    expect(fn () => app(MigrationManifest::class)->hash())->toThrow(RuntimeException::class);
});

it('rejects files outside the image application directory', function (): void {
    symlink('/etc/hosts', $this->manifestDirectory.'/migrations/2026_01_02_000000_escape.php');

    expect(fn () => app(MigrationManifest::class)->hash())->toThrow(RuntimeException::class);
});

it('rejects an empty migration set', function (): void {
    File::deleteDirectory($this->migrationDirectory);
    File::ensureDirectoryExists($this->migrationDirectory);

    expect(fn () => app(MigrationManifest::class)->hash())->toThrow(RuntimeException::class, 'No migrations found');
});

it('rejects schema directories escaping the image or pointing at missing inputs', function (string $target): void {
    File::deleteDirectory($this->schemaDirectory);
    symlink($target, $this->schemaDirectory);

    expect(fn () => app(MigrationManifest::class)->hash())->toThrow(RuntimeException::class);
})->with(['/tmp', '/nonexistent-shoutrrr-schema']);
