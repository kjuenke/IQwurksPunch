<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class DistributionPackageManifestService
{
    private string $projectRoot;

    private DistributionPackagePlanService $plans;


    public function __construct(
        ?string $projectRoot = null,
        ?DistributionPackagePlanService $plans = null
    )
    {
        $projectRoot =
            $projectRoot
            ??
            dirname(
                __DIR__,
                2
            );


        $projectRoot =
            rtrim(
                trim(
                    $projectRoot
                ),
                DIRECTORY_SEPARATOR
            );


        if ($projectRoot === '') {
            throw new RuntimeException(
                'The distribution-manifest project root cannot be empty.'
            );
        }


        if (!is_dir($projectRoot)) {
            throw new RuntimeException(
                'The distribution-manifest project root does not exist: '
                .
                $projectRoot
            );
        }


        $resolvedRoot =
            realpath(
                $projectRoot
            );


        if ($resolvedRoot === false) {
            throw new RuntimeException(
                'The distribution-manifest project root could not be resolved.'
            );
        }


        $this->projectRoot =
            $resolvedRoot;


        $this->plans =
            $plans
            ??
            new DistributionPackagePlanService(
                $this->projectRoot
            );
    }


    /**
     * @return array<string,mixed>
     */
    public function manifest(
        ?string $version = null
    ): array
    {
        $startedAt =
            microtime(
                true
            );


        $plan =
            $this->plans->plan(
                $version
            );


        if (
            (
                $plan['can_build']
                ??
                false
            )
            !==
            true
        ) {
            throw new RuntimeException(
                'The distribution package manifest cannot be created because the package plan is blocked.'
            );
        }


        $excludedPatterns =
            $plan['excluded_paths']
            ??
            [];


        if (!is_array($excludedPatterns)) {
            throw new RuntimeException(
                'The distribution package plan did not provide valid exclusion rules.'
            );
        }


        $entries = [];

        $excludedEntries = [];

        $unsafeEntryCount = 0;


        $includePaths =
            $plan['include_paths']
            ??
            [];


        if (!is_array($includePaths)) {
            throw new RuntimeException(
                'The distribution package plan did not provide valid include paths.'
            );
        }


        foreach ($includePaths as $includePath) {

            $relativePath =
                $this->normalizeRelativePath(
                    (string)$includePath
                );


            if ($relativePath === '') {
                continue;
            }


            $absolutePath =
                $this->projectRoot
                .
                DIRECTORY_SEPARATOR
                .
                str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $relativePath
                );


            if (
                $this->isExcluded(
                    $relativePath,
                    $excludedPatterns
                )
            ) {
                $excludedEntries[] = [
                    'path' =>
                        $relativePath,

                    'reason' =>
                        'excluded_by_rule',

                    'pattern' =>
                        $this->matchingPattern(
                            $relativePath,
                            $excludedPatterns
                        )
                ];


                continue;
            }


            if (
                !file_exists(
                    $absolutePath
                )
                &&
                !is_link(
                    $absolutePath
                )
            ) {
                continue;
            }


            if (is_link($absolutePath)) {
                $unsafeEntryCount++;


                $excludedEntries[] = [
                    'path' =>
                        $relativePath,

                    'reason' =>
                        'symbolic_link_not_allowed',

                    'pattern' =>
                        null
                ];


                continue;
            }


            if (is_dir($absolutePath)) {

                $entries[$relativePath] =
                    $this->directoryEntry(
                        $relativePath,
                        $absolutePath,
                        false
                    );


                $this->walkDirectory(
                    $absolutePath,
                    $relativePath,
                    $excludedPatterns,
                    $entries,
                    $excludedEntries,
                    $unsafeEntryCount
                );


                continue;
            }


            if (is_file($absolutePath)) {

                $entries[$relativePath] =
                    $this->fileEntry(
                        $relativePath,
                        $absolutePath
                    );
            }
        }


        $runtimeDirectories =
            $plan['runtime_directories']
            ??
            [];


        if (!is_array($runtimeDirectories)) {
            throw new RuntimeException(
                'The distribution package plan did not provide valid runtime directories.'
            );
        }


        foreach ($runtimeDirectories as $runtimeDirectory) {

            $relativePath =
                $this->normalizeRelativePath(
                    (string)$runtimeDirectory
                );


            if ($relativePath === '') {
                continue;
            }


            if (isset($entries[$relativePath])) {
                continue;
            }


            $entries[$relativePath] =
                $this->directoryEntry(
                    $relativePath,
                    null,
                    true
                );
        }


        ksort(
            $entries,
            SORT_STRING
        );


        usort(
            $excludedEntries,
            static fn (
                array $left,
                array $right
            ): int =>
                strcmp(
                    (string)(
                        $left['path']
                        ??
                        ''
                    ),
                    (string)(
                        $right['path']
                        ??
                        ''
                    )
                )
        );


        $manifestEntries =
            array_values(
                $entries
            );


        $fileCount =
            count(
                array_filter(
                    $manifestEntries,
                    static fn (
                        array $entry
                    ): bool =>
                        (
                            $entry['type']
                            ??
                            ''
                        )
                        ===
                        'file'
                )
            );


        $directoryCount =
            count(
                array_filter(
                    $manifestEntries,
                    static fn (
                        array $entry
                    ): bool =>
                        (
                            $entry['type']
                            ??
                            ''
                        )
                        ===
                        'directory'
                )
            );


        $generatedDirectoryCount =
            count(
                array_filter(
                    $manifestEntries,
                    static fn (
                        array $entry
                    ): bool =>
                        (
                            $entry['type']
                            ??
                            ''
                        )
                        ===
                        'directory'
                        &&
                        (
                            $entry['generated']
                            ??
                            false
                        )
                        ===
                        true
                )
            );


        $totalBytes =
            array_sum(
                array_map(
                    static fn (
                        array $entry
                    ): int =>
                        (int)(
                            $entry['size_bytes']
                            ??
                            0
                        ),
                    $manifestEntries
                )
            );


        $overallStatus =
            $unsafeEntryCount > 0
                ? 'WARN'
                : 'PASS';


        return [
            'mode' =>
                'manifest',

            'application_name' =>
                $plan['application_name']
                ??
                'IQwurksPunch',

            'application_version' =>
                $plan['application_version']
                ??
                null,

            'package_base_name' =>
                $plan['package_base_name']
                ??
                null,

            'package_file_name' =>
                $plan['package_file_name']
                ??
                null,

            'artifact_path' =>
                $plan['artifact_path']
                ??
                null,

            'entries' =>
                $manifestEntries,

            'excluded_patterns' =>
                array_values(
                    $excludedPatterns
                ),

            'excluded_entries' =>
                $excludedEntries,

            'manifest_sha256' =>
                $this->manifestDigest(
                    $manifestEntries
                ),

            'summary' => [
                'entries' =>
                    count(
                        $manifestEntries
                    ),

                'files' =>
                    $fileCount,

                'directories' =>
                    $directoryCount,

                'generated_directories' =>
                    $generatedDirectoryCount,

                'excluded_entries' =>
                    count(
                        $excludedEntries
                    ),

                'unsafe_entries' =>
                    $unsafeEntryCount,

                'total_bytes' =>
                    $totalBytes
            ],

            'overall_status' =>
                $overallStatus,

            'blocked' =>
                false,

            'can_build' =>
                true,

            'changes_made' =>
                false,

            'generated_at' =>
                date(
                    DATE_ATOM
                ),

            'duration_milliseconds' =>
                round(
                    (
                        microtime(
                            true
                        )
                        -
                        $startedAt
                    )
                    *
                    1000,
                    2
                )
        ];
    }


    /**
     * @param array<int,string> $excludedPatterns
     * @param array<string,array<string,mixed>> $entries
     * @param array<int,array<string,mixed>> $excludedEntries
     */
    private function walkDirectory(
        string $absoluteDirectory,
        string $relativeDirectory,
        array $excludedPatterns,
        array &$entries,
        array &$excludedEntries,
        int &$unsafeEntryCount
    ): void
    {
        $items =
            scandir(
                $absoluteDirectory
            );


        if ($items === false) {
            throw new RuntimeException(
                'The package source directory could not be read: '
                .
                $relativeDirectory
            );
        }


        foreach ($items as $item) {

            if (
                $item === '.'
                ||
                $item === '..'
            ) {
                continue;
            }


            $relativePath =
                $relativeDirectory
                .
                '/'
                .
                $item;


            $relativePath =
                $this->normalizeRelativePath(
                    $relativePath
                );


            $absolutePath =
                $absoluteDirectory
                .
                DIRECTORY_SEPARATOR
                .
                $item;


            $matchingPattern =
                $this->matchingPattern(
                    $relativePath,
                    $excludedPatterns
                );


            if ($matchingPattern !== null) {
                $excludedEntries[] = [
                    'path' =>
                        $relativePath,

                    'reason' =>
                        'excluded_by_rule',

                    'pattern' =>
                        $matchingPattern
                ];


                continue;
            }


            if (is_link($absolutePath)) {
                $unsafeEntryCount++;


                $excludedEntries[] = [
                    'path' =>
                        $relativePath,

                    'reason' =>
                        'symbolic_link_not_allowed',

                    'pattern' =>
                        null
                ];


                continue;
            }


            if (is_dir($absolutePath)) {

                $entries[$relativePath] =
                    $this->directoryEntry(
                        $relativePath,
                        $absolutePath,
                        false
                    );


                $this->walkDirectory(
                    $absolutePath,
                    $relativePath,
                    $excludedPatterns,
                    $entries,
                    $excludedEntries,
                    $unsafeEntryCount
                );


                continue;
            }


            if (is_file($absolutePath)) {

                $entries[$relativePath] =
                    $this->fileEntry(
                        $relativePath,
                        $absolutePath
                    );
            }
        }
    }


    /**
     * @return array<string,mixed>
     */
    private function directoryEntry(
        string $relativePath,
        ?string $absolutePath,
        bool $generated
    ): array
    {
        return [
            'path' =>
                $relativePath,

            'type' =>
                'directory',

            'source_path' =>
                $absolutePath,

            'generated' =>
                $generated,

            'size_bytes' =>
                0,

            'sha256' =>
                null,

            'mode' =>
                $generated
                    ? '0770'
                    : $this->fileMode(
                        $absolutePath
                    )
        ];
    }


    /**
     * @return array<string,mixed>
     */
    private function fileEntry(
        string $relativePath,
        string $absolutePath
    ): array
    {
        if (!is_readable($absolutePath)) {
            throw new RuntimeException(
                'The package source file is not readable: '
                .
                $relativePath
            );
        }


        $size =
            filesize(
                $absolutePath
            );


        if ($size === false) {
            throw new RuntimeException(
                'The package source file size could not be read: '
                .
                $relativePath
            );
        }


        $sha256 =
            hash_file(
                'sha256',
                $absolutePath
            );


        if ($sha256 === false) {
            throw new RuntimeException(
                'The package source file could not be hashed: '
                .
                $relativePath
            );
        }


        return [
            'path' =>
                $relativePath,

            'type' =>
                'file',

            'source_path' =>
                $absolutePath,

            'generated' =>
                false,

            'size_bytes' =>
                $size,

            'sha256' =>
                $sha256,

            'mode' =>
                $this->fileMode(
                    $absolutePath
                )
        ];
    }


    private function fileMode(
        ?string $absolutePath
    ): ?string
    {
        if ($absolutePath === null) {
            return null;
        }


        $permissions =
            fileperms(
                $absolutePath
            );


        if ($permissions === false) {
            return null;
        }


        return
            substr(
                sprintf(
                    '%04o',
                    $permissions
                    &
                    07777
                ),
                -4
            );
    }


    /**
     * @param array<int,string> $patterns
     */
    private function isExcluded(
        string $relativePath,
        array $patterns
    ): bool
    {
        return
            $this->matchingPattern(
                $relativePath,
                $patterns
            )
            !==
            null;
    }


    /**
     * @param array<int,string> $patterns
     */
    private function matchingPattern(
        string $relativePath,
        array $patterns
    ): ?string
    {
        $relativePath =
            $this->normalizeRelativePath(
                $relativePath
            );


        foreach ($patterns as $pattern) {

            $pattern =
                $this->normalizeRelativePath(
                    (string)$pattern
                );


            if ($pattern === '') {
                continue;
            }


            if ($relativePath === $pattern) {
                return $pattern;
            }


            if (
                !str_contains(
                    $pattern,
                    '*'
                )
                &&
                !str_contains(
                    $pattern,
                    '?'
                )
                &&
                str_starts_with(
                    $relativePath,
                    $pattern
                    .
                    '/'
                )
            ) {
                return $pattern;
            }


            if (
                fnmatch(
                    $pattern,
                    $relativePath,
                    FNM_PATHNAME
                )
            ) {
                return $pattern;
            }
        }


        return null;
    }


    private function normalizeRelativePath(
        string $path
    ): string
    {
        $path =
            str_replace(
                '\\',
                '/',
                trim(
                    $path
                )
            );


        $path =
            preg_replace(
                '#/+#',
                '/',
                $path
            )
            ??
            $path;


        return
            trim(
                $path,
                '/'
            );
    }


    /**
     * @param array<int,array<string,mixed>> $entries
     */
    private function manifestDigest(
        array $entries
    ): string
    {
        $lines = [];


        foreach ($entries as $entry) {

            $lines[] =
                implode(
                    '|',
                    [
                        (string)(
                            $entry['path']
                            ??
                            ''
                        ),

                        (string)(
                            $entry['type']
                            ??
                            ''
                        ),

                        (
                            $entry['generated']
                            ??
                            false
                        )
                            ? 'generated'
                            : 'source',

                        (string)(
                            $entry['mode']
                            ??
                            ''
                        ),

                        (string)(
                            $entry['size_bytes']
                            ??
                            0
                        ),

                        (string)(
                            $entry['sha256']
                            ??
                            ''
                        )
                    ]
                );
        }


        return
            hash(
                'sha256',
                implode(
                    "\n",
                    $lines
                )
            );
    }
}
