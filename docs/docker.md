# Ambiente Docker

## Requisiti
- Docker Desktop installato e in esecuzione

## Avvio
```bash
# build immagini
docker compose build

# install composer (dentro container tools)
docker compose run --rm tools composer install --no-interaction --prefer-dist

# eseguire test
docker compose run --rm tools vendor/bin/phpunit --bootstrap tests/bootstrap.php --colors=always --testdox

# avviare server dev (porta 8080)
docker compose up app
```

## Helper
```bash
bash tools/dev.sh tools composer update
bash tools/dev.sh tools vendor/bin/phpunit --testsuite Unit
```
