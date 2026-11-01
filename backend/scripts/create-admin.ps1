# اجرا از PowerShell؛ رمز نه در آرگومان دستور و نه در فایل نوشته می‌شود.
param([Parameter(Mandatory=$true)][string]$Username)
$ErrorActionPreference = 'Stop'
$first = Read-Host 'رمز عبور (حداقل ۱۲ نویسه، حداکثر ۷۲ بایت)' -AsSecureString
$second = Read-Host 'تکرار رمز عبور' -AsSecureString
try {
    $plain = [System.Net.NetworkCredential]::new('', $first).Password
    $confirm = [System.Net.NetworkCredential]::new('', $second).Password
    if ($plain -cne $confirm) { throw 'تکرار رمز یکسان نیست.' }
    $oldEncoding = $OutputEncoding
    try {
        $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
        $plain | & php (Join-Path $PSScriptRoot 'create-admin.php') $Username --password-stdin
        if ($LASTEXITCODE -ne 0) { throw 'ساخت حساب انجام نشد.' }
    } finally { $OutputEncoding = $oldEncoding }
} finally {
    $plain = $null
    $confirm = $null
    $first.Dispose()
    $second.Dispose()
}
