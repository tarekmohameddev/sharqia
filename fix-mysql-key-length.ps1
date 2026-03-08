# Fix MySQL ERROR 1071 (key too long) and ERROR 1089 (invalid prefix key).
# 1. Shorten varchar(255) to varchar(191) so indexes fit with utf8mb4.
# 2. Remove any prefix key like `col`(191) from KEY/UNIQUE KEY lines (avoids ERROR 1089
#    on MyISAM/non-string columns). Column shortening makes full-column index valid.

param(
    [Parameter(Mandatory=$true)]
    [string]$InputFile,
    [string]$OutputFile = ""
)

if ($OutputFile -eq "") {
    $OutputFile = $InputFile -replace '\.sql$', '_fixed.sql'
}

if (-not (Test-Path $InputFile)) {
    Write-Error "Input file not found: $InputFile"
    exit 1
}

$reader = [System.IO.StreamReader]::new($InputFile)
$writer = [System.IO.StreamWriter]::new($OutputFile)

try {
    $lineNum = 0
    while ($null -ne ($line = $reader.ReadLine())) {
        $lineNum++
        # 1) Shorten varchar(255) to varchar(191) so indexes fit (utf8mb4).
        $line = $line -replace ' varchar\(255\)', ' varchar(191)'
        # 2) Remove prefix key: `col`(191) or `col`(250) etc. -> `col` (full column index).
        #    Avoids ERROR 1089 when prefix was applied to non-string or MyISAM HASH key.
        $line = $line -replace '(`[a-z_0-9]+`)\(\d+\)', '$1'
        $writer.WriteLine($line)
    }
    Write-Host "Done. Fixed SQL written to: $OutputFile"
} finally {
    $reader.Close()
    $writer.Close()
}
