$src  = 'C:\Users\sgame\.gemini\antigravity-ide\scratch\soulsync-system'
$dest = 'C:\Users\sgame\.gemini\antigravity-ide\scratch\soulsync-system-clean.zip'

if (Test-Path $dest) { Remove-Item $dest -Force }

$files = Get-ChildItem -Path $src -Recurse -File | Where-Object {
    $_.FullName -notmatch '\\node_modules\\' -and
    $_.FullName -notmatch '\\.next\\' -and
    $_.FullName -notmatch '\\.git\\' -and
    $_.Name -ne 'soulsync-system-clean.zip' -and
    $_.Name -ne 'make-zip.ps1'
}

Write-Host "Found $($files.Count) files to zip..."

Add-Type -Assembly System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($dest, 'Create')

foreach ($file in $files) {
    $entryName = 'soulsync-system/' + $file.FullName.Substring($src.Length + 1)
    [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $file.FullName, $entryName, 'Optimal') | Out-Null
}

$zip.Dispose()
Write-Host "ZIP created at: $dest"
$size = (Get-Item $dest).Length / 1MB
Write-Host ("Size: {0:N2} MB" -f $size)
Write-Host "Done!"
