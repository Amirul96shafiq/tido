# One-time fix: Cursor non-legacy agent Shell expects MSYS under resources\app\bin.
# Copying bash.exe alone fails with "Top-level not found"; junction Git's bin/usr instead.
$ErrorActionPreference = 'Stop'

$gitRoot = 'G:\Apps\Git'
$gitBin = Join-Path $gitRoot 'bin'
$gitUsr = Join-Path $gitRoot 'usr'
$cursorApp = Join-Path $env:LOCALAPPDATA 'Programs\cursor\resources\app'
$cursorBin = Join-Path $cursorApp 'bin'
$cursorUsr = Join-Path $cursorApp 'usr'

foreach ($required in @($gitBin, $gitUsr)) {
    if (-not (Test-Path $required)) {
        Write-Error "Git for Windows path not found: $required"
    }
}

function Ensure-Junction {
    param(
        [string] $Link,
        [string] $Target
    )

    if (Test-Path $Link) {
        $item = Get-Item $Link -Force
        if ($item.LinkType -eq 'Junction' -and $item.Target -eq $Target) {
            Write-Host "Junction already correct: $Link -> $Target"
            return
        }

        Remove-Item $Link -Recurse -Force
    }

    New-Item -ItemType Junction -Path $Link -Target $Target | Out-Null
    Write-Host "Created junction: $Link -> $Target"
}

Ensure-Junction -Link $cursorBin -Target $gitBin
Ensure-Junction -Link $cursorUsr -Target $gitUsr

Write-Host 'Done. Reload Cursor (Developer: Reload Window), then retry agent shell commands.'
