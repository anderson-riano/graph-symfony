param(
    [switch]$SkipBuild,
    [switch]$RunTests
)

$ErrorActionPreference = 'Stop'

function Run-Step {
    param(
        [Parameter(Mandatory = $true)][string]$Title,
        [Parameter(Mandatory = $true)][string]$Command
    )

    Write-Host ""
    Write-Host "==> $Title" -ForegroundColor Cyan
    Write-Host "$Command" -ForegroundColor DarkGray
    Invoke-Expression $Command
}

if ($SkipBuild) {
    Run-Step -Title "Starting containers" -Command "docker compose up -d"
} else {
    Run-Step -Title "Building and starting containers" -Command "docker compose up -d --build"
}

Run-Step -Title "Installing PHP dependencies" -Command "docker compose exec php composer install --no-interaction"
Run-Step -Title "Running database migrations" -Command "docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction"
Run-Step -Title "Loading product fixtures" -Command "docker compose exec php php bin/console doctrine:fixtures:load --no-interaction"

if ($RunTests) {
    Run-Step -Title "Running test suite" -Command "docker compose exec php composer test"
}

Write-Host ""
Write-Host "Bootstrap completed." -ForegroundColor Green
Write-Host "App: http://localhost:8080" -ForegroundColor Green
Write-Host "GraphQL: http://localhost:8080/api/graphql" -ForegroundColor Green
Write-Host "RabbitMQ UI: http://localhost:15672" -ForegroundColor Green
Write-Host ""
Write-Host "Workers are running as docker services: worker + outbox_publisher" -ForegroundColor Yellow
Write-Host "Watch logs with:" -ForegroundColor Yellow
Write-Host "docker compose logs -f worker" -ForegroundColor Yellow
Write-Host "docker compose logs -f outbox_publisher" -ForegroundColor Yellow
