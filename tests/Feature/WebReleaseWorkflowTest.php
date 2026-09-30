<?php

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

it('requires explicit web-only gates on every Job step and enables startup checks', function (): void {
    $workflow = Yaml::parseFile(base_path('.github/workflows/deploy.yml'));
    expect($workflow['on']['workflow_dispatch']['inputs']['web_only']['default'])->toBeTrue();
    $mutations = 0;

    foreach ($workflow['jobs']['deploy']['steps'] as $step) {
        $script = $step['run'] ?? '';
        if (preg_match('/gcloud run jobs (update|execute)/', $script)) {
            expect($step['if'])->toBe('${{ !inputs.web_only }}');
            $mutations++;
        }
        if (($step['id'] ?? '') === 'config') {
            expect($step['env']['WEB_ONLY'])->toBe('${{ inputs.web_only }}')
                ->and($script)->toContain('WEB_ONLY_RELEASE:', 'WEB_RELEASE_MIGRATION_HASH:');
        }
    }

    expect($mutations)->toBe(3);
});

it('selects actual production traffic even when a newer ready revision is unserved', function (): void {
    $process = new Process(['jq', '-er', '-f', base_path('.github/serving-revision.jq')]);
    $process->setInput(json_encode(['status' => [
        'latestReadyRevisionName' => 'shoutrrr-new-unserved',
        'traffic' => [
            ['revisionName' => 'shoutrrr-old', 'percent' => 100],
            ['revisionName' => 'shoutrrr-new-unserved', 'tag' => 'preview'],
        ],
    ]], JSON_THROW_ON_ERROR));
    $process->mustRun();

    expect(trim($process->getOutput()))->toBe('shoutrrr-old');
});

it('rejects ambiguous or unpinned rollback baselines', function (array $traffic): void {
    $process = new Process(['jq', '-er', '-f', base_path('.github/serving-revision.jq')]);
    $process->setInput(json_encode(['status' => ['traffic' => $traffic]], JSON_THROW_ON_ERROR));
    $process->run();

    expect($process->isSuccessful())->toBeFalse();
})->with([
    'split traffic' => [[['revisionName' => 'shoutrrr-a', 'percent' => 90], ['revisionName' => 'shoutrrr-b', 'percent' => 10]]],
    'latest alias' => [[['revisionName' => 'shoutrrr-a', 'percent' => 100, 'latestRevision' => true]]],
    'missing revision' => [[['percent' => 100]]],
    'empty traffic' => [[]],
]);

it('blocks the web listener when startup verification fails', function (string $mode, string $hash, int $gateExit, bool $starts): void {
    $directory = sys_get_temp_dir().'/shoutrrr-startup-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($directory);
    File::put($directory.'/php', <<<'SH'
#!/bin/sh
echo "$*" >> "$STARTUP_LOG"
case "$*" in
  *production:assert-web-release*) exit "$GATE_EXIT" ;;
esac
SH);
    chmod($directory.'/php', 0700);

    try {
        $process = new Process(['sh', base_path('docker/app-command.sh')], base_path(), [
            'PATH' => $directory.':'.getenv('PATH'),
            'WEB_ONLY_RELEASE' => $mode,
            'WEB_RELEASE_MIGRATION_HASH' => $hash,
            'GATE_EXIT' => (string) $gateExit,
            'STARTUP_LOG' => $directory.'/calls',
        ]);
        $process->run();
        expect($process->isSuccessful())->toBe($starts)
            ->and(str_contains(File::get($directory.'/calls'), 'octane:start'))->toBe($starts);
    } finally {
        File::deleteDirectory($directory);
    }
})->with([
    'approved' => ['true', 'manifest', 0, true],
    'failed database check' => ['true', 'manifest', 1, false],
    'missing manifest' => ['true', '', 0, false],
    'invalid mode' => ['invalid', 'manifest', 0, false],
    'ordinary release' => ['false', '', 1, true],
]);

it('rejects Cloud Run overrides that could bypass the startup gates', function (array $containers, bool $accepted): void {
    $process = new Process(['jq', '-e', '-f', base_path('.github/web-startup.jq')]);
    $process->setInput(json_encode(['spec' => ['template' => ['spec' => ['containers' => $containers]]]], JSON_THROW_ON_ERROR));
    $process->run();

    expect($process->isSuccessful())->toBe($accepted);
})->with([
    'image defaults' => [[['name' => 'shoutrrr']], true],
    'empty overrides' => [[['command' => [], 'args' => []]], true],
    'alternate command' => [[['command' => ['php', 'artisan', 'octane:start']]], false],
    'alternate args' => [[['args' => ['php', 'artisan', 'octane:start']]], false],
    'multiple containers' => [[['name' => 'shoutrrr'], ['name' => 'sidecar']], false],
    'no containers' => [[], false],
]);
