$src  = 'C:\Users\sgame\.gemini\antigravity-ide\scratch\soulsync-laravel'
$dest = 'C:\Users\sgame\.gemini\antigravity-ide\scratch\soulsync-laravel.zip'

if (Test-Path $dest) { Remove-Item $dest -Force }

$files = Get-ChildItem -Path $src -Recurse -File | Where-Object {
    $_.FullName -notmatch '\\vendor\\' -and
    $_.FullName -notmatch '\\.git\\' -and
    $_.Name -ne 'soulsync-laravel.zip'
}

Write-Host "Found $($files.Count) files to zip..."
Add-Type -Assembly System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($dest, 'Create')

foreach ($file in $files) {
    $entryName = 'soulsync-laravel/' + $file.FullName.Substring($src.Length + 1)
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, 'Optimal') | Out-Null
}

$zip.Dispose()
$size = (Get-Item $dest).Length / 1KB
Write-Host ("✅ ZIP created! Size: {0:N0} KB" -f $size)
Write-Host "Location: $dest"
