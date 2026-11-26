# Invoices module

## Structure

```
src/Modules/
├── Invoices/
│   ├── Domain/          Invoice aggregate, value objects, rules, repository interface
│   ├── Application/     Commands/, Queries/, Ports/, Data/
│   └── Infrastructure/  HTTP, Eloquent, Notifications adapter and listener, service provider
├── Notifications/       given module, Invoices only uses its Api namespace
└── Shared/              business rule base, error types, transactional command bus
```

Domain and Application don't use Laravel. Application talks to the outside only through
`InvoiceRepository` and `Ports\InvoiceDelivery`. Only Infrastructure knows Laravel and the
Notifications module, and from Notifications it only uses `Modules\Notifications\Api`.
HTTP is in `Infrastructure/Http` because I treat it as one more adapter. The task says the
given module is not a reference, so I didn't copy its `Presentation/` folder.
`tests/Unit/ArchitectureTest.php` checks these dependencies and the naming.

Statuses: `draft` is where every invoice starts, `sending` means the e-mail was requested
but its delivery is not confirmed yet, `sent-to-client` means the provider confirmed it.

## API

| Method | Path | Response |
| --- | --- | --- |
| `POST` | `/api/invoices` | `201` `{"data":{"id":"<uuid>"}}` with a `Location` header |
| `GET` | `/api/invoices/{id}` | `200` invoice |
| `POST` | `/api/invoices/{id}/send` | `204` |
| `GET` | `/api/notification/hook/delivered/{id}` | `204`, needs a signature (see [Webhook signature](#webhook-signature)) |

Field names follow the task: `id`, `status`, `customerName`, `customerEmail`,
`productLines[]` (`productName`, `quantity`, `unitPrice`, `totalUnitPrice`) and
`totalPrice`. `productLines` can be left out, `null` or `[]`.

All `/api/*` responses are JSON, also without `Accept: application/json`. Validation
errors use Laravel's format (`422` with `errors`), e-mails are validated with the same
filter as in the domain (`email:filter`). Other errors look like
`{"error":{"code":"...","message":"..."}}`:

| Status | Code | When |
| --- | --- | --- |
| 422 | `INVOICE_MUST_BE_DRAFT` | sending an invoice that is not a draft |
| 422 | `INVOICE_MUST_HAVE_PRODUCT_LINES` | sending an invoice without lines |
| 422 | `INVOICE_MUST_BE_SENDING` | delivery report for an invoice that is not being sent |
| 422 | `INVALID_VALUE` | a value the domain rejects, when the call doesn't come through HTTP validation |
| 401 | `WEBHOOK_SIGNATURE_INVALID` | delivery report without a valid signature |
| 404 | `INVOICE_NOT_FOUND` | unknown invoice |
| 409 | `INVOICE_CHANGED_CONCURRENTLY` | another request changed the invoice in the meantime |
| 409 | `INVOICE_ALREADY_EXISTS` | create with an id that already exists |
| 503 | `INVOICE_DELIVERY_UNAVAILABLE` | the notification provider refused the e-mail |

## Design decisions

- `Invoice` has a private constructor, a `draft()` factory and two behaviours, `send()`
  and `confirmDelivery()`. Every condition is a separate rule class (`InvoiceMustBeDraft`
  etc.) and all of them are checked before the status changes, so a rejected call leaves
  the invoice as it was. The status can't be set from outside (`public private(set)`).
- Quantity and unit price must be positive already when the invoice is created, because
  the task lists it as part of the invoice structure. The send condition about positive
  values then always holds and `send()` only checks that there is at least one line.
  Allowing `0` in a draft wouldn't help anyway, there is no endpoint to edit a draft.
  Totals are calculated, not stored, and integer overflow is rejected.
- Customer and lines are `readonly` (no edit endpoint), so the repository only ever
  updates the status. `restore()` doesn't apply the sending rules, they belong to `send()`.
- Commands don't return anything. The controller generates the UUIDv7, dispatches the
  command and returns `201` with the id. Reading is a separate query that returns an
  `InvoiceView`, the aggregate is never serialized directly.
- Each command runs in one transaction (`TransactionalCommandBus`, one attempt). Load,
  change, save and the notification call commit or roll back together. Exceptions are
  turned into HTTP responses after they leave the bus (`FaultResponses`), so error
  handling can't commit half of a change.
- The repository maps the aggregate to separate Eloquent models. A new instance is always
  inserted, so creating the same id twice is a conflict and never overwrites an invoice.
  An update is a compare-and-set on the status the invoice was loaded with. That status is
  kept in a `WeakMap`, so the aggregate doesn't need a version field. Concurrent commands
  on the same invoice wait for each other: a row lock on PostgreSQL/MySQL, and on SQLite
  `BEGIN IMMEDIATE` with a 5 s `busy_timeout` (in the default mode SQLite fails the second
  writer with "database is locked").
- Notifications are behind the `InvoiceDelivery` port. `NotificationsInvoiceDelivery` sends
  the e-mail through `NotificationFacadeInterface` with the invoice id as the reference and
  turns a provider exception into `ExternalServiceUnavailable` (503).
  `ConfirmInvoiceDeliveryOnWebhook` listens to `WebhookDeliveredEvent` and dispatches
  `ConfirmInvoiceDeliveryCommand`.

## When something goes wrong

`SendInvoiceHandler` does `load -> send() -> save -> notify` in one transaction.

| Situation | Result |
| --- | --- |
| Invoice is not a draft or has no lines | `422`, nothing saved, no e-mail |
| The provider refuses the e-mail | `503`, rollback, the invoice stays a draft and can be sent again |
| Two sends of the same draft at the same time | the second one waits for the first commit, sees `sending` and gets `422`. The compare-and-set (`409`) is the last safety net. Only one e-mail, because the save happens before notify |
| Waiting for the lock longer than `busy_timeout` (SQLite, 5 s) | `500`, nothing saved, no e-mail, the client can retry |
| Delivery report for a `sending` invoice | `204`, status `sent-to-client` |
| Delivery report arrives before the send has committed | it waits for the commit, then `204` and `sent-to-client` |
| The same delivery report again | `204`, no change (providers can report a delivery more than once) |
| Delivery report for a draft | `422`, the provider gets a non-2xx response and retries |
| Forged or old report (wrong secret, other reference, outside ±5 min) | `401`, nothing dispatched |
| Report for a reference that isn't an invoice | `204`, ignored and logged (Notifications isn't only for invoices) |
| A row in the database the model rejects | `500` (`UnexpectedValueException`) |

Calling the provider inside the transaction means the lock is held while it runs: one row
on PostgreSQL/MySQL, all writes on SQLite. A slow provider slows down other writes. I
accepted that, because then a failed e-mail rolls the status back and two concurrent
requests can't send the e-mail twice.

Known gap: if the provider accepts the e-mail and then the commit fails, the invoice
stays a draft and sending it again e-mails the customer a second time. The proper fix is
a transactional outbox with an idempotency key on the provider side, and `NotifyData` has
no field for that. I preferred a draft that can be sent again over an invoice stuck in
`sending` without an e-mail. In production I'd also run the listener on a queue with
retries. Here it runs synchronously, so `./start.sh` works without a queue worker.

## Webhook signature

Without a signature anyone who knows an invoice id could mark it as `sent-to-client`.
The Notifications route now requires `X-Notification-Signature: t=<unix>,v1=<hex>`, where
`v1` is HMAC-SHA256 of `"<t>.<action>.<reference>"` with `NOTIFICATION_WEBHOOK_SECRET`.
The middleware checks it before the event is dispatched. The comparison is constant-time,
a signature only works for its own action and reference, the timestamp has to be within
5 minutes, and when the secret is missing the hook returns 500 instead of accepting
everything. Locally the 401 response mentions the simulate command. The route is still
`GET`, like in the given module.

The easiest way to confirm a delivery is
`docker compose exec app php artisan notifications:simulate-delivered <id>`. By hand:

```bash
export NOTIFICATION_WEBHOOK_SECRET=local-dev-webhook-secret-change-me  # value from .env.example
t=$(date +%s); sig=$(printf '%s' "$t.delivered.<id>" | openssl dgst -sha256 -hmac "$NOTIFICATION_WEBHOOK_SECRET" | sed 's/^.* //')
curl -s localhost/api/notification/hook/delivered/<id> -H "X-Notification-Signature: t=$t,v1=$sig"
```

## Access control (not done)

The task has no users, so anyone with an invoice id can view and send it. The UUID isn't
real protection: a UUIDv7 starts with a timestamp, and the id ends up in the `Location`
header, the e-mail and the logs. With users I would add authentication, store the issuer
on the invoice in `Invoice::draft()`, check an `InvoicePolicy` before the handler runs and
load invoices only within the issuer, so someone else's invoice returns `404`.

## Tests

Pest, run with `docker compose exec app php artisan test`.

| Layer | Where | What |
| --- | --- | --- |
| Domain | `tests/Unit/Invoices/Domain` | all transitions and rejections, which rule failed (`toBreak()`), no change after a rejection, restoring, totals, value objects. No Laravel |
| Application | `tests/Unit/Invoices/Application` | save happens before notify, no e-mail after a conflict or a rejected send |
| Persistence | `tests/Feature/Invoices/Persistence` | round-trip with line order, status update, conflicts on a repeated create and on a stale update, broken rows give a 500 |
| Concurrency | `tests/Feature/Invoices/Concurrency` | two requests in separate PHP processes on one SQLite file: the second send waits and gets `422` with only one e-mail sent, and a delivery report that comes before the send has committed waits and succeeds |
| HTTP | `tests/Feature/Invoices/Http` | the API (also without `Accept`), rollback after a failed e-mail, the whole create -> send -> webhook -> `sent-to-client` flow, also through the simulate command |
| Webhook signature | `tests/Feature/Notification/Http` | valid signatures pass. Missing, malformed, wrong secret, other reference or action, tampered, expired and future ones get `401` and nothing is dispatched. A missing secret fails |
| Architecture | `tests/Unit/ArchitectureTest.php` | layer and module dependencies, naming, final/readonly classes |

I checked that the important tests really catch the bug they are there for: removing the
transaction, swapping save and notify, or removing the compare-and-set or `BEGIN IMMEDIATE`
makes the matching test fail. The row lock on PostgreSQL/MySQL isn't tested, the tests run
on SQLite.

## Left out

Editing drafts, listing invoices, currencies (amounts are plain integers like in the
task, a real invoice would have a `Money` value object), authentication, PHPStan and
Deptrac.
