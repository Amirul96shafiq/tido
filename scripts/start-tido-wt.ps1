# Launches one elevated Windows Terminal window with two Git Bash tabs.
# The Git Bash profile has elevate:true; starting wt elevated keeps both tabs.
$ErrorActionPreference = 'Stop'

$gitBash = 'G:\Apps\Git\bin\bash.exe'
if (-not (Test-Path -LiteralPath $gitBash)) {
    $gitBash = Join-Path $env:ProgramFiles 'Git\bin\bash.exe'
}

$devScript = 'G:/projects/tido/scripts/start-dev-all.sh'
$cwd = 'G:\projects\tido'
$gitBashProfile = '{2ece5bfe-50ed-5f3a-ab87-5cd4baafed2b}'

$wtArgs = @(
    '-w', 'new',
    'nt', '--title', 'tido', '--suppressApplicationTitle',
    '--profile', $gitBashProfile, '--startingDirectory', $cwd,
    $gitBash, '-i', '-l', $devScript,
    ';',
    'nt', '--profile', $gitBashProfile, '--startingDirectory', $cwd,
    ';',
    'ft', '-t', '0'
)

Start-Process -FilePath 'wt.exe' -Verb RunAs -ArgumentList $wtArgs
