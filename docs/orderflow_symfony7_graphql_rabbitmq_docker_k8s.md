# OrderFlow Symfony 7 - GraphQL, RabbitMQ, Docker and Kubernetes

## Context

This project is a backend technical test built with Symfony 7, GraphQL, RabbitMQ and PostgreSQL.

The goal is not to create a large application, but to demonstrate senior-level backend skills through a small, well-designed, maintainable and production-oriented system.

The project must show:

- Clean architecture decisions.
- Good separation of concerns.
- GraphQL API design.
- Asynchronous processing with RabbitMQ.
- Symfony Messenger usage.
- Domain-driven thinking.
- Error handling.
- Idempotency.
- State management.
- Tests.
- Dockerized local environment.
- Optional Kubernetes manifests for deployment readiness.
- Clear technical documentation.

This project should be implemented as if it were a real production backend, but with controlled scope.

---

## Main Idea

Build an order processing backend.

A client can create an order using GraphQL. The order is created with status `PENDING`. After that, the system processes the order asynchronously using RabbitMQ and Symfony Messenger.

The order flow is:

1. Create order through GraphQL mutation.
2. Publish an `OrderCreatedMessage`.
3. Reserve product inventory asynchronously.
4. Simulate payment approval asynchronously.
5. Confirm the order asynchronously.
6. Simulate customer notification asynchronously.
7. Store an event log for every important step.

No real payment gateway or email provider is required. These parts must be simulated, but the code should be designed in a way that real providers could be added later.

---

## Required Stack

Use the following stack:

- PHP 8.3 or higher.
- Symfony 7.
- PostgreSQL.
- Doctrine ORM.
- API Platform with GraphQL enabled.
- Symfony Messenger.
- RabbitMQ through AMQP transport.
- Docker Compose.
- PHPUnit.
- PHPStan or Psalm.
- PHP CS Fixer or Symfony Coding Standards.
- Optional Kubernetes manifests.

---

## Technical Goal

This project must demonstrate senior backend capability.

The implementation must prove knowledge in:

- Application architecture.
- Domain modeling.
- Transaction management.
- Asynchronous processing.
- Retry handling.
- Failure handling.
- Idempotency.
- Observability through logs and event history.
- GraphQL API design.
- Docker-based local development.
- Optional deployment thinking using Kubernetes.

Do not build a simple CRUD.

The project must be small, but polished.

---

## Functional Requirements

### Product Management

The system must support products.

Each product must have:

- `id`
- `name`
- `sku`
- `price`
- `stock`
- `isActive`
- `createdAt`
- `updatedAt`

Rules:

- SKU must be unique.
- Price must be greater than zero.
- Stock cannot be negative.
- Inactive products cannot be ordered.

---

### Order Management

The system must support customer orders.

Each order must have:

- `id`
- `customerName`
- `customerEmail`
- `status`
- `total`
- `createdAt`
- `updatedAt`

Each order must have one or more order items.

Each order item must have:

- `id`
- `order`
- `product`
- `quantity`
- `unitPrice`
- `subtotal`

Rules:

- An order must have at least one item.
- Quantity must be greater than zero.
- The order total must be calculated server-side.
- Product price must be snapshotted into `unitPrice`.
- Order total must not be received from the client.
- Client must not be allowed to directly change the order status.

---

## Order Statuses

Use a native PHP enum for order statuses.

Statuses:

```txt
PENDING
INVENTORY_RESERVED
PAYMENT_APPROVED
CONFIRMED
FAILED
CANCELLED
```

Expected transitions:

```txt
PENDING -> INVENTORY_RESERVED
INVENTORY_RESERVED -> PAYMENT_APPROVED
PAYMENT_APPROVED -> CONFIRMED

PENDING -> FAILED
INVENTORY_RESERVED -> FAILED
PAYMENT_APPROVED -> FAILED
```

Invalid transitions must be prevented.

---

## Order Event Log

The system must store an audit trail of the order flow.

Entity: `OrderEventLog`

Fields:

- `id`
- `order`
- `eventName`
- `status`
- `payload`
- `errorMessage`
- `createdAt`

Examples of event names:

```txt
ORDER_CREATED
INVENTORY_RESERVED
INVENTORY_RESERVATION_FAILED
PAYMENT_APPROVED
PAYMENT_REJECTED
ORDER_CONFIRMED
NOTIFICATION_SENT
NOTIFICATION_FAILED
```

This table is important because it demonstrates observability, traceability and production thinking.

---

## GraphQL Requirements

Expose the API using GraphQL.

The project must provide at least the following GraphQL operations.

---

### Mutation: Create Order

Example:

```graphql
mutation {
  createOrder(input: {
    customerName: "Anderson Riaño",
    customerEmail: "anderson@test.com",
    items: [
      {
        productId: "PRODUCT_UUID",
        quantity: 2
      }
    ]
  }) {
    id
    status
    total
    createdAt
  }
}
```

Behavior:

- Validate input.
- Validate product existence.
- Validate product active status.
- Validate quantity.
- Calculate totals.
- Persist order in `PENDING` status.
- Persist order items.
- Register `ORDER_CREATED` event.
- Dispatch `OrderCreatedMessage`.
- Return the created order.

---

### Query: Get Order By ID

Example:

```graphql
query {
  order(id: "ORDER_UUID") {
    id
    customerName
    customerEmail
    status
    total
    items {
      id
      quantity
      unitPrice
      subtotal
      product {
        id
        name
        sku
      }
    }
    events {
      eventName
      status
      errorMessage
      createdAt
    }
  }
}
```

---

### Query: List Products

Example:

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
        isActive
      }
    }
  }
}
```

---

## Asynchronous Processing Requirements

Use Symfony Messenger and RabbitMQ.

The following messages must exist:

```txt
OrderCreatedMessage
InventoryReservedMessage
PaymentApprovedMessage
OrderConfirmedMessage
```

The following handlers must exist:

```txt
OrderCreatedMessageHandler
InventoryReservedMessageHandler
PaymentApprovedMessageHandler
OrderConfirmedMessageHandler
```

---

### Handler 1: OrderCreatedMessageHandler

Responsibility:

- Load the order.
- Validate that the order is still `PENDING`.
- Check product stock.
- Reserve inventory.
- Change order status to `INVENTORY_RESERVED`.
- Register `INVENTORY_RESERVED` event.
- Dispatch `InventoryReservedMessage`.

Failure behavior:

- If stock is not available, mark order as `FAILED`.
- Register `INVENTORY_RESERVATION_FAILED`.
- Do not dispatch next message.

---

### Handler 2: InventoryReservedMessageHandler

Responsibility:

- Load the order.
- Validate that the order status is `INVENTORY_RESERVED`.
- Simulate payment processing.
- If payment is approved:
  - Change status to `PAYMENT_APPROVED`.
  - Register `PAYMENT_APPROVED`.
  - Dispatch `PaymentApprovedMessage`.

Failure behavior:

- If payment fails:
  - Change status to `FAILED`.
  - Register `PAYMENT_REJECTED`.
  - Release reserved stock if applicable.

The payment simulation may use deterministic behavior.

Suggested rule:

- Reject payment if customer email contains `fail`.
- Approve otherwise.

This makes tests predictable.

---

### Handler 3: PaymentApprovedMessageHandler

Responsibility:

- Load the order.
- Validate that the order status is `PAYMENT_APPROVED`.
- Change status to `CONFIRMED`.
- Register `ORDER_CONFIRMED`.
- Dispatch `OrderConfirmedMessage`.

---

### Handler 4: OrderConfirmedMessageHandler

Responsibility:

- Load the order.
- Validate that the order status is `CONFIRMED`.
- Simulate notification sending.
- Register `NOTIFICATION_SENT`.

No real email integration is required.

---

## Idempotency Requirements

The system must implement basic idempotency for asynchronous messages.

Create an entity/table:

```txt
ProcessedMessage
```

Fields:

- `id`
- `messageId`
- `messageName`
- `processedAt`

Each message must carry a unique `messageId`.

Before processing a message, the handler must verify if the message was already processed.

If the message was already processed:

- Do not process it again.
- Do not throw an error.
- Register a debug/info log if useful.

This demonstrates senior-level awareness of retries and duplicate message delivery.

---

## Error Handling Requirements

The project must handle errors cleanly.

Required behavior:

- Domain validation errors should be clear.
- GraphQL errors should not expose internal stack traces.
- Failed asynchronous messages should be retried according to Messenger configuration.
- After retries are exhausted, failed messages should go to a failure transport.
- Order state must remain consistent after failures.

Messenger should be configured with retry strategy.

Example expected behavior:

```yaml
framework:
  messenger:
    failure_transport: failed

    transports:
      async:
        dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
        retry_strategy:
          max_retries: 3
          delay: 1000
          multiplier: 2

      failed:
        dsn: 'doctrine://default?queue_name=failed'
```

Adapt the configuration if needed.

---

## Architecture Requirements

Use a clear layered architecture.

Recommended structure:

```txt
src/
  Domain/
    Order/
      Entity/
      Enum/
      Repository/
      Service/
      Exception/

    Product/
      Entity/
      Repository/
      Exception/

    Shared/
      ValueObject/
      Exception/

  Application/
    Order/
      Command/
      Handler/
      DTO/
      Service/

    Inventory/
      Service/

    Payment/
      Service/

    Notification/
      Service/

  Infrastructure/
    Doctrine/
      Repository/

    Messenger/
      Message/
      Handler/

    GraphQL/
      Resolver/
      Mutation/
      Query/

  UI/
    GraphQL/
```

The final structure may be adjusted to Symfony conventions, but the project must clearly separate:

- Domain logic.
- Application use cases.
- Infrastructure adapters.
- GraphQL/API layer.

Avoid putting business logic directly inside controllers, GraphQL resolvers or Doctrine entities when it does not belong there.

---

## Important Design Rules

The implementation must follow these rules:

- Keep business rules in domain/application services.
- Keep GraphQL resolvers thin.
- Do not expose internal infrastructure details to GraphQL.
- Use DTOs for input where useful.
- Use Doctrine repositories for persistence.
- Use transactions when creating orders and updating stock.
- Avoid duplicated business logic.
- Prefer explicit exceptions over generic runtime exceptions.
- Use enums for status values.
- Use value objects where they add clarity, for example Money or Email, but do not over-engineer.
- Keep the project small but polished.
- Do not create unnecessary microservices for this technical test.

---

## Database Requirements

Use PostgreSQL.

Required tables/entities:

```txt
products
orders
order_items
order_event_logs
processed_messages
```

Migrations must be generated and included.

Provide fixtures or seeders for sample products.

Example products:

```txt
SKU-KEYBOARD-001
Mechanical Keyboard
Price: 120.00
Stock: 10

SKU-MOUSE-001
Wireless Mouse
Price: 65.00
Stock: 20

SKU-MONITOR-001
27 Inch Monitor
Price: 300.00
Stock: 5
```

---

## Docker Requirements

Docker is mandatory.

Provide a `docker-compose.yml` with:

- PHP/Symfony application.
- PostgreSQL.
- RabbitMQ with management UI.
- Optional Nginx container.

RabbitMQ management UI should be accessible locally.

Suggested ports:

```txt
Symfony app: 8000
PostgreSQL: 5432
RabbitMQ AMQP: 5672
RabbitMQ Management: 15672
```

Suggested Docker services:

```yaml
services:
  php:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    volumes:
      - .:/var/www/html
    depends_on:
      - database
      - rabbitmq

  database:
    image: postgres:16
    environment:
      POSTGRES_DB: orderflow
      POSTGRES_USER: app
      POSTGRES_PASSWORD: app
    ports:
      - "5432:5432"

  rabbitmq:
    image: rabbitmq:3-management
    ports:
      - "5672:5672"
      - "15672:15672"
```

Adapt the final configuration if needed, but keep Docker Compose functional from scratch.

---

## Kubernetes Requirements

Kubernetes is optional but highly recommended.

If included, create a `k8s/` folder with basic manifests.

The Kubernetes setup does not need to be production-perfect, but it must demonstrate deployment awareness.

Required manifests if Kubernetes is implemented:

```txt
k8s/
  namespace.yaml
  configmap.yaml
  secret.example.yaml
  app-deployment.yaml
  app-service.yaml
  worker-deployment.yaml
  postgres-deployment.yaml
  postgres-service.yaml
  rabbitmq-deployment.yaml
  rabbitmq-service.yaml
```

Expected Kubernetes components:

### Namespace

Create a namespace:

```txt
orderflow
```

### Symfony App Deployment

The app deployment must run the Symfony HTTP application.

It should expose the app through a ClusterIP service.

### Worker Deployment

Create a separate worker deployment for Symfony Messenger.

The worker command should be similar to:

```bash
php bin/console messenger:consume async -vv --time-limit=3600 --memory-limit=256M
```

This demonstrates that background workers are separated from the web application.

### PostgreSQL

For local Kubernetes demo, PostgreSQL can be deployed as a simple Deployment and Service.

A production note should explain that in real production this should usually be a managed database or StatefulSet with persistent volumes.

### RabbitMQ

RabbitMQ can be deployed as a simple Deployment and Service for demo purposes.

A production note should explain that in real production this should use a RabbitMQ operator, persistent volumes, proper credentials and monitoring.

### Secrets

Do not commit real secrets.

Create only:

```txt
secret.example.yaml
```

The final README must explain that the developer should copy it to `secret.yaml` locally.

Example:

```bash
cp k8s/secret.example.yaml k8s/secret.yaml
```

### Kubernetes Commands

The README must include commands like:

```bash
kubectl apply -f k8s/namespace.yaml
kubectl apply -f k8s/configmap.yaml
kubectl apply -f k8s/secret.yaml
kubectl apply -f k8s/
```

Also include:

```bash
kubectl get pods -n orderflow
kubectl logs -f deployment/orderflow-worker -n orderflow
kubectl port-forward service/orderflow-app 8000:80 -n orderflow
kubectl port-forward service/rabbitmq 15672:15672 -n orderflow
```

Important:

Kubernetes must not replace Docker Compose for local development.

Docker Compose is mandatory for easy local execution.

Kubernetes is an optional demonstration of deployment readiness.

---

## Environment Variables

Provide a `.env.example`.

Required variables:

```env
APP_ENV=dev
APP_SECRET=change_me

DATABASE_URL="postgresql://app:app@database:5432/orderflow?serverVersion=16&charset=utf8"

MESSENGER_TRANSPORT_DSN=amqp://guest:guest@rabbitmq:5672/%2f/messages
```

Adjust names depending on final Docker service names.

---

## Commands Required In Final README

The final README must explain how to run:

```bash
docker compose up -d --build
```

```bash
docker compose exec php composer install
```

```bash
docker compose exec php php bin/console doctrine:migrations:migrate
```

```bash
docker compose exec php php bin/console doctrine:fixtures:load
```

```bash
docker compose exec php php bin/console messenger:consume async -vv
```

```bash
docker compose exec php php bin/phpunit
```

Also include commands for:

```bash
php bin/console messenger:failed:show
php bin/console messenger:failed:retry
php bin/console messenger:failed:remove
```

---

## Testing Requirements

Add tests for the most important behavior.

Minimum tests:

### Unit Tests

- Order total calculation.
- Invalid order without items.
- Invalid quantity.
- Invalid status transition.
- Payment simulation approval.
- Payment simulation rejection.

### Integration Tests

- Create order use case.
- Stock reservation success.
- Stock reservation failure.
- Idempotent message handling.
- Order confirmed after successful flow.

### GraphQL Tests

- `createOrder` mutation.
- `order` query.

Tests do not need to cover every line, but they must prove the critical business flow.

---

## Code Quality Requirements

Add and configure:

- PHPStan or Psalm.
- PHP CS Fixer or Easy Coding Standard.
- PHPUnit.

Add Composer scripts:

```json
{
  "scripts": {
    "test": "php bin/phpunit",
    "analyse": "phpstan analyse src tests",
    "cs:fix": "php-cs-fixer fix"
  }
}
```

Adapt commands depending on chosen tools.

---

## Expected Deliverables

The project must include:

```txt
README.md
docker-compose.yml
.env.example
composer.json
src/
config/
migrations/
tests/
fixtures/
docker/
k8s/
```

The README must explain:

- What the project does.
- Why RabbitMQ is used.
- Why GraphQL is used.
- How the asynchronous order flow works.
- How idempotency is handled.
- How to run the project with Docker.
- How to run workers.
- How to run tests.
- How to optionally deploy locally with Kubernetes.
- Example GraphQL queries and mutations.
- Possible future improvements.

---

## Example GraphQL Payloads

### Create Successful Order

```graphql
mutation {
  createOrder(input: {
    customerName: "Anderson Riaño",
    customerEmail: "anderson@test.com",
    items: [
      {
        productId: "REPLACE_WITH_PRODUCT_ID",
        quantity: 2
      }
    ]
  }) {
    id
    status
    total
    createdAt
  }
}
```

### Create Order With Payment Failure

```graphql
mutation {
  createOrder(input: {
    customerName: "Rejected Customer",
    customerEmail: "fail@test.com",
    items: [
      {
        productId: "REPLACE_WITH_PRODUCT_ID",
        quantity: 1
      }
    ]
  }) {
    id
    status
    total
    createdAt
  }
}
```

### Get Order Detail

```graphql
query {
  order(id: "REPLACE_WITH_ORDER_ID") {
    id
    customerName
    customerEmail
    status
    total
    items {
      quantity
      unitPrice
      subtotal
      product {
        name
        sku
      }
    }
    events {
      eventName
      status
      errorMessage
      createdAt
    }
  }
}
```

---

## Acceptance Criteria

The project is considered complete when:

- A user can create an order through GraphQL.
- The order is stored as `PENDING`.
- A message is sent to RabbitMQ.
- A worker can process the order.
- Inventory is reserved.
- Payment is simulated.
- The order is confirmed if payment is approved.
- The order fails if inventory or payment fails.
- Every important step is stored in `OrderEventLog`.
- Duplicate message processing is prevented.
- Tests cover the main flow.
- Docker environment works from scratch.
- Kubernetes manifests exist if the optional deployment part is implemented.
- README is clear enough for another developer to run the project.

---

## Senior-Level Expectations

Do not build this as a simple CRUD.

The project must demonstrate engineering judgment.

Prioritize:

- Simplicity with structure.
- Clear naming.
- Explicit business rules.
- Small cohesive classes.
- Good error messages.
- Predictable tests.
- Transactional consistency.
- Observable flow through event logs.
- Clear boundaries between API, application, domain and infrastructure.
- Docker-first local development.
- Optional Kubernetes readiness.

Avoid:

- Huge generic services.
- Business logic inside GraphQL resolvers.
- Magic strings for statuses.
- Overengineering with unnecessary abstractions.
- Unclear folder structure.
- Unhandled async failures.
- Missing documentation.
- Kubernetes manifests that do not match the real Docker image or environment variables.

---

## Suggested Implementation Plan

Before writing code, create a detailed implementation plan.

The plan must include:

1. Project setup.
2. Docker setup.
3. Dependency installation.
4. Database entities and migrations.
5. Domain enums and services.
6. GraphQL schema/resources.
7. Application use cases.
8. Messenger messages and handlers.
9. RabbitMQ transport configuration.
10. Idempotency implementation.
11. Event logging.
12. Fixtures.
13. Tests.
14. Kubernetes manifests, if included.
15. README final update.
16. Final verification commands.

After creating the plan, execute it step by step.

Do not skip tests.

Do not leave TODOs unless they are listed in a final `Future Improvements` section.

---

## Prompt For Codex

Read this entire README carefully.

First, generate a detailed technical implementation plan.

Then implement the complete project following the plan.

Respect the defined scope.

Do not turn this into a generic CRUD.

The focus must be:

- Symfony 7.
- GraphQL.
- RabbitMQ.
- Symfony Messenger.
- PostgreSQL.
- Docker Compose.
- Clean architecture.
- Order state machine.
- Idempotency.
- Event logs.
- Tests.
- Optional Kubernetes manifests.

When finished, verify or document all commands required to run:

- Installation.
- Docker environment.
- Migrations.
- Fixtures.
- GraphQL examples.
- RabbitMQ workers.
- Tests.
- Static analysis.
- Optional Kubernetes deployment.

If a technical decision requires adaptation, explain it in the final README under a section called `Technical Decisions`.

---

## Future Improvements

Include this section in the final README:

- Real payment gateway integration.
- Real email/SMS notification provider.
- Authentication and authorization.
- Admin dashboard.
- Order cancellation flow.
- Stock reservation expiration.
- Outbox pattern.
- Dead-letter queue monitoring.
- Metrics and tracing.
- CI/CD pipeline.
- Kubernetes production hardening.
- Horizontal worker autoscaling.
- Managed PostgreSQL.
- RabbitMQ operator.
