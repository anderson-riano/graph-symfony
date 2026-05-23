# OrderFlow Symfony 7

Backend técnico con Symfony 7, GraphQL, RabbitMQ, Messenger, PostgreSQL y Docker.

## What this project does

OrderFlow permite crear órdenes por GraphQL y procesarlas de forma asíncrona:

1. `createOrder` crea la orden en estado `PENDING`.
2. Se publica `OrderCreatedMessage`.
3. Worker reserva inventario.
4. Worker simula pago.
5. Worker confirma orden.
6. Worker simula notificación.
7. Cada paso queda auditado en `order_event_logs`.

## Stack

- PHP 8.3
- Symfony 7.4
- API Platform GraphQL
- Symfony Messenger + RabbitMQ (AMQP)
- PostgreSQL 16
- Doctrine ORM + Migrations + Fixtures
- PHPUnit, PHPStan, PHP CS Fixer
- Docker Compose
- Kubernetes básico opcional (`k8s/`)

## Architecture

Estructura por capas:

- `Domain`: entidades, enums, reglas de negocio.
- `Application`: casos de uso y servicios de aplicación.
- `Infrastructure`: repositorios Doctrine, mensajes/handlers Messenger, mutation resolver GraphQL.
- `DataFixtures`: productos base.

Se evita lógica pesada en GraphQL: el resolver de `createOrder` delega al caso de uso `CreateOrderUseCase`.

## Async flow and reliability

- Estado de órdenes con enum nativo `OrderStatus`.
- Transiciones válidas controladas por dominio.
- Handlers:
  - `OrderCreatedMessageHandler`
  - `InventoryReservedMessageHandler`
  - `PaymentApprovedMessageHandler`
  - `OrderConfirmedMessageHandler`
- Idempotencia: tabla `processed_messages` + guard por `messageId`.
- Reintentos y cola de fallos en Messenger:
  - `async` con `max_retries=3`
  - `failed` transport en Doctrine

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

### 1) Build and start

```bash
docker compose up -d --build
```

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

### 5) Start worker

```bash
docker compose exec php php bin/console messenger:consume async -vv
```

### 6) Run tests

```bash
docker compose exec php php bin/phpunit
```

### 7) Static analysis and coding style

```bash
docker compose exec php composer analyse
docker compose exec php composer cs:fix
```

### 8) Failed message operations

```bash
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
kubectl port-forward service/orderflow-app 8000:80 -n orderflow
kubectl port-forward service/rabbitmq 15672:15672 -n orderflow
```

## Technical Decisions

- **Symfony 7.4**: se usó 7.4 porque el bootstrap con 7.2 quedó bloqueado por advisories de seguridad del lock inicial.
- **Docker host ports**: se mapearon `8080` (app) y `5433` (PostgreSQL) para evitar conflictos locales detectados en el host.
- **GraphQL IDs**: API Platform expone IDs como IRI (`/api/...`). `createOrder` acepta `productId` en formato UUID o IRI.
- **Idempotencia**: se aplicó por handler con persistencia en `processed_messages` y constraint único en `message_id`.

## Future Improvements

- Integración real con gateway de pagos.
- Integración real de email/SMS.
- Autenticación y autorización.
- Dashboard administrativo.
- Flujo de cancelación de órdenes.
- Expiración de reservas de stock.
- Outbox pattern.
- Monitoreo de dead-letter queues.
- Métricas y tracing.
- CI/CD pipeline.
- Hardening de Kubernetes para producción.
- Autoscaling horizontal de workers.
- PostgreSQL administrado.
- RabbitMQ operator.
"# graph-symfony" 
