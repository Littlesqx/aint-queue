#### Run Listener

```bash
./vendor/bin/aint-queue queue:listen --channel=example
```

#### Run Server

```bash
php -S localhost:8000 -t ./public/
```

#### Pushing job

```bash
curl -v http://localhost:8000
```

#### Check job status

```bash
./vendor/bin/aint-queue queue:status --channel=example
```