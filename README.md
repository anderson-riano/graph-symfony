# OrderFlow Symfony 7

Backend tecnico con Symfony 7, GraphQL, RabbitMQ, Messenger, PostgreSQL y Docker.

## What this project does

OrderFlow permite crear ordenes por GraphQL y procesarlas de forma asincrona:

1. `createOrder` crea la orden en estado `PENDING`.
2. En la misma transaccion se persiste un mensaje en `outbox_messages`.
3. Worker de outbox publica mensajes pendientes a RabbitMQ.
4. Worker de Messenger reserva inventario con locking pesimista.
5. Worker simula pago.
6. Worker confirma orden.
7. Worker simula notificacion.
8. Cada paso queda auditado en `order_event_logs`.

## Stack

- PHP 8.3
- Symfony 7.4
- API Platform GraphQL
- Symfony Messenger + RabbitMQ (AMQP)
- PostgreSQL 16
- Doctrine ORM + Migrations + Fixtures
- Symfony Validator
- PHPUnit, PHPStan, PHP CS Fixer
- Docker Compose
- Kubernetes basico opcional (`k8s/`)

## Architecture

Estructura por capas:

- `Domain`: entidades, enums, reglas de negocio.
- `Application`: casos de uso y servicios de aplicacion.
- `Infrastructure`: repositorios Doctrine, mensajes/handlers Messenger, mutation resolver GraphQL, comando outbox publisher.
- `DataFixtures`: productos base.

Se evita logica pesada en GraphQL: el resolver de `createOrder` valida input y delega al caso de uso `CreateOrderUseCase`.

## Async flow and reliability

- Estado de ordenes con enum nativo `OrderStatus`.
- Transiciones validas controladas por dominio.
- Outbox transaccional:
  - Tabla `outbox_messages`.
  - Escritura de mensajes dentro de la misma transaccion de negocio.
  - Publicacion asincrona via `app:outbox:publish`.
- Handlers:
  - `OrderCreatedMessageHandler`
  - `InventoryReservedMessageHandler`
  - `PaymentApprovedMessageHandler`
  - `OrderConfirmedMessageHandler`
- Idempotencia: tabla `processed_messages` + guard por `messageId`.
- Locking de inventario: `PESSIMISTIC_WRITE` por producto al reservar/liberar stock.
- Reintentos y cola de fallos en Messenger:
  - `async` con `max_retries=3`
  - `failed` transport en Doctrine

## Validation

- DTOs de entrada (`CreateOrderInput`, `CreateOrderItemInput`) usan Symfony Validator.
- El resolver de GraphQL lanza `ApiPlatform\Validator\Exception\ValidationException` cuando hay violaciones.
- Respuesta de error sigue el formato esperado por API Platform GraphQL.

## Event audit trail

Tabla `order_event_logs` con:

- `eventName`
- `status`
- `payload`
- `errorMessage`
- `createdAt`

Eventos principales:

- `ORDER_CREATED`
- `INVENTORY_RESERVED`
- `INVENTORY_RESERVATION_FAILED`
- `PAYMENT_APPROVED`
- `PAYMENT_REJECTED`
- `ORDER_CONFIRMED`
- `NOTIFICATION_SENT`
- `NOTIFICATION_FAILED`

## Project structure

```txt
src/
  Domain/
  Application/
  Infrastructure/
  DataFixtures/
config/
migrations/
tests/
docker/
k8s/
```

## Run with Docker

### One-command bootstrap (PowerShell)

```powershell
./scripts/bootstrap.ps1
```

Optional:

```powershell
./scripts/bootstrap.ps1 -SkipBuild
./scripts/bootstrap.ps1 -RunTests
```

### Automated end-to-end demo flow (PowerShell)

```powershell
./scripts/demo-flow.ps1
```

Payment-failure scenario:

```powershell
./scripts/demo-flow.ps1 -CustomerEmail fail@test.com
```

### 1) Build and start

```bash
docker compose up -d --build
```

Esto inicia `php`, `database`, `rabbitmq`, `worker` y `outbox_publisher`.

### 2) Install dependencies

```bash
docker compose exec php composer install
```

### 3) Run migrations

```bash
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 4) Load fixtures

```bash
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### 5) Worker logs

```bash
docker compose logs -f worker
docker compose logs -f outbox_publisher
```

### 6) Manual worker mode (optional)

```bash
docker compose stop worker outbox_publisher
docker compose exec php composer outbox:publish
docker compose exec php composer worker:consume
```

### 7) Run tests

```bash
docker compose exec php php bin/phpunit
```

### 8) Static analysis and coding style

```bash
docker compose exec php composer analyse
docker compose exec php composer cs:fix
```

### 9) Outbox + failed message operations

```bash
docker compose exec php php bin/console app:outbox:publish --limit=50
docker compose exec php php bin/console app:outbox:publish --loop --limit=50 --sleep-ms=1000
docker compose exec php php bin/console messenger:failed:show
docker compose exec php php bin/console messenger:failed:retry
docker compose exec php php bin/console messenger:failed:remove
```

## Local endpoints

- App: `http://localhost:8080`
- GraphQL endpoint: `http://localhost:8080/api/graphql`
- PostgreSQL host port: `5433`
- RabbitMQ AMQP: `5672`
- RabbitMQ management: `http://localhost:15672`

## GraphQL examples

### List products

```graphql
query {
  products {
    edges {
      node {
        id
        name
        sku
        price
        stock
        active
      }
    }
  }
}
```

### Create successful order

```graphql
mutation {
  createOrder(input: {
    customerName: "Anderson Riano"
    customerEmail: "anderson@test.com"
    items: [
      {
        productId: "/api/products/REPLACE_PRODUCT_UUID"
        quantity: 2
      }
    ]
  }) {
    order {
      id
      status
      total
      createdAt
    }
  }
}
```

### Create order with simulated payment failure

```graphql
mutation {
  createOrder(input: {
    customerName: "Rejected Customer"
    customerEmail: "fail@test.com"
    items: [
      {
        productId: "/api/products/REPLACE_PRODUCT_UUID"
        quantity: 1
      }
    ]
  }) {
    order {
      id
      status
      total
      createdAt
    }
  }
}
```

### Query order detail

```graphql
query {
  order(id: "/api/orders/REPLACE_ORDER_UUID") {
    id
    customerName
    customerEmail
    status
    total
    items {
      edges {
        node {
          quantity
          unitPrice
          subtotal
          product {
            name
            sku
          }
        }
      }
    }
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
```

## Testing

- Unit:
  - calculo de total y validaciones de dominio.
  - simulacion de pago aprobada/rechazada.
- Integracion:
  - create order.
  - reserva de stock ok/fail.
  - idempotencia.
  - confirmacion de orden.
- Messenger/Outbox:
  - `tests/Integration/Messaging/OutboxPublisherIntegrationTest.php`.

## Optional Kubernetes (`k8s/`)

```bash
cp k8s/secret.example.yaml k8s/secret.yaml
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/secret.yaml
kubectl apply -f k8s/
```

Useful commands:

```bash
kubectl get pods -n orderflow
kubectl logs -f deployment/orderflow-worker -n orderflow
kubectl logs -f deployment/orderflow-outbox-publisher -n orderflow
kubectl port-forward service/orderflow-app 8000:80 -n orderflow
kubectl port-forward service/rabbitmq 15672:15672 -n orderflow
```

## Technical Decisions

- **Symfony 7.4**: se uso 7.4 porque el bootstrap con 7.2 quedo bloqueado por advisories de seguridad del lock inicial.
- **Docker host ports**: se mapearon `8080` (app) y `5433` (PostgreSQL) para evitar conflictos locales detectados en el host.
- **GraphQL IDs**: API Platform expone IDs como IRI (`/api/...`). `createOrder` acepta `productId` en formato UUID o IRI.
- **Idempotencia**: guard por handler con persistencia en `processed_messages` y constraint unico en `message_id`.
- **Outbox**: se implemento patron transaccional para evitar dual-write (DB + broker).
- **Inventory locking**: se usa `PESSIMISTIC_WRITE` para reducir riesgo de sobreventa concurrente.
