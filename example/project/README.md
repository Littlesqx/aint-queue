#### Install

```bash
composer install
```

#### Run Listener

```bash
./vendor/bin/aint-queue worker:listen --channel=example
```

#### Run Server

```bash
php -S localhost:8000 -t ./public/
```

#### Push job

```bash
curl -v http://localhost:8000
```

#### Check job status

via Console

```bash
./vendor/bin/aint-queue queue:status --channel=example
```

or start the dashboard server

```
./vendor/bin/aint-queue queue:dashboard  --addr={$ip}:{$host} --channel=example
```