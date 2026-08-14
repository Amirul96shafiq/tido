param(
    [Parameter(Mandatory = $true)]
    [string] $SpecFile
)

$ErrorActionPreference = 'Stop'
$spec = Get-Content -Raw -Path $SpecFile | ConvertFrom-Json
$executable = [string] $spec.executable
$argList = @($spec.args)
$pidFile = [string] $spec.pidFile
$debugFile = [string] $spec.debugFile
$cwd = [string] $spec.cwd

function Write-DebugJson([hashtable] $payload) {
    ($payload | ConvertTo-Json -Compress) | Set-Content -Path $debugFile -Encoding utf8
}

Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

[StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
public struct STARTUPINFO {
    public int cb;
    public string lpReserved;
    public string lpDesktop;
    public string lpTitle;
    public int dwX;
    public int dwY;
    public int dwXSize;
    public int dwYSize;
    public int dwXCountChars;
    public int dwYCountChars;
    public int dwFillAttribute;
    public int dwFlags;
    public short wShowWindow;
    public short cbReserved2;
    public IntPtr lpReserved2;
    public IntPtr hStdInput;
    public IntPtr hStdOutput;
    public IntPtr hStdError;
}

[StructLayout(LayoutKind.Sequential, CharSet = CharSet.Unicode)]
public struct STARTUPINFOEX {
    public STARTUPINFO StartupInfo;
    public IntPtr lpAttributeList;
}

[StructLayout(LayoutKind.Sequential)]
public struct PROCESS_INFORMATION {
    public IntPtr hProcess;
    public IntPtr hThread;
    public int dwProcessId;
    public int dwThreadId;
}

public static class TidoWin32 {
    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern uint GetConsoleProcessList(uint[] processList, uint count);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern IntPtr OpenProcess(uint access, bool inherit, uint processId);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern bool CloseHandle(IntPtr handle);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern bool InitializeProcThreadAttributeList(IntPtr list, int count, int flags, ref IntPtr size);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern bool UpdateProcThreadAttribute(IntPtr list, uint flags, IntPtr attribute, IntPtr value, IntPtr size, IntPtr previous, IntPtr returnSize);

    [DllImport("kernel32.dll", SetLastError = true)]
    public static extern void DeleteProcThreadAttributeList(IntPtr list);

    [DllImport("kernel32.dll", SetLastError = true, CharSet = CharSet.Unicode)]
    public static extern bool CreateProcess(
        string applicationName,
        string commandLine,
        IntPtr processAttributes,
        IntPtr threadAttributes,
        bool inheritHandles,
        uint creationFlags,
        IntPtr environment,
        string currentDirectory,
        ref STARTUPINFOEX startupInfo,
        out PROCESS_INFORMATION processInformation);

    public const uint PROCESS_CREATE_PROCESS = 0x0080;
    public const uint PROCESS_DUP_HANDLE = 0x0040;
    public const uint EXTENDED_STARTUPINFO_PRESENT = 0x00080000;
    public static readonly IntPtr PROC_THREAD_ATTRIBUTE_PARENT_PROCESS = new IntPtr(0x00020000);
}
"@

function Get-ConsoleProcessIds {
    $buffer = New-Object -TypeName 'System.UInt32[]' -ArgumentList 64
    $count = [TidoWin32]::GetConsoleProcessList($buffer, [uint32] $buffer.Length)
    if ($count -le 0) {
        return @()
    }

    return @($buffer[0..([Math]::Max(0, $count - 1))])
}

function Get-QuotedCommandLine([string] $exe, [string[]] $commandArgs) {
    $parts = @('"' + $exe + '"')
    foreach ($arg in $commandArgs) {
        if ($null -eq $arg) {
            continue
        }
        $escaped = [string] $arg
        if ($escaped -match '[\s"]') {
            $escaped = '"' + ($escaped -replace '"', '\"') + '"'
        }
        $parts += $escaped
    }

    return ($parts -join ' ')
}

try {
    $consolePids = @(Get-ConsoleProcessIds)
    $bashCandidates = @()
    foreach ($consolePid in $consolePids) {
        $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$consolePid" -ErrorAction SilentlyContinue
        if (-not $proc -or $proc.Name -ne 'bash.exe') {
            continue
        }

        $commandLine = [string] $proc.CommandLine
        $bashCandidates += [pscustomobject]@{
            ProcessId = [int] $proc.ProcessId
            ParentProcessId = [int] $proc.ParentProcessId
            CommandLine = $commandLine
            HasShellIntegration = $commandLine -match 'shellIntegration-bash'
        }
    }

    $shellIntegrated = @($bashCandidates | Where-Object { $_.HasShellIntegration })
    $pool = $shellIntegrated
    if ($pool.Count -eq 0) {
        $pool = $bashCandidates
    }

    $parentPid = 0
    if ($pool.Count -gt 0) {
        $ids = @($pool | ForEach-Object { $_.ProcessId })
        $inner = @($pool | Where-Object { $ids -contains $_.ParentProcessId })
        if ($inner.Count -gt 0) {
            $parentPid = [int] $inner[0].ProcessId
        } else {
            $parentPid = [int] $pool[0].ProcessId
        }
    }

    if ($parentPid -le 0) {
        Write-DebugJson @{
            ok = $false
            reason = 'no-console-bash'
            consolePids = @($consolePids)
            bashCount = $bashCandidates.Count
        }
        exit 2
    }

    $parentHandle = [TidoWin32]::OpenProcess(
        [TidoWin32]::PROCESS_CREATE_PROCESS -bor [TidoWin32]::PROCESS_DUP_HANDLE,
        $false,
        [uint32] $parentPid
    )
    if ($parentHandle -eq [IntPtr]::Zero) {
        Write-DebugJson @{
            ok = $false
            reason = 'open-parent-failed'
            parentPid = $parentPid
            win32 = [Runtime.InteropServices.Marshal]::GetLastWin32Error()
        }
        exit 3
    }

    $size = [IntPtr]::Zero
    [void][TidoWin32]::InitializeProcThreadAttributeList([IntPtr]::Zero, 1, 0, [ref] $size)
    $attributeList = [Runtime.InteropServices.Marshal]::AllocHGlobal($size)
    if (-not [TidoWin32]::InitializeProcThreadAttributeList($attributeList, 1, 0, [ref] $size)) {
        Write-DebugJson @{
            ok = $false
            reason = 'init-attr-failed'
            parentPid = $parentPid
            win32 = [Runtime.InteropServices.Marshal]::GetLastWin32Error()
        }
        exit 4
    }

    $parentHandlePtr = [Runtime.InteropServices.Marshal]::AllocHGlobal([IntPtr]::Size)
    [Runtime.InteropServices.Marshal]::WriteIntPtr($parentHandlePtr, $parentHandle)
    $updated = [TidoWin32]::UpdateProcThreadAttribute(
        $attributeList,
        0,
        [TidoWin32]::PROC_THREAD_ATTRIBUTE_PARENT_PROCESS,
        $parentHandlePtr,
        [IntPtr] [IntPtr]::Size,
        [IntPtr]::Zero,
        [IntPtr]::Zero
    )
    if (-not $updated) {
        Write-DebugJson @{
            ok = $false
            reason = 'update-attr-failed'
            parentPid = $parentPid
            win32 = [Runtime.InteropServices.Marshal]::GetLastWin32Error()
        }
        exit 5
    }

    $startupInfo = New-Object STARTUPINFO
    $startupInfo.cb = [Runtime.InteropServices.Marshal]::SizeOf([type][STARTUPINFOEX])
    $startup = New-Object STARTUPINFOEX
    $startup.StartupInfo = $startupInfo
    $startup.lpAttributeList = $attributeList

    $commandLine = Get-QuotedCommandLine $executable $argList
    $processInfo = New-Object PROCESS_INFORMATION
    $created = [TidoWin32]::CreateProcess(
        $executable,
        $commandLine,
        [IntPtr]::Zero,
        [IntPtr]::Zero,
        $true,
        [TidoWin32]::EXTENDED_STARTUPINFO_PRESENT,
        [IntPtr]::Zero,
        $cwd,
        [ref] $startup,
        [ref] $processInfo
    )

    $win32 = [Runtime.InteropServices.Marshal]::GetLastWin32Error()
    if (-not $created) {
        Write-DebugJson @{
            ok = $false
            reason = 'create-process-failed'
            parentPid = $parentPid
            win32 = $win32
            commandLine = $commandLine
        }
        exit 6
    }

    [void][TidoWin32]::CloseHandle($processInfo.hThread)
    [void][TidoWin32]::CloseHandle($processInfo.hProcess)
    [TidoWin32]::DeleteProcThreadAttributeList($attributeList)
    [Runtime.InteropServices.Marshal]::FreeHGlobal($attributeList)
    [Runtime.InteropServices.Marshal]::FreeHGlobal($parentHandlePtr)
    [void][TidoWin32]::CloseHandle($parentHandle)

    Set-Content -Path $pidFile -Value ([string] $processInfo.dwProcessId) -Encoding ascii
    Write-DebugJson @{
        ok = $true
        parentPid = $parentPid
        childPid = $processInfo.dwProcessId
        consolePids = @($consolePids)
        bashCount = $bashCandidates.Count
        shellIntegratedCount = $shellIntegrated.Count
        commandLine = $commandLine
    }
    exit 0
} catch {
    Write-DebugJson @{
        ok = $false
        reason = 'exception'
        message = $_.Exception.Message
        type = $_.Exception.GetType().FullName
    }
    exit 1
}
