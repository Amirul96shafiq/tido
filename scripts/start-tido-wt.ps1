# Launches one elevated Windows Terminal window with a single Git Bash tab
# split left|right: left runs npm run dev:all, right is an empty standby shell.
# The Git Bash profile has elevate:true; starting wt elevated keeps both panes.
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
    'sp', '-V',
    '--profile', $gitBashProfile, '--startingDirectory', $cwd
)

Start-Process -FilePath 'wt.exe' -Verb RunAs -ArgumentList $wtArgs
