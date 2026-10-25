# Asynchronous Processing & Symfony Messenger Architecture

Episciences uses [Symfony Messenger 7.x](https://symfony.com/doc/current/messenger.html) backed by a **Doctrine DBAL transport** (`doctrine://`) to handle asynchronous background tasks without requiring an external broker like RabbitMQ or Redis.

This architecture replaces legacy MySQL polling tables (`QUEUE_MESSAGE`, `QUEUE_SOLR_INDEX`) with standard message queues, automatic retries with jitter, failure dead-lettering, and dedicated CLI worker daemons.

---

## Architecture Overview

```
                      ┌───────────────────────────────────────┐
                      │    ZF1 Application (Web / CLI)        │
                      └──────────────────┬────────────────────┘
                                         │
             ┌───────────────────────────┴───────────────────────────┐
             │ BoundedRetryDispatcher (producer-side retry + jitter)  │
             └─────────────┬───────────────────────────┬─────────────┘
                           │                           │
          Queue: solr_index│                           │Queue: next_revalidation
                           ▼                           ▼
                 ┌───────────────────┐       ┌───────────────────┐
                 │messenger_messages │       │messenger_messages │
                 │(queue=solr_index) │       │(queue=next_reval) │
                 └─────────┬─────────┘       └─────────┬─────────┘
                           │                           │
   episciences:worker      ▼   episciences:worker      ▼
   --transport=solr_index      --transport=next_revalidation
                 ┌───────────────────┐       ┌───────────────────┐
                 │ Apache Solr Core  │       │ Next.js Frontend  │
                 │ (/update/json)    │       │ (/api/revalidate) │
                 └───────────────────┘       └───────────────────┘
```

### Why Separate Transports?

The platform defines two independent transports sharing the same underlying `messenger_messages` table via the `queue_name` column:

1. **`solr_index`**: Heavy document building (aggregating metadata, authors, affiliations, citations, full-text extractions, translations) and sending to Apache Solr.
2. **`next_revalidation`**: Lightweight authenticated HTTP `POST` requests to Next.js ISR cache invalidation endpoints (`/api/revalidate`).

**Separation rationale:** Isolating transports prevents resource contention. A long Solr document build or Solr server pause will never delay or starve real-time cache invalidations on the public Next.js frontend.

---

## Message Types and Handlers

| Transport | Message Class | Handler Class | Trigger | Action |
|-----------|---------------|---------------|---------|--------|
| `solr_index` | `Episciences\Solr\Indexing\Messenger\Message\IndexPaperMessage` | `IndexPaperMessageHandler` | Paper published, volume/section edited, metadata enriched | Builds Solr document via `DocumentBuilder` and indexes it in Solr |
| `solr_index` | `Episciences\Solr\Indexing\Messenger\Message\DeletePaperMessage` | `DeletePaperMessageHandler` | Paper unpublished or deleted | Sends delete query to Solr |
| `next_revalidation` | `Episciences\Next\Messenger\Message\RevalidateTagMessage` | `RevalidateTagMessageHandler` | Article, volume, section, or menu change | Sends POST to Next.js with cache tag and authentication token |

---

## Database Tables & Schema

All messaging state lives in the main application database (`episciences`).

### 1. `messenger_messages`
Standard Symfony Messenger table storing enqueued messages.
- `id` (bigint, auto-increment)
- `body` (longtext): serialized message and envelope stamps
- `headers` (longtext): envelope metadata
- `queue_name` (varchar(190)): `'solr_index'` or `'next_revalidation'`
- `created_at`, `available_at`, `delivered_at`

### 2. `messenger_failed`
Dead-letter queue table storing messages that exceeded the maximum retry count. Allows manual inspection and retrying.

### 3. Dispatch Failure Tables
- `solr_index_enqueue_failures`
- `next_revalidation_enqueue_failures`

When the database or network fails completely and the producer-side `BoundedRetryDispatcher` cannot write to `messenger_messages` after all retries, the failed envelope is recorded here so it can be re-dispatched once connectivity is restored.

### Initializing Tables
Run the `--setup` command once per transport:

```bash
php scripts/console.php episciences:queue --transport=solr_index --setup
php scripts/console.php episciences:queue --transport=next_revalidation --setup
```

---

## Reliability and Retry Strategy

### Producer Side: `BoundedRetryDispatcher`
When dispatching messages from web controllers or CLI scripts:
- Retries up to **3 times** if a transient database failure occurs.
- Implements exponential backoff with random jitter to prevent "thundering herd" spikes against MySQL.
- Falls back to recording in the transport-specific enqueue failure table if all retries fail.

### Consumer Side: Worker Retries
When consuming messages in `episciences:worker`:
- Messages are configured with an exponential retry strategy (multiplier 2).
- **Retry Delay Jitter (Symfony 7.1+)**: Each retry delay is randomized by ±10% to prevent synchronized worker retries.
- After reaching the retry threshold (default: 3 attempts), the message is unacknowledged and sent to the `messenger_failed` dead-letter queue.

---

## Operations & Production Deployment

Workers **must run continuously** under a process supervisor. A periodic cron tick is not sufficient.

### Systemd Service Configuration

A template systemd unit is provided in [`src/php-fpm/episciences-worker@.service`](../src/php-fpm/episciences-worker@.service).

1. **Install the service template:**
   ```bash
   sudo ln -sf /var/www/htdocs/src/php-fpm/episciences-worker@.service /etc/systemd/system/episciences-worker@.service
   sudo systemctl daemon-reload
   ```

2. **Enable and start workers for both transports:**
   ```bash
   sudo systemctl enable --now episciences-worker@solr_index
   sudo systemctl enable --now episciences-worker@next_revalidation
   ```

3. **Monitor logs and status:**
   ```bash
   sudo systemctl status episciences-worker@solr_index episciences-worker@next_revalidation
   sudo journalctl -u episciences-worker@solr_index -f
   ```

4. **Resource Management:**
   Each worker process restarts automatically after:
   - Processing `--limit=500` messages (prevents PHP memory leaks)
   - Running for `--time-limit=3600` seconds (1 hour)
   - Exceeding `--memory-limit=512M`

### Docker Compose (Development)

In local development (`docker-compose.yml`), two services run the workers automatically:
- `worker-solr-index`
- `worker-next-revalidation`

Both share the `php-fpm` container image and start with `make up` or `make dev-setup`.

---

## Queue Administration & Troubleshooting

Use the [`episciences:queue`](./console-commands.md#episciencesqueue) CLI command:

### Check Queue Statistics
```bash
php scripts/console.php episciences:queue --transport=solr_index --stats
php scripts/console.php episciences:queue --transport=next_revalidation --stats
```

### Inspect and Retry Dead-Letter Messages
```bash
# List failed messages
php scripts/console.php episciences:queue --transport=solr_index --list-failed

# Retry a specific failed message by ID
php scripts/console.php episciences:queue --transport=solr_index --retry=<message-id>
```

### Inspect and Recover Producer Dispatch Failures
```bash
# List enqueue failures
php scripts/console.php episciences:queue --transport=solr_index --list-dispatch-failures

# Retry dispatching to the main queue
php scripts/console.php episciences:queue --transport=solr_index --retry-dispatch-failure=<id>
```

### Interplay with Read-Only Maintenance Mode
During scheduled maintenance (`docs/read-only-mode.md`), database write queries are forbidden. Stop the workers before maintenance begins:
```bash
sudo systemctl stop episciences-worker@solr_index episciences-worker@next_revalidation
```
Restart them once maintenance completes.
