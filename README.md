## Invoice Structure:

The invoice should contain the following fields:
* **Invoice ID**: Auto-generated during creation.
* **Invoice Status**: Possible states include `draft,` `sending,` and `sent-to-client`.
* **Customer Name** 
* **Customer Email** 
* **Invoice Product Lines**, each with:
  * **Product Name**
  * **Quantity**: Integer, must be positive. 
  * **Unit Price**: Integer, must be positive.
  * **Total Unit Price**: Calculated as Quantity x Unit Price. 
* **Total Price**: Sum of all Total Unit Prices.

## Required Endpoints:

1. **View Invoice**: Retrieve invoice data in the format above.
2. **Create Invoice**: Initialize a new invoice.
3. **Send Invoice**: Handle the sending of an invoice.

## Functional Requirements:

### Invoice Criteria:

* An invoice can only be created in `draft` status. 
* An invoice can be created with empty product lines. 
* An invoice can only be sent if it is in `draft` status. 
* An invoice can only be marked as `sent-to-client` if its current status is `sending`. 
* To be sent, an invoice must contain product lines with both quantity and unit price as positive integers greater than **zero**.

### Invoice Sending Workflow:

* **Send an email notification** to the customer using the `NotificationFacade`. 
  * The email's subject and message may be hardcoded or customized as needed. 
  * Change the **Invoice Status** to `sending` when the invoice is sent.

### Delivery:

* Upon successful delivery by the Dummy notification provider:
  * The **Notification Module** triggers a `WebhookDeliveredEvent` via webhook.
  * The **Invoice Module** listens for and captures this event.
  * The **Invoice Status** is updated from `sending` to `sent-to-client`.
  * **Note**: This transition requires that the invoice is currently in the `sending` status.

## Technical Requirements:

* **Preferred Approach**: Domain-Driven Design (DDD) is preferred for this project. If you have experience with DDD, please feel free to apply this methodology. However, if you are more comfortable with another approach, you may choose an alternative structure.
* **Alternative Submission**: If you have a different, comparable project or task that showcases your skills, you may submit that instead of creating this task.
* **Tests**: Core invoice logic must be covered by tests. Choose the testing strategy that best demonstrates the correctness of your solution.
* **Documentation**: Candidates are encouraged to document their decisions and reasoning in comments or a README file, explaining why specific implementations or structures were chosen.

## Evaluation Criteria

We look at the overall shape of the solution, not a checklist. In particular:
* Architecture, separation of concerns, and clarity of module boundaries.
* Testing strategy — what you chose to test and why.
* How the send/deliver workflow behaves when things do not go as expected.

## Note on the Notification Module:

The Notification module included in this repository is a minimal, mock integration example. It is intentionally simple and should not be treated as a reference for DDD structure or for the expected invoice design.

## Setup Instructions:

* Start the project by running `./start.sh`.
* To access the container environment, use: `docker compose exec app bash`.

## Solution

Design decisions, error handling and the tests are described in
[src/Modules/Invoices/README.md](src/Modules/Invoices/README.md).

### Running it

After `./start.sh` the API is available at `http://localhost/api`.

```bash
curl -s -X POST localhost/api/invoices -H 'Content-Type: application/json' \
  -d '{"customerName":"Jane Doe","customerEmail":"jane@example.com","productLines":[{"productName":"Consulting","quantity":3,"unitPrice":1500}]}'
curl -s -X POST localhost/api/invoices/<id>/send
curl -s localhost/api/invoices/<id>
```

The delivery webhook now requires a signature, so calling
`/api/notification/hook/delivered/<id>` directly returns `401`. To simulate the
provider confirming the delivery:

```bash
docker compose exec app php artisan notifications:simulate-delivered <id>
```

The command signs the request and sends it through the normal route, middleware and listener.

Tests: `docker compose exec app php artisan test`

### Changes outside the Invoices module

* `src/Modules/Shared`: base class for business rules, error types mapped to HTTP statuses and a command bus that runs each command in a transaction. `bootstrap/app.php` registers the error mapping, forces JSON responses for `/api/*` and registers the simulate command.
* Notifications webhook: added a signature check (`X-Notification-Signature`, HMAC-SHA256 with `NOTIFICATION_WEBHOOK_SECRET`), otherwise anyone could mark an invoice as delivered. This changes the given module's contract, so `NotificationControllerTest` now sends signed requests. Its assertions are unchanged.
* `config/database.php`: SQLite starts transactions with `BEGIN IMMEDIATE` and waits up to 5 s for a lock. Without that, two overlapping commands fail with "database is locked" instead of the second one waiting. I also replaced `PDO::MYSQL_ATTR_SSL_CA` with `Pdo\Mysql::ATTR_SSL_CA`, because the old constant is deprecated in PHP 8.5.
* `StatusEnum` was replaced by `Modules\Invoices\Domain\ValueObjects\InvoiceStatus`.
* The product lines migration has a new `position` column that keeps the order of the lines. `./start.sh` runs `migrate:fresh`, so the schema is rebuilt.
* Tests use Pest 4 with in-memory SQLite, the concurrency tests use a temporary SQLite file. The original PHPUnit tests run as they were, apart from the signature header mentioned above.
* `composer.lock`: only Pest, PHPUnit 12 and their dependencies changed. Laravel and the other application packages have the same versions as in the task.
