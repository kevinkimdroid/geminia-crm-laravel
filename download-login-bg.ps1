# Download the official login background from CRM and save to public/images
$url = "http://10.1.1.64/layouts/v7/resources/Images/login-background.jpg"
$out = Join-Path $PSScriptRoot "public\images\login-hero.jpg"

try {
    Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing
    Write-Host "Downloaded login background to $out"
} catch {
    Write-Host "Error: $_"
    exit 1
}
