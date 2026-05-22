# Notification Service

Laravel API-сервис для массовой отправки SMS/Email уведомлений с приоритетной очередью, идемпотентностью и историей статусов доставки.

## Стек

- PHP 8.5, Laravel 13
- PostgreSQL
- RabbitMQ priority queue
- Redis для cache/idempotency TTL
- PHPUnit integration tests

## В рамках тестового задания (для простоты):

- Отсутствует авторизация / CORS
- Отсутствует nginx / fpm, запуск API происходит через `artisan serve`
- Consumer запускается в виде отдельного контейнера, а не через `supervisor`
- Отсутствует проверка существования пользователя
- В качестве идентификаторов пользователей принимаются phones/emails
- RabbitMQ exchange/queue декларируются из кода, а не из `definitions.json`
- Нет PHPStan и мутационных тестов

## Архитектура

`POST /api/v1/notifications` создает batch рассылки и записи `notifications` в PostgreSQL со статусом `queued`. Каждое уведомление публикуется в RabbitMQ durable queue `notifications.priority`; `transactional` сообщения получают priority `10`, `marketing` — priority `1`.

Worker `php artisan notifications:consume` читает RabbitMQ, вызывает mock SMS/Email provider, фиксирует `sent`, затем `delivered` или `dropped`. Повторная доставка брокером безопасна: terminal-статусы `delivered` и `dropped` больше не отправляются провайдеру повторно. Повторные API-запросы защищены `Idempotency-Key` header, `idempotency_key` body field или автоматическим fingerprint payload.

## Запуск

```bash
docker compose up -d --build
```

Сервисы:

- API: `http://localhost:8000`
- RabbitMQ management: `http://localhost:15672` (`notification_service` / `notification_service`)
- PostgreSQL: `localhost:5432`
- Redis: `localhost:6379`

После старта `app` сам выполнит `composer install`, `php artisan key:generate` и `php artisan migrate --force`. Worker стартует отдельным compose-сервисом.

## API

OpenAPI-файл: [`openapi.yaml`](openapi.yaml).

### Создать batch уведомлений

```bash
curl -X POST http://localhost:8000/api/v1/notifications \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: route-change-42' \
  -d '{
    "channel": "email",
    "priority": "transactional",
    "message": "Route changed",
    "recipient_ids": ["driver@example.com", "dispatcher@example.com"]
  }'
```

Поля:

- `channel`: `email` или `sms`
- `priority`: `transactional` или `marketing`
- `message`: текст сообщения
- `recipient_ids`: массив идентификаторов подписчиков, email-адресов или телефонов
- `idempotency_key`: опционально, альтернатива header `Idempotency-Key`

### История уведомлений подписчика

```bash
curl http://localhost:8000/api/v1/subscribers/driver@example.com/notifications
```

Статусы:

- `queued` — принято и ожидает отправки
- `sent` — передано mock-провайдеру
- `delivered` — провайдер подтвердил доставку
- `dropped` — permanent failure, например невалидный email/телефон

## Тесты

```bash
php artisan test
```

## Mock-провайдеры

Email provider доставляет адреса с `@`, SMS provider доставляет номера формата E.164-ish. Невалидные получатели переходят в `dropped`. Сообщение с текстом `[temporary-fail]` имитирует временную ошибку провайдера для retry-сценариев.
