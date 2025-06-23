# --- Configuration ---
# IMPORTANT: Change these paths to your project's root and desired output file location.
$ProjectRoot = "C:\xampp\htdocs\alsmweb"
$OutputFilePath = "C:\xampp\htdocs\alsmweb\project_context.txt"

# Patterns for files/directories to exclude
$ExcludePaths = @(
    "*\node_modules\*",
    "*\vendor\*",
    "*\venv\*",
    "*\lib\*",
    "*\dist\*",
    "*\build\*",
    "*\_ig\*",
    "*\target\*",
    "*.log",
    "*.tmp",
    "*.bak",
    "*.DS_Store",
    ".env*",
    "*.git*",
    "*.vscode\*",
    "*.idea\*",
    "Thumbs.db",
    "*.exe", "*.dll", "*.obj", "*.pdb",
    "*.zip", "*.tar", "*.gz", "*.rar",
    "*.jpg", "*.jpeg", "*.png", "*.gif", "*.bmp", "*.svg", "*.webp",
    "*.mp3", "*.wav", "*.ogg", "*.flac", "*.aac",
    "*.mp4", "*.mkv", "*.avi", "*.mov",
    "*.psd", "*.ai", "*.sketch"
)

# --- Helper Functions ---

Function Should-Exclude {
    param(
        [string]$Path,
        [array]$ExcludePatterns
    )
    foreach ($pattern in $ExcludePatterns) {
        if ($Path -like $pattern) {
            return $true
        }
    }
    return $false
}

Function Get-MarkdownLanguageIdentifier {
    param (
        [string]$FilePath
    )
    $extension = [System.IO.Path]::GetExtension($FilePath).ToLowerInvariant()
    switch ($extension) {
        ".php" { "php" }
        ".html" { "html" }
        ".htm" { "html" }
        ".css" { "css" }
        ".js" { "javascript" }
        ".ts" { "typescript" }
        ".json" { "json" }
        ".xml" { "xml" }
        ".sql" { "sql" }
        ".py" { "python" }
        ".md" { "markdown" }
        ".txt" { "plaintext" }
        ".sh" { "bash" }
        ".bat" { "batch" }
        ".ps1" { "powershell" }
        ".java" { "java" }
        ".c" { "c" }
        ".cpp" { "cpp" }
        ".cs" { "csharp" }
        ".go" { "go" }
        ".rb" { "ruby" }
        ".yml" { "yaml" }
        ".yaml" { "yaml" }
        ".toml" { "toml" }
        default { "" }
    }
}

Function Get-SimpleTree {
    param(
        [string]$CurrentPath,
        [int]$IndentLevel = 0,
        [array]$ExcludePatterns
    )
    $indent = "    " * $IndentLevel # Using 4 regular spaces
    $output = @()

    $items = Get-ChildItem -LiteralPath $CurrentPath -Force | Where-Object {
        $fullPath = $_.FullName
        -not (Should-Exclude -Path $fullPath -ExcludePatterns $ExcludePatterns)
    }

    foreach ($item in $items | Sort-Object { -not $_.PSIsContainer }, Name) {
        if ($item.PSIsContainer) {
            $output += "$($indent)[$($item.Name)]/"
            $output += (Get-SimpleTree -CurrentPath $item.FullName -IndentLevel ($IndentLevel + 1) -ExcludePatterns $ExcludePatterns)
        } else {
            $output += "$($indent)$($item.Name)"
        }
    }
    return $output
}

# --- Main Script Execution ---

Write-Host "Starting project context concatenation..." -ForegroundColor Cyan

Remove-Item -LiteralPath $OutputFilePath -ErrorAction SilentlyContinue

Add-Content -LiteralPath $OutputFilePath -Value @"
# Project Overview
This file contains the concatenated source code of my web project, prepared for analysis by Google Gemini.

**Project Purpose:** [A web dev project for my TAFE course and new business, MVW.]
**Technologies Used:** [PHP, Apache, MySQL, CSS, JavaScript.]
**Analysis Goal:** [Analyze for security, performance, and suggest refactoring for modern best practices.]

--- Directory Structure ---
"@

Write-Host "Generating directory tree..." -ForegroundColor Green
$treeOutput = Get-SimpleTree -CurrentPath $ProjectRoot -ExcludePatterns $ExcludePaths
$treeOutput | Add-Content -LiteralPath $OutputFilePath

Add-Content -LiteralPath  $OutputFilePath -Value "`n--- File Contents ---`n"

Write-Host "Concatenating file contents..." -ForegroundColor Green
Get-ChildItem -LiteralPath $ProjectRoot -Recurse -File -Force | ForEach-Object {
    $file = $_
    $relativePath = $file.FullName.Substring($ProjectRoot.Length).TrimStart('\').Replace('\', '/')

    if (Should-Exclude -Path $file.FullName -ExcludePatterns $ExcludePaths) {
        Write-Host "Skipping excluded file: $relativePath" -ForegroundColor Yellow
        return
    }

    $language = Get-MarkdownLanguageIdentifier -FilePath $file.FullName
    Write-Host "Processing: $relativePath"

    Add-Content -LiteralPath $OutputFilePath -Value "--- START FILE: $relativePath ---"
    Add-Content -LiteralPath $OutputFilePath -Value "```$language"
    Add-Content -LiteralPath $OutputFilePath -Value (Get-Content -LiteralPath $file.FullName -Raw)
    Add-Content -LiteralPath $OutputFilePath -Value "--- END FILE: $relativePath ---"
}

Write-Host "`n-----" -ForegroundColor Cyan
Write-Host "SCRIPT FINISHED." -ForegroundColor Green
Write-Host "Project context file created at: $OutputFilePath" -ForegroundColor Green
Write-Host "IMPORTANT: Review the output file for any secrets before sharing it." -ForegroundColor Red