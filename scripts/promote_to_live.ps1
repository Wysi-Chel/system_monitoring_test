[CmdletBinding()]
param(
    [string]$SourcePath = "C:\xampp\htdocs\system_monitoring_test",
    [string]$TargetPath = "C:\xampp\htdocs\system_monitoring",
    [string]$BackupRoot = "",
    [string]$PhpCommand = "php",
    [switch]$Apply,
    [switch]$DeleteRemoved,
    [switch]$SkipSchemaSync
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

function Resolve-NormalizedPath {
    param([string]$PathValue)

    return (Resolve-Path -LiteralPath $PathValue).Path
}

function Get-EnvironmentMarker {
    param([string]$RootPath)

    $markerPath = Join-Path $RootPath ".app-env"
    if (-not (Test-Path -LiteralPath $markerPath)) {
        return ""
    }

    return (Get-Content -LiteralPath $markerPath -Raw).Trim()
}

function Assert-Environment {
    param(
        [string]$RootPath,
        [string]$ExpectedEnvironment,
        [string]$Label
    )

    $actualEnvironment = Get-EnvironmentMarker -RootPath $RootPath
    if ($actualEnvironment -ne $ExpectedEnvironment) {
        throw "$Label must be marked as '$ExpectedEnvironment' in .app-env. Current value: '$actualEnvironment'."
    }
}

function Get-RelativePath {
    param(
        [string]$BasePath,
        [string]$FullPath
    )

    $normalizedBasePath = [System.IO.Path]::GetFullPath($BasePath).TrimEnd("\")
    $normalizedFullPath = [System.IO.Path]::GetFullPath($FullPath)

    if (-not $normalizedFullPath.StartsWith($normalizedBasePath, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Path '$normalizedFullPath' is not inside '$normalizedBasePath'."
    }

    return $normalizedFullPath.Substring($normalizedBasePath.Length).TrimStart("\")
}

function Test-ExcludedRelativePath {
    param([string]$RelativePath)

    $normalizedRelativePath = ($RelativePath -replace "\\", "/").TrimStart("/")
    $excludedPrefixes = @(
        ".git/",
        "uploads/",
        "scripts/__pycache__/",
        "__pycache__/"
    )
    $excludedExactMatches = @(
        ".app-env"
    )

    if ($excludedExactMatches -contains $normalizedRelativePath) {
        return $true
    }

    foreach ($prefix in $excludedPrefixes) {
        if ($normalizedRelativePath.StartsWith($prefix, [System.StringComparison]::OrdinalIgnoreCase)) {
            return $true
        }
    }

    return $false
}

function Get-FileCatalog {
    param([string]$RootPath)

    $catalog = @{}

    Get-ChildItem -LiteralPath $RootPath -Recurse -Force -File | ForEach-Object {
        $relativePath = Get-RelativePath -BasePath $RootPath -FullPath $_.FullName
        if (Test-ExcludedRelativePath -RelativePath $relativePath) {
            return
        }

        $catalog[$relativePath] = [PSCustomObject]@{
            RelativePath = $relativePath
            FullPath     = $_.FullName
            Hash         = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash
            Length       = $_.Length
        }
    }

    return $catalog
}

function New-PromotionPlan {
    param(
        [hashtable]$SourceCatalog,
        [hashtable]$TargetCatalog
    )

    $copyItems = @()
    $deleteItems = @()

    foreach ($relativePath in ($SourceCatalog.Keys | Sort-Object)) {
        $sourceItem = $SourceCatalog[$relativePath]
        $targetItem = $TargetCatalog[$relativePath]
        $targetExists = $null -ne $targetItem

        if (-not $targetExists -or $sourceItem.Hash -ne $targetItem.Hash) {
            $copyItems += [PSCustomObject]@{
                RelativePath = $relativePath
                SourcePath   = $sourceItem.FullPath
                TargetPath   = if ($targetExists) { $targetItem.FullPath } else { $null }
                TargetExists = $targetExists
            }
        }
    }

    foreach ($relativePath in ($TargetCatalog.Keys | Sort-Object)) {
        if ($SourceCatalog.ContainsKey($relativePath)) {
            continue
        }

        $targetItem = $TargetCatalog[$relativePath]
        $deleteItems += [PSCustomObject]@{
            RelativePath = $relativePath
            TargetPath   = $targetItem.FullPath
        }
    }

    return [PSCustomObject]@{
        CopyItems   = $copyItems
        DeleteItems = $deleteItems
    }
}

function Write-Plan {
    param(
        [pscustomobject]$Plan,
        [bool]$IncludeDeleteStep
    )

    Write-Output ""
    Write-Output "Promotion preview"
    Write-Output "Copy / update: $($Plan.CopyItems.Count)"
    Write-Output "Missing in source: $($Plan.DeleteItems.Count)"

    if ($Plan.CopyItems.Count -gt 0) {
        Write-Output ""
        Write-Output "Files to copy/update:"
        $Plan.CopyItems | ForEach-Object {
            $label = if ($_.TargetExists) { "update" } else { "new" }
            Write-Output (" - [{0}] {1}" -f $label, $_.RelativePath)
        }
    }

    if ($Plan.DeleteItems.Count -gt 0) {
        Write-Output ""
        if ($IncludeDeleteStep) {
            Write-Output "Files to delete from live:"
        } else {
            Write-Output "Files missing in test (not deleted unless -DeleteRemoved is used):"
        }

        $Plan.DeleteItems | ForEach-Object {
            Write-Output (" - {0}" -f $_.RelativePath)
        }
    }

    Write-Output ""
}

function Backup-ExistingFile {
    param(
        [string]$SourceFilePath,
        [string]$RelativePath,
        [string]$BackupPath
    )

    $backupFilePath = Join-Path $BackupPath $RelativePath
    $backupDirectory = Split-Path -Path $backupFilePath -Parent
    if (-not (Test-Path -LiteralPath $backupDirectory)) {
        New-Item -ItemType Directory -Path $backupDirectory -Force | Out-Null
    }

    Copy-Item -LiteralPath $SourceFilePath -Destination $backupFilePath -Force
}

$resolvedSourcePath = Resolve-NormalizedPath -PathValue $SourcePath
$resolvedTargetPath = Resolve-NormalizedPath -PathValue $TargetPath

if ($resolvedSourcePath -eq $resolvedTargetPath) {
    throw "Source and target paths must be different."
}

Assert-Environment -RootPath $resolvedSourcePath -ExpectedEnvironment "test" -Label "Source"
Assert-Environment -RootPath $resolvedTargetPath -ExpectedEnvironment "live" -Label "Target"

$sourceCatalog = Get-FileCatalog -RootPath $resolvedSourcePath
$targetCatalog = Get-FileCatalog -RootPath $resolvedTargetPath
$plan = New-PromotionPlan -SourceCatalog $sourceCatalog -TargetCatalog $targetCatalog

Write-Plan -Plan $plan -IncludeDeleteStep $DeleteRemoved.IsPresent

if (-not $Apply) {
    Write-Output "Preview only. Re-run with -Apply to promote test changes to live."
    exit 0
}

if ($plan.CopyItems.Count -eq 0 -and (-not $DeleteRemoved -or $plan.DeleteItems.Count -eq 0)) {
    Write-Output "No live changes are needed."

    if (-not $SkipSchemaSync) {
        Write-Output "Running live schema sync anyway..."
    } else {
        exit 0
    }
}

if ([string]::IsNullOrWhiteSpace($BackupRoot)) {
    $BackupRoot = Join-Path (Split-Path -Path $resolvedTargetPath -Parent) "system_monitoring_backups"
}

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$backupPath = Join-Path $BackupRoot ("promotion_" + $timestamp)
New-Item -ItemType Directory -Path $backupPath -Force | Out-Null

$filesToBackup = @()
$filesToBackup += $plan.CopyItems | Where-Object { $_.TargetExists }

if ($DeleteRemoved) {
    $filesToBackup += $plan.DeleteItems
}

foreach ($item in $filesToBackup) {
    Backup-ExistingFile -SourceFilePath $item.TargetPath -RelativePath $item.RelativePath -BackupPath $backupPath
}

$manifest = [PSCustomObject]@{
    promoted_at      = (Get-Date).ToString("s")
    source_path      = $resolvedSourcePath
    target_path      = $resolvedTargetPath
    backup_path      = $backupPath
    copy_items       = @($plan.CopyItems | ForEach-Object { $_.RelativePath })
    delete_items     = @($plan.DeleteItems | ForEach-Object { $_.RelativePath })
    delete_removed   = [bool]$DeleteRemoved
    schema_sync_skip = [bool]$SkipSchemaSync
}
$manifest | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath (Join-Path $backupPath "promotion-manifest.json")

foreach ($item in $plan.CopyItems) {
    $destinationFilePath = Join-Path $resolvedTargetPath $item.RelativePath
    $destinationDirectory = Split-Path -Path $destinationFilePath -Parent

    if (-not (Test-Path -LiteralPath $destinationDirectory)) {
        New-Item -ItemType Directory -Path $destinationDirectory -Force | Out-Null
    }

    Copy-Item -LiteralPath $item.SourcePath -Destination $destinationFilePath -Force
}

if ($DeleteRemoved) {
    foreach ($item in $plan.DeleteItems) {
        if (Test-Path -LiteralPath $item.TargetPath) {
            Remove-Item -LiteralPath $item.TargetPath -Force
        }
    }
}

if (-not $SkipSchemaSync) {
    $schemaScriptPath = Join-Path $resolvedTargetPath "scripts\sync_environment_schema.php"
    if (-not (Test-Path -LiteralPath $schemaScriptPath)) {
        throw "Schema sync script is missing: $schemaScriptPath"
    }

    Write-Output "Running live schema sync..."
    & $PhpCommand $schemaScriptPath
    if ($LASTEXITCODE -ne 0) {
        throw "Live schema sync failed."
    }
}

Write-Output ""
Write-Output "Promotion complete."
Write-Output ("Backup saved to: {0}" -f $backupPath)
