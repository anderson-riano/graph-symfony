param(
    [string]$CustomerName = "Demo Customer",
    [string]$CustomerEmail = "demo@test.com",
    [string]$Sku = "SKU-KEYBOARD-001",
    [int]$Quantity = 2,
    [int]$WorkerTimeLimit = 20,
    [string]$GraphQlEndpoint = "http://localhost:8080/api/graphql"
)

$ErrorActionPreference = 'Stop'

function Step {
    param([string]$Message)
    Write-Host ""
    Write-Host "==> $Message" -ForegroundColor Cyan
}

function Invoke-GraphQl {
    param(
        [Parameter(Mandatory = $true)][string]$Query,
        [hashtable]$Variables = @{}
    )

    $payload = @{
        query = $Query
        variables = $Variables
    } | ConvertTo-Json -Depth 20 -Compress

    $json = Invoke-RestMethod -Uri $GraphQlEndpoint -Method Post -ContentType "application/json" -Body $payload -TimeoutSec 120

    if ($null -ne $json.errors) {
        $json.errors | ConvertTo-Json -Depth 20 | Write-Host -ForegroundColor Red
        throw "GraphQL returned errors."
    }

    return $json.data
}

if ($Quantity -le 0) {
    throw "Quantity must be greater than zero."
}

Step "Ensuring services are up"
docker compose up -d | Out-Host

Step "Loading fixtures for predictable demo data"
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction | Out-Host

Step "Fetching product by SKU ($Sku)"
$productsQuery = @"
query {
  products {
    edges {
      node {
        id
        sku
        name
        stock
        price
        active
      }
    }
  }
}
"@

$productsData = Invoke-GraphQl -Query $productsQuery
$productNode = $productsData.products.edges |
    ForEach-Object { $_.node } |
    Where-Object { $_.sku -eq $Sku } |
    Select-Object -First 1

if ($null -eq $productNode) {
    throw "Product with SKU '$Sku' was not found."
}

if (-not $productNode.active) {
    throw "Product '$Sku' is inactive."
}

Write-Host ("Selected product: {0} ({1}) stock={2} price={3}" -f $productNode.name, $productNode.id, $productNode.stock, $productNode.price) -ForegroundColor Gray

Step "Creating order via GraphQL mutation"
$safeCustomerName = $CustomerName.Replace('"', '\"')
$safeCustomerEmail = $CustomerEmail.Replace('"', '\"')

$createOrderMutation = @"
mutation {
  createOrder(input: {
    customerName: "$safeCustomerName"
    customerEmail: "$safeCustomerEmail"
    items: [{ productId: "$($productNode.id)", quantity: $Quantity }]
  }) {
    order {
      id
      status
      total
      createdAt
    }
  }
}
"@

$createOrderData = Invoke-GraphQl -Query $createOrderMutation

$order = $createOrderData.createOrder.order
$orderId = $order.id
Write-Host ("Created order: {0} status={1} total={2}" -f $orderId, $order.status, $order.total) -ForegroundColor Green

Step "Consuming async messages (time-limit=$WorkerTimeLimit s)"
docker compose exec php php bin/console messenger:consume async -vv --time-limit=$WorkerTimeLimit | Out-Host

Step "Fetching final order state and event trail"
$orderQuery = @"
query {
  order(id: "$orderId") {
    id
    status
    total
    events {
      edges {
        node {
          eventName
          status
          errorMessage
          createdAt
        }
      }
    }
  }
}
"@

$orderData = Invoke-GraphQl -Query $orderQuery
$finalOrder = $orderData.order
$eventNames = @($finalOrder.events.edges | ForEach-Object { $_.node.eventName })

$expectedStatus = if ($CustomerEmail.ToLower().Contains("fail")) { "FAILED" } else { "CONFIRMED" }

Write-Host ("Final status: {0} (expected: {1})" -f $finalOrder.status, $expectedStatus) -ForegroundColor Yellow
Write-Host ("Events: {0}" -f ($eventNames -join " -> ")) -ForegroundColor Yellow

if ($finalOrder.status -ne $expectedStatus) {
    throw ("Unexpected final status. Got '{0}' but expected '{1}'." -f $finalOrder.status, $expectedStatus)
}

Write-Host ""
Write-Host "Demo flow completed successfully." -ForegroundColor Green
