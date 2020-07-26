#### Install

```bash
composer install
```

#### Build images

```bash
docker build -t aint-queue:test .
```

#### Run AintQueue Console Tool

```bash
docker run -v /current/path:/var/www/ aint-queue:test queue:status --channel=example
```