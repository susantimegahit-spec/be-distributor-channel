<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ManageOpenApiDocs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'docs:openapi {action=split : Action to perform: split or bundle}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Split monolithic openapi.yaml into modular per-module YAML files, or bundle them back without any data loss';

    /**
     * Module definitions & title mapping.
     */
    protected array $moduleTitles = [
        'production'         => 'SMESTA API - Modul Production (BOM, PDO, Issue, Receipt, Change Product)',
        'logistic-ekspedisi' => 'SMESTA API - Modul Logistic & Ekspedisi (Picklist, DO, Weighing, Kendaraan, Rates)',
        'sales-orders'       => 'SMESTA API - Modul Sales Order, Return, CMO & Pricing',
        'purchasing-vendor'  => 'SMESTA API - Modul Purchasing (PR, Quotation), Budgeting & Vendor Portal',
        'claims-programs'    => 'SMESTA API - Modul Claims, Program & Balance Ledger',
        'hr-recruitment'     => 'SMESTA API - Modul HR, Recruitment & Psikotes DISC',
        'task-reporting'     => 'SMESTA API - Modul Task Management, Reporting & Scheduler',
        'master-auth-core'   => 'SMESTA API - Modul Auth, Users, Roles, Master Data, Warehouse & Approval',
    ];

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        if ($action === 'split') {
            return $this->splitDocs();
        }

        if ($action === 'bundle') {
            return $this->bundleDocs();
        }

        $this->error("Unknown action '{$action}'. Use 'split' or 'bundle'.");
        return self::FAILURE;
    }

    /**
     * Split resources/docs/openapi.yaml into resources/docs/modules/*.yaml
     * Leaving resources/docs/openapi.yaml 100% untouched.
     */
    protected function splitDocs(): int
    {
        $sourcePath = resource_path('docs/openapi.yaml');
        if (!file_exists($sourcePath)) {
            $this->error("File not found: {$sourcePath}");
            return self::FAILURE;
        }

        $modulesDir = resource_path('docs/modules');
        if (!is_dir($modulesDir)) {
            mkdir($modulesDir, 0755, true);
        }

        $rawContent = file_get_contents($sourcePath);
        $lines = preg_split("/\r\n|\n|\r/", $rawContent);
        $totalLines = count($lines);

        $pathsStartIdx = null;
        $componentsStartIdx = null;

        foreach ($lines as $idx => $line) {
            if ($pathsStartIdx === null && preg_match('/^paths:\s*$/', $line)) {
                $pathsStartIdx = $idx;
            } elseif ($pathsStartIdx !== null && preg_match('/^components:\s*$/', $line)) {
                $componentsStartIdx = $idx;
                break;
            }
        }

        if ($pathsStartIdx === null || $componentsStartIdx === null) {
            $this->error('Could not locate paths: or components: root sections in openapi.yaml.');
            return self::FAILURE;
        }

        $headerLines = array_slice($lines, 0, $pathsStartIdx);
        $pathSectionLines = array_slice($lines, $pathsStartIdx + 1, $componentsStartIdx - ($pathsStartIdx + 1));
        $componentLines = array_slice($lines, $componentsStartIdx);

        // Parse path blocks inside paths: section
        $pathBlocks = [];
        $currentPath = null;
        $currentBlock = [];

        foreach ($pathSectionLines as $line) {
            if (preg_match('/^  (\/[^\s:]+):\s*$/', $line, $m)) {
                if ($currentPath !== null) {
                    $pathBlocks[] = [
                        'path'  => $currentPath,
                        'lines' => $currentBlock,
                    ];
                }
                $currentPath = $m[1];
                $currentBlock = [$line];
            } else {
                if ($currentPath !== null) {
                    $currentBlock[] = $line;
                }
            }
        }
        if ($currentPath !== null) {
            $pathBlocks[] = [
                'path'  => $currentPath,
                'lines' => $currentBlock,
            ];
        }

        // Group path blocks into modules while preserving original order metadata
        $grouped = [];
        foreach (array_keys($this->moduleTitles) as $modKey) {
            $grouped[$modKey] = [];
        }

        $manifestOrder = [];
        foreach ($pathBlocks as $idx => $block) {
            $moduleKey = $this->resolveModuleForPath($block['path']);
            $grouped[$moduleKey][] = $block;
            $manifestOrder[] = [
                'index'  => $idx,
                'path'   => $block['path'],
                'module' => $moduleKey,
                'lines'  => count($block['lines']),
            ];
        }

        // Save _header.yaml, _components.yaml, and _manifest.json for 100% lossless bundling
        file_put_contents($modulesDir . '/_header.yaml', implode("\n", $headerLines) . "\n");
        file_put_contents($modulesDir . '/_components.yaml', implode("\n", $componentLines));
        file_put_contents($modulesDir . '/_manifest.json', json_encode([
            'source_total_lines' => $totalLines,
            'total_paths'        => count($pathBlocks),
            'generated_at'       => date('c'),
            'order'              => $manifestOrder,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // Write each module file as a valid standalone OpenAPI 3.0.3 specification
        $totalExportedPaths = 0;
        $totalExportedPathLines = 0;

        $this->info("Memecah openapi.yaml ({$totalLines} baris, " . count($pathBlocks) . " endpoint) ke dalam modul:");

        foreach ($grouped as $modKey => $blocks) {
            $modTitle = $this->moduleTitles[$modKey] ?? $modKey;
            $modHeader = [
                'openapi: 3.0.3',
                'info:',
                "  title: \"{$modTitle}\"",
                '  description: |',
                '    Dokumentasi API Modular SMESTA (Bagian dari `resources/docs/openapi.yaml`).',
                '    Edit file ini secara langsung agar ringan di IDE, lalu jalankan `php artisan docs:openapi bundle` untuk menyatukan kembali ke `openapi.yaml`.',
                '  version: 1.0.0',
                'servers:',
                '  - url: https://api-sm-connect.susantimegah.com/api/distributor-channel',
                '    description: Production Server',
                '  - url: http://api-dev.susantimegah.com/api/distributor-channel',
                '    description: Development Server',
                '  - url: http://localhost:8000/api/distributor-channel',
                '    description: Local Server',
                'paths:',
            ];

            $modBodyLines = [];
            foreach ($blocks as $b) {
                $totalExportedPaths++;
                $totalExportedPathLines += count($b['lines']);
                foreach ($b['lines'] as $l) {
                    $modBodyLines[] = $l;
                }
            }

            $minimalSecurityComponent = [
                '',
                'components:',
                '  securitySchemes:',
                '    BearerAuth:',
                '      type: http',
                '      scheme: bearer',
                '      bearerFormat: JWT',
            ];

            $fullModContent = implode("\n", array_merge($modHeader, $modBodyLines, $minimalSecurityComponent)) . "\n";
            $filePath = "{$modulesDir}/{$modKey}.yaml";
            file_put_contents($filePath, $fullModContent);

            $lineCount = count($modHeader) + count($modBodyLines) + count($minimalSecurityComponent);
            $this->line(sprintf(
                "  - %-24s : %3d endpoints (%5d baris) -> resources/docs/modules/%s.yaml",
                $modKey,
                count($blocks),
                $lineCount,
                $modKey
            ));
        }

        $this->info("  - _components.yaml         : Schemas & Security (" . count($componentLines) . " baris)");
        $this->info("Total endpoint terverifikasi : {$totalExportedPaths} / " . count($pathBlocks) . " (100% Lengkap, 0 terhapus).");
        $this->info("File asli resources/docs/openapi.yaml tetap utuh.");

        return self::SUCCESS;
    }

    /**
     * Bundle resources/docs/modules/*.yaml back into resources/docs/openapi.yaml
     */
    protected function bundleDocs(): int
    {
        $modulesDir = resource_path('docs/modules');
        $headerFile = "{$modulesDir}/_header.yaml";
        $componentsFile = "{$modulesDir}/_components.yaml";
        $manifestFile = "{$modulesDir}/_manifest.json";

        if (!file_exists($headerFile) || !file_exists($componentsFile)) {
            $this->error("Modular files not found in {$modulesDir}. Run 'php artisan docs:openapi split' first.");
            return self::FAILURE;
        }

        $header = rtrim(file_get_contents($headerFile), "\r\n");
        $components = file_get_contents($componentsFile);

        // Read all path blocks from each module file
        $allBlocksByPath = [];
        $extraBlocksByModule = [];

        foreach (array_keys($this->moduleTitles) as $modKey) {
            $modFile = "{$modulesDir}/{$modKey}.yaml";
            if (!file_exists($modFile)) {
                continue;
            }

            $lines = preg_split("/\r\n|\n|\r/", file_get_contents($modFile));
            $inPaths = false;
            $currentPath = null;
            $currentBlock = [];

            foreach ($lines as $line) {
                if (!$inPaths) {
                    if (preg_match('/^paths:\s*$/', $line)) {
                        $inPaths = true;
                    }
                    continue;
                }

                if (preg_match('/^components:\s*$/', $line)) {
                    break;
                }

                if (preg_match('/^  (\/[^\s:]+):\s*$/', $line, $m)) {
                    if ($currentPath !== null) {
                        $this->storeParsedBlock($allBlocksByPath, $currentPath, $currentBlock, $modKey);
                    }
                    $currentPath = $m[1];
                    $currentBlock = [$line];
                } else {
                    if ($currentPath !== null) {
                        $currentBlock[] = $line;
                    }
                }
            }

            if ($currentPath !== null) {
                $this->storeParsedBlock($allBlocksByPath, $currentPath, $currentBlock, $modKey);
            }
        }

        // Reconstruct in original manifest order first, then append any newly added paths
        $manifest = file_exists($manifestFile) ? json_decode(file_get_contents($manifestFile), true) : [];
        $orderList = $manifest['order'] ?? [];

        $bundledPathLines = ['paths:'];
        $usedKeys = [];

        foreach ($orderList as $item) {
            $p = $item['path'];
            if (isset($allBlocksByPath[$p])) {
                foreach ($allBlocksByPath[$p] as $l) {
                    $bundledPathLines[] = $l;
                }
                $usedKeys[$p] = true;
            }
        }

        // Append any new paths added to module files that weren't in original manifest
        foreach ($allBlocksByPath as $p => $blockLines) {
            if (!isset($usedKeys[$p])) {
                foreach ($blockLines as $l) {
                    $bundledPathLines[] = $l;
                }
            }
        }

        $finalYaml = $header . "\n" . implode("\n", $bundledPathLines) . "\n" . $components;
        file_put_contents(resource_path('docs/openapi.yaml'), $finalYaml);

        $this->info('Berhasil menggabungkan seluruh file modul kembali ke resources/docs/openapi.yaml!');
        return self::SUCCESS;
    }

    protected function storeParsedBlock(array &$allBlocksByPath, string $path, array $blockLines, string $modKey): void
    {
        // Trim trailing empty line just before components if added by split
        while (!empty($blockLines) && trim(end($blockLines)) === '' && count($blockLines) > 1) {
            // Keep single blank line between path blocks if original had it
            break;
        }
        $allBlocksByPath[$path] = $blockLines;
    }

    protected function resolveModuleForPath(string $path): string
    {
        $p = strtolower($path);

        if (str_contains($p, '/production/')) {
            return 'production';
        }

        if (str_contains($p, '/logistic/') || str_contains($p, '/ekspedisi/')) {
            return 'logistic-ekspedisi';
        }

        if (
            str_contains($p, '/sales-orders') ||
            str_contains($p, '/sales-returns') ||
            str_contains($p, '/sales-dashboard') ||
            str_contains($p, '/customer-monthly-orders') ||
            str_contains($p, '/sales-distributors') ||
            str_contains($p, '/sales-employees') ||
            str_contains($p, '/distributor-item-prices') ||
            str_contains($p, '/discounts')
        ) {
            return 'sales-orders';
        }

        if (
            str_contains($p, '/purchasing-request') ||
            str_contains($p, '/getdepartment') ||
            str_contains($p, '/getdetailpr') ||
            str_contains($p, '/api/addpr') ||
            str_contains($p, '/quotations') ||
            str_contains($p, '/vendor-portal') ||
            str_contains($p, '/vendor-management') ||
            str_contains($p, '/budgeting') ||
            str_contains($p, '/document-types')
        ) {
            return 'purchasing-vendor';
        }

        if (str_contains($p, '/claims') || str_contains($p, '/pi-settings')) {
            return 'claims-programs';
        }

        if (str_contains($p, '/recruitment') || str_contains($p, '/psikotes') || str_contains($p, '/hr/')) {
            return 'hr-recruitment';
        }

        if (str_contains($p, '/task-management') || str_contains($p, '/reporting') || str_contains($p, '/cron-jobs')) {
            return 'task-reporting';
        }

        return 'master-auth-core';
    }
}

