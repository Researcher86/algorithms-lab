# Algorithms Lab — план реализации (v2: в экосистеме Engineering Lab)

> **Что изменилось в v2.** План переписан под экосистему `engineering-lab`:
> книга `book/algorithms-lab`, конвенции проектов `php-systems-lab`, Docker,
> PHP 8.5, `bin/experiment` / `bin/benchmark`, `docs/PHASES.md` и контракт
> «код → глава книги». Содержательная часть по алгоритмам сохранена.

**Текущее состояние репозитория:** каркас Phase 0 уже создан — Docker-образ
`php:8.5-cli`, Compose, Makefile, Composer, PHPUnit, PHPStan level 8,
PHP-CS-Fixer, GitHub Actions, README, docs и `DocumentationTest`. Осталось:
первый коммит, публичный remote и перенос этого плана в `docs/PHASES.md`.
Проверки `make test`, `make analyse`, `make format-check` проходят в контейнере.

---

## 1. Куда встраивается проект

```text
engineering-lab/                      # рабочая папка, не git-репозиторий
├── book/                             # git: Researcher86/engineering-lab-book
│   ├── README.md                     # оглавление всей серии
│   └── algorithms-lab/               # 7 глав книги (RU), сейчас заглушки
│       ├── README.md
│       ├── 01-hash-table-and-bloom-filter.md
│       ├── 02-lru-lfu-cache.md
│       ├── 03-graph-algorithms.md
│       ├── 04-b-tree-and-search.md
│       ├── 05-consistent-hashing.md
│       ├── 06-top-k-and-stream-processing.md
│       └── 07-external-sorting.md
├── algorithms-lab/                   # git: Researcher86/algorithms-lab — этот проект
│   ├── src/                          # реализации + harness
│   ├── experiments/                  # запускаемые опыты
│   ├── benchmarks/                   # замеры
│   └── docs/                         # инженерная документация (EN)
├── php-systems-lab/                  # 9 соседних учебных проектов
│   ├── php-mini-cache/
│   ├── php-mini-database/
│   └── ...
└── architecture-lab/ ...             # другие лаборатории серии
```

### 1.1. Роли

| Компонент | Роль |
|---|---|
| `book/algorithms-lab` | Повествование на русском: 7 глав, каждая объясняет механизм и ссылается на код |
| `algorithms-lab` | Исполняемый источник истины: код, эксперименты, измерения, инженерные docs на английском |
| `book/php-systems-lab/tools/check_book.py` | Проверяет, что код в книге совпадает с кодом репозитория на закреплённом SHA/теге |
| `php-systems-lab/*` | Соседние лаборатории: общие конвенции и сценарии, где алгоритмы применяются |
| `engineering-lab-book` | Зонтичный репозиторий книги и CI проверки глав |

### 1.2. Контракт с экосистемой

1. **Одна глава = один модуль.** Порядок и номера глав фиксированы
   `book/algorithms-lab/README.md`: 01 Hash/Bloom, 02 Cache, 03 Graphs,
   04 B-Tree, 05 Consistent Hashing, 06 Top-K, 07 External Sorting.
   Внутренний порядок разработки может отличаться, но имена глав и docs — нет.
2. **Книга пишется по коду, а не наоборот.** Сначала реализация, тесты и
   измерение; потом глава с выдержками из настоящих файлов.
3. **Ссылки в книге закреплены.** Каждый линк ведёт на `v1.0.0` или полный SHA,
   никогда на `master`. Репозиторий должен быть публичным:
   `github.com/Researcher86/algorithms-lab`.
4. **Языки.** Код, комментарии, README и `docs/` — английский (как в
   `php-systems-lab`). Главы книги — русский.
5. **Эксперимент запускается одной командой.** `make experiment ARGS="hash:load-factor"`;
   в главе приводится реальный вывод из контейнера.
6. **Книга проверяется машинно.** `check_book.py` сверяет каждый code block с
   pinned-источником; для algorithms-lab его нужно научить семи главам
   (сейчас в нём жёстко зашиты 26 глав php-systems-lab).
7. **Один репозиторий на всю лабораторию.** Не семь отдельных репозиториев:
   у книги один `algorithms-lab/README.md`, а темы делят Foundation и harness.
   Если позже делить — это меняет все ссылки в книге, поэтому решение
   фиксируется сейчас (рекомендация: один репозиторий).

### 1.3. Что это меняет по сравнению с v1

| Было в v1 | Стало в v2 | Причина |
|---|---|---|
| PHP 8.3+ | PHP `^8.5`, образ `php:8.5-cli` | Как в `php-memory-lab` и `php-mini-cache` |
| Локальный запуск | Docker + `make` | CI и разработка в одном окружении |
| `bin/lab` | `bin/experiment` + `bin/benchmark` | Конвенция `php-memory-lab` |
| `docs/01-hash-table.md` и т.п. | Главы в `book/`, инженерные docs в репозитории | Не дублировать книгу |
| Порядок: hash → cache → consistent → b-tree → graphs | Порядок книги: hash → cache → graphs → b-tree → consistent → top-k → sort | Номера глав фиксированы |
| PHPStan max | PHPStan level 8, paths включают experiments/benchmarks | Как в соседних проектах |
| `PLAN.md` в корне навсегда | `PLAN.md` временно → `docs/PHASES.md` | Конвенция build journal |
| CI-матрица 8.3/8.4 | CI через `make` в Docker-образе проекта | Проверяем то окружение, в котором работаем |
| Нет remote | Публичный GitHub + тег `v1.0.0` | Без этого ссылки в книге не работают |
| Нет проверки docs | `DocumentationTest` | Ссылки и команды не должны гнить |

---

## 2. Принципы

1. **Baseline first.** У каждой структуры есть наивная реализация или
   встроенный аналог PHP, с которым мы сравниваемся.
2. **Счётчики важнее секундомера.** Для проверки сложности считаем сравнения,
   пробы, коллизии, чтения узлов. Время — вторичная метрика.
3. **Воспроизводимость.** Seed, версия PHP, конфиг opcache/JIT, commit и команда
   сохраняются вместе с результатом.
4. **Реальные и состязательные данные.** Random скучен: нужны Zipf,
   sorted/reverse, дубликаты, коллизии и «плохие» для алгоритма случаи.
5. **Изоляция замеров.** Память и время меряются в отдельном процессе; xdebug
   выключен; прогрев и повторы обязательны.
6. **Документация — часть Definition of Done.** Нет отчёта с выводами — тема не
   закрыта.
7. **Код — источник для книги.** Каждая глава ссылается на конкретные файлы и
   строки, а не на пересказ.
8. **Минимум зависимостей.** Runtime-зависимостей нет; dev-инструменты — Composer.

### 2.1. Не цели

- Веб-интерфейс, БД, Docker Swarm, Kubernetes, микросервисы.
- Production-ready библиотека с SemVer и поддержкой.
- Задачи в стиле LeetCode, не связанные с темами лаборатории.
- Отдельный репозиторий на каждую главу (см. решение выше).

---

## 3. Стек и стандарты экосистемы

| Аспект | Стандарт | Примечание |
|---|---|---|
| PHP | `^8.5`, CLI, 64-bit | `php:8.5-cli` в Docker |
| Composer | `researcher86/algorithms-lab`, PSR-4 `AlgorithmsLab\` → `src/`, `AlgorithmsLab\Tests\` → `tests/` | Как `researcher86/php-memory-lab` |
| Runtime | без зависимостей; `ext-pcntl`, `ext-posix` | Для дочерних процессов, таймаутов и memory-limit тестов |
| Тесты | PHPUnit `^11.5` | `phpunit.xml`, cacheDirectory |
| Статанализ | PHPStan `^2` level 8 | paths: `src`, `bin`, `tests`, `experiments`, `benchmarks` |
| Стиль | PHP-CS-Fixer `^3`, общий конфиг соседей | `@PHP84Migration`, `@PSR12`, `declare_strict_types`, `strict_param` |
| Docker | `php:8.5-cli` + `pcntl`, `posix` + инструменты (`procps`, `htop`, `strace`, `time`, `linux-perf`) | Xdebug ставится, но по умолчанию выключен |
| Оркестрация | `docker-compose.yml`, service `php`, container `algorithms-lab` | Как у соседей |
| Make | `up/down/build/shell/htop/install/test/analyse/format/format-check/experiment/benchmark` + `-debug` | CI вызывает те же target'ы |
| CI | GitHub Actions: `make build install test analyse format-check`, затем `make down` | Без performance-гейтов |
| Лицензия | MIT в `composer.json`, файла `LICENSE` нет | Как в `php-memory-lab` |
| Результаты | `var/results/`, `var/benchmarks/`, `var/datasets/` — gitignored | Маленькие фикстуры — в `datasets/` |
| Релиз | тег `v1.0.0` после книги/интеграции | На него ссылаются главы |
| Язык | код/docs/README — EN; книга — RU | Комментарии объясняют решения |

**Отклонения от соседей, которые нужно зафиксировать в `docs/DECISIONS.md`:**

- Xdebug по умолчанию `mode=off`, а не `start_with_request=trigger`: лаборатория
  измеряет время, и загруженный Xdebug искажает замеры даже в passive-режиме.
  `make experiment-debug` / `make benchmark-debug` включают его явно.
- PHPStan level 8, не max: как в `php-memory-lab`; max даёт шум на динамических
  структурах и не окупается.
- Один репозиторий на 7 глав: отличается от `php-systems-lab`, где каждая тема —
  отдельный проект, но соответствует структуре книги.

### 3.1. Docker — единственное поддерживаемое окружение

На хосте не требуется PHP, Composer, PHPUnit или расширения. Всё запускается в
контейнере, как в `php-memory-lab` и `php-mini-cache`:

```text
хост
  └── make <target>
        └── docker compose exec php <команда>
              └── php:8.5-cli + pcntl + posix + Composer
```

Правила:

1. Любая команда проекта — через `make`: `build`, `install`, `test`, `analyse`,
   `format`, `format-check`, `shell`, `experiment`, `benchmark`.
2. `make` не вызывает PHP на хосте. Если Docker не запущен, команда падает с
   ошибкой Docker, а не с «у меня другая версия PHP».
3. CI вызывает те же `make`-target'ы в том же образе. Локально и в CI — одно
   окружение.
4. Версия PHP закреплена образом `php:8.5-cli`; версия Composer — образом
   `composer:2`.
5. Xdebug установлен, но по умолчанию `xdebug.mode=off`: лаборатория измеряет
   время, и загруженный Xdebug искажает числа. Отладка — только через
   `make experiment-debug` / `make benchmark-debug` с `XDEBUG_MODE=debug`.
6. `var/` смонтирован вместе с проектом, поэтому результаты и датасеты остаются
   на хосте, но не попадают в git.
7. `make down` останавливает контейнер; CI делает это в `always()`.

**Dockerfile (скелет):**

```dockerfile
FROM php:8.5-cli

ARG XDEBUG_VERSION=3.5.3

RUN apt-get update && apt-get install -y \
        $PHPIZE_DEPS git unzip procps htop strace time \
    && git clone --depth 1 --branch ${XDEBUG_VERSION} \
        https://github.com/xdebug/xdebug.git /tmp/xdebug \
    && cd /tmp/xdebug \
    && phpize && ./configure --enable-xdebug \
    && make -j"$(nproc)" && make install \
    && docker-php-ext-enable xdebug \
    && docker-php-ext-install pcntl posix \
    && rm -rf /tmp/xdebug \
    && apt-get purge -y --auto-remove $PHPIZE_DEPS \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV PHP_IDE_CONFIG serverName=algorithms-lab

RUN { \
        echo 'zend_extension=xdebug'; \
        echo 'xdebug.mode=off'; \
        echo 'xdebug.start_with_request=trigger'; \
        echo 'xdebug.client_host=host.docker.internal'; \
        echo 'xdebug.client_port=9003'; \
        echo 'xdebug.discover_client_host=1'; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

RUN { \
        echo 'memory_limit=512M'; \
        echo 'opcache.enable_cli=0'; \
        echo 'date.timezone=UTC'; \
    } > /usr/local/etc/php/conf.d/zz-algorithms-lab.ini

WORKDIR /app
```

**Почему Xdebug из исходников, а не `pecl install`:** PECL отдавал 504, а
стабильный Xdebug 3.5.3 поддерживает PHP 8.5. Версия закреплена `ARG`, сборка
воспроизводима; build-зависимости ставятся на время сборки и удаляются из
финального слоя.

**docker-compose.yml (скелет):**

```yaml
services:
  php:
    build: .
    container_name: algorithms-lab
    working_dir: /app
    volumes:
      - .:/app
    stdin_open: true
    tty: true
```

**Makefile (контракт):**

```make
TTY := $(if $(CI),-T,)

up:
	docker compose up -d

install: up
	docker compose exec $(TTY) php composer install

test: up
	docker compose exec $(TTY) php composer test

analyse: up
	docker compose exec $(TTY) php composer analyse

format-check: up
	docker compose exec $(TTY) php composer format:check

down:
	docker compose down
```

Полный Makefile добавляет `build`, `shell`, `htop`, `format`, `experiment`,
`benchmark` и `-debug`-варианты.

**Ограничение:** Docker на macOS — виртуализированная Linux-машина. Числа
внутри неё шумнее, чем на Linux-хосте. Это не отменяет Docker как среду
разработки: относительные сравнения и счётчики остаются валидными, а эталонные
числа для книги снимаются в одном зафиксированном Linux-окружении (CI или
отдельный хост) и всегда публикуются с environment block.

---

## 4. Структура репозитория

```text
algorithms-lab/
├── bin/
│   ├── experiment              # запуск одного опыта по имени
│   └── benchmark               # запуск suite'а замеров
├── src/
│   ├── Foundation/             # BinaryHeap, BitArray, Hashers, Random, Comparator, UnionFind
│   ├── Instrumentation/        # Counters, Stopwatch, MemoryMeter
│   ├── Experiment/             # Experiment, Registry, Runner, Options, Output
│   ├── Benchmark/              # Benchmark, Runner, Timings, Environment, Formatters, ComplexityFit
│   ├── Cli/                    # Arguments, usage
│   ├── HashTable/              # Chaining, OpenAddressing, RobinHood (stretch)
│   ├── BloomFilter/            # BloomFilter, CountingBloomFilter (stretch)
│   ├── Cache/                  # LRU, LFU, naive baselines
│   ├── Graph/                  # representations, traversals, Dijkstra, MST, DSU
│   ├── Tree/                   # BTree, BPlusTree, page simulation
│   ├── ConsistentHash/         # HashRing, HRW, modulo baseline
│   ├── Stream/                 # TopK, CountMin, SpaceSaving
│   └── ExternalSort/           # chunked sort, k-way merge
├── experiments/
│   ├── 01-hash-table/
│   ├── 02-bloom-filter/
│   ├── 03-cache/
│   ├── 04-graph/
│   ├── 05-tree/
│   ├── 06-consistent-hash/
│   ├── 07-stream/
│   └── 08-external-sort/
├── benchmarks/
│   ├── hash-table.php
│   ├── bloom.php
│   ├── cache.php
│   ├── graph.php
│   ├── tree.php
│   ├── ring.php
│   ├── stream.php
│   └── external-sort.php
├── datasets/                   # генераторы и маленькие фикстуры
├── tests/                      # зеркалит src/, плюс DocumentationTest
├── docs/
│   ├── PHASES.md               # build journal: Goal / Tasks / DoD / Tests
│   ├── DECISIONS.md            # датированные решения и trade-off
│   ├── BENCHMARKS.md           # методика + измеренные таблицы
│   ├── ARCHITECTURE.md         # карта модулей и harness
│   └── COMPLEXITY.md           # теория vs измеренные показатели
├── var/
│   ├── results/                # JSON/CSV прогонов
│   ├── benchmarks/             # вывод benchmark suite'ов
│   └── datasets/               # сгенерированные большие данные
├── .github/workflows/ci.yml
├── Dockerfile
├── docker-compose.yml
├── Makefile
├── composer.json
├── phpunit.xml
├── phpstan.neon
├── .php-cs-fixer.dist.php
├── .gitignore
├── README.md                   # EN, в стиле соседей
└── PLAN.md                     # временно; после Phase 0 → docs/PHASES.md
```

**Почему `experiments/NN-topic`:** `Registry` в `php-memory-lab` обходит
`experiments/*/*.php`, каждый файл возвращает дескриптор `Experiment` с именем.
Это даёт список без ручного реестра и не ломается при переносе файла.
Наши имена: `hash:load-factor`, `bloom:fpr`, `cache:zipf`, `graph:dijkstra`,
`tree:height`, `ring:remap`, `stream:top-k`, `sort:memory-limit`.

---

## 5. Карта «глава книги ↔ модуль ↔ эксперимент ↔ benchmark»

| # | Глава книги | Модуль | Основной код | Эксперименты | Benchmark suite |
|---|---|---|---|---|---|
| 01 | Hash Table и Bloom Filter | `HashTable`, `BloomFilter` | `ChainingHashTable`, `OpenAddressingHashTable`, `BloomFilter` | `hash:load-factor`, `hash:collisions`, `hash:memory`, `bloom:fpr` | `hash-table`, `bloom` |
| 02 | LRU / LFU Cache | `Cache` | `LRUCache`, `LFUCache`, `NaiveLRU` | `cache:zipf`, `cache:scan`, `cache:loop`, `cache:lfu-shift` | `cache` |
| 03 | Graph Algorithms | `Graph` | `AdjacencyList`, `CsrGraph`, `Bfs`, `Dfs`, `Dijkstra`, `Kruskal`, `UnionFind` | `graph:representations`, `graph:dijkstra`, `graph:topo`, `graph:dsu` | `graph` |
| 04 | B-Tree и поиск | `Tree` | `BTree`, `BPlusTree`, `PageSimulator` | `tree:height`, `tree:range`, `tree:page-reads`, `tree:insert-patterns` | `tree` |
| 05 | Consistent Hashing | `ConsistentHash` | `HashRing`, `RendezvousHash`, `ModuloHash` | `ring:remap`, `ring:balance`, `ring:replicas` | `ring` |
| 06 | Top-K и потоковая обработка | `Stream` | `TopKHeap`, `QuickSelect`, `CountMinSketch`, `SpaceSaving` | `stream:top-k`, `stream:sketch`, `stream:unique`, `stream:drift` | `stream` |
| 07 | External Sorting | `ExternalSort` | `ExternalSorter`, `ChunkReader`, `KWayMerge` | `sort:memory-limit`, `sort:fan-in`, `sort:patterns`, `sort:io` | `external-sort` |

Каждая строка заканчивается главой книги, тестом, таблицей в
`docs/BENCHMARKS.md` и записью в `docs/DECISIONS.md`, если было нетривиальное
решение.

---

## 6. Phase 0 — каркас в экосистеме

**Цель:** репозиторий, который с первого коммита выглядит и проверяется как
соседние проекты.

### Задачи

- [ ] Создать публичный репозиторий `Researcher86/algorithms-lab`, добавить remote.
- [x] `composer.json`: имя, `"php": "^8.5"`, `ext-pcntl`, `ext-posix`, PSR-4,
      dev-зависимости, scripts `test/analyse/format/format:check`.
- [x] `Dockerfile`: `php:8.5-cli`, расширения, Composer, Xdebug (off by default),
      диагностические инструменты.
- [x] `docker-compose.yml`: service `php`, container `algorithms-lab`, volume `.:/app`.
- [x] `Makefile`: базовые target'ы `up/down/build/shell/htop/install/test/analyse/`
      `format/format-check/check`; `experiment`/`benchmark` и `-debug` — Phase 1.
- [x] `phpunit.xml`, `phpstan.neon` (level 8), `.php-cs-fixer.dist.php` (общий конфиг).
- [x] `.gitignore`: vendor, кэши, `var/results`, `var/benchmarks`, `var/datasets`,
      `.idea`, `.draft`, `.DS_Store`.
- [x] `.github/workflows/ci.yml`: `make build install test analyse format-check`,
      `make down` при `always()`.
- [x] `README.md` в стиле соседей: 30-second demo, What it does, Chapters,
      Documentation, Project structure, How it relates. Полные Roadmap и
      «What this is not» — после первых глав.
- [x] `docs/PHASES.md`: Final Architecture, Core Principles, Phase 0+.
- [x] `docs/DECISIONS.md`: первые записи (PHP 8.5, Docker, namespace, Xdebug,
      один репозиторий, книга отдельно).
- [x] `docs/BENCHMARKS.md`: методика и обещание, что каждая цифра воспроизводима.
- [x] `tests/DocumentationTest.php`: все docs связаны из README, относительные
      ссылки резолвятся, пути в backticks существуют. Проверка команд
      experiment/benchmark — в Phase 1, когда эти команды появятся.
- [ ] Перенести этот PLAN.md в `docs/PHASES.md` (на английском) и удалить PLAN.md.
- [ ] Первый коммит: `Initialize algorithms-lab project`.

### Definition of Done

```bash
make build
make install
make test
make analyse
make format-check
```

все зелёные в контейнере; CI на GitHub повторяет то же самое; README и docs
проходят `DocumentationTest`; `PLAN.md` больше не лежит в корне.

---

## 7. Phase 1 — Foundation, experiment и benchmark harness

**Цель:** один способ запускать опыт и один способ мерить, до первой темы.

### 7.1. Foundation

- `BinaryHeap` — min/max, comparator, lazy deletion.
- `BitArray` — битовый массив, `get/set/popcount`.
- `Hashers` — FNV-1a 64, Murmur3, xxh128, crc32; маска vs modulo.
- `DeterministicRandom` — seeded PRNG (xorshift/PCG).
- `Comparator` — обёртки со счётчиком сравнений.
- `UnionFind` — path compression + union by rank.

### 7.2. Instrumentation

- `Counters`: `comparisons`, `probes`, `hashCalls`, `collisions`, `nodeReads`,
  `swaps`, `rehashes`.
- Публичные `int`-поля + null-object; в time-бенчмарках выключены.
- `Stopwatch` на `hrtime(true)`.
- `MemoryMeter`: `memory_get_usage(false/true)`, peak, baseline, `gc_collect_cycles`.
- `Measure` — фасад для экспериментов.

### 7.3. Experiment

Повторить модель `php-memory-lab`:

- `Experiment` — readonly descriptor: `name`, `description`, `supports`, `Closure`.
- `Registry` — обходит `experiments/*/*.php`, требует вернуть `Experiment`,
  запрещает дубли имён, сортирует.
- `ExperimentRunner` — валидирует опции, создаёт `Output`, вызывает closure.
- `Options` — только объявленные опции; неизвестная опция — ошибка, не «молча
  проигнорировали».
- `Output` — текст в stdout, JSON отдельно; `--output` только с `--format=json`.
- `bin/experiment` — `list`, `<name>`, `--format=text|json`.

### 7.4. Benchmark

- `Benchmark` — readonly descriptor: `name`, `iterations`, `Closure`.
- `BenchmarkRunner` — warmup + repetitions, min/max/mean/median, p95/MAD, ops/sec
  от median, PHP memory delta.
- `Environment` — PHP, OS, CPU, cgroup limits, memory_limit, OPcache, JIT,
  Xdebug mode, allocator, git commit.
- `Formatter` — text / JSON / CSV.
- `ComplexityFit` — OLS на логарифмах: `t ≈ a·n^b`, `R²`, отдельная модель
  `a + b·log n`; используется в экспериментах, не в time-замерах.
- `bin/benchmark <suite> --format=... --output=... --iterations=... --repetitions=... --warmups=...`.

### 7.5. Демо-сценарии

- `experiments/00-demo/smoke.php` — `demo:smoke`, показывает формат вывода.
- `benchmarks/demo.php` — suite `demo`, проверяет harness в CI.
- `docs/BENCHMARKS.md` — как читать median/min/max, почему окружение обязательно.

### Definition of Done

- `make experiment` печатает список; `make experiment ARGS="demo:smoke"` работает.
- `make benchmark ARGS="demo"` печатает таблицу с environment block.
- `--format=json` валиден, `--output` пишет файл.
- `DocumentationTest` проверяет команды из docs.
- `make test analyse format-check` зелёные.

---

## 8. Главы (Phase 2–8)

Общий Definition of Done для каждой главы:

1. Реализация + наивный baseline/oracle.
2. Unit + differential/property тесты; инварианты.
3. 3–5 экспериментов в `experiments/NN-topic/` с именами `topic:name`.
4. Benchmark suite в `benchmarks/`.
5. Таблица результатов в `docs/BENCHMARKS.md` + выводы.
6. Записи в `docs/DECISIONS.md` о нетривиальных trade-off.
7. Материал для книги: список code excerpts (файл + диапазон строк), команды
   экспериментов, реальный вывод, 2–3 отказа/крайних случая.
8. `make test analyse format-check` зелёные; `docs/PHASES.md` отмечен ✅.
9. Один логический коммит (плюс fixup-коммиты ревью).

### 8.1. Phase 2 — глава 01: Hash Table и Bloom Filter

**Код:** `ChainingHashTable`, `OpenAddressingHashTable` (linear/quadratic/double,
tombstones, resize), `RobinHoodHashTable` (stretch), `BloomFilter` с
`optimal(n, p)` и double hashing, `CountingBloomFilter` (stretch).

**Ожидаемая сложность:** put/get/remove O(1) avg, O(n) worst; Bloom add/check
O(k), память O(m).

**Эксперименты:**

- `hash:load-factor` — α = 0.5/0.75/0.9, пробы и сравнения на hit/miss.
- `hash:collisions` — ключи с общими младшими битами; сравнение хеш-функций.
- `hash:memory` — bytes/key vs PHP-массив; теория vs измерение.
- `hash:resize` — амортизированная стоимость rehash.
- `bloom:fpr` — измеренный FPR vs целевой p = 1/5/10%, оптимальное k.
- `bloom:memory` — Bloom vs hash set при одинаковом FPR.

**Benchmark suite `hash-table`:** insert/get/delete на N = 1e3…1e6, chaining vs
open addressing, α sweep. **`bloom`:** add/check, N и p sweep.

**Книга:** почему PHP-массив — это уже hash table; chaining vs open addressing;
цена коллизий; ложноположительные срабатывания и память.

### 8.2. Phase 3 — глава 02: LRU / LFU Cache

**Код:** `LRUCache` (map + двусвязный список), `LFUCache` (frequency buckets +
minFreq, tie-break LRU), `NaiveLRU` (O(n)), `ArrayLRU` (порядок PHP-массива),
`HeapLFU` (O(log n)); `SegmentedLRU`/`TinyLFU` — stretch.

**Эксперименты:**

- `cache:zipf` — hit ratio на Zipf(α = 0.8/1.0/1.2), capacity 100/1k/10k.
- `cache:scan` — последовательный проход вымывает LRU.
- `cache:loop` — цикл длиной capacity+1, худший случай LRU.
- `cache:lfu-shift` — сдвиг популярности: LFU vs LRU.
- `cache:memory` — bytes/entry, накладные расходы узлов.

**Benchmark suite `cache`:** get/put/evict, LRU vs LFU vs naive vs array.

**Книга:** O(1) на указателях; почему LFU без recency гниёт; scan pollution;
что происходит при capacity → 0/1.

### 8.3. Phase 4 — глава 03: Graph Algorithms

**Код:** `AdjacencyList`, `AdjacencyMatrix`, `EdgeList`, `CsrGraph`; BFS, DFS
(итеративный), components, topo sort (Kahn + DFS), cycle detection; Dijkstra
(своя heap с lazy deletion + decrease-key), Bellman-Ford; Kruskal + UnionFind,
Prim; A*, Tarjan SCC, PageRank — stretch; генераторы ER/grid/BA/path/star/complete.

**Эксперименты:**

- `graph:representations` — память list/matrix/CSR при V/E.
- `graph:traversal` — BFS/DFS на sparse vs dense.
- `graph:dijkstra` — своя heap vs `SplPriorityQueue`, lazy vs decrease-key.
- `graph:topo` — Kahn vs DFS на DAG.
- `graph:dsu` — с/без path compression и union by rank.
- `graph:cross-check` — Dijkstra vs Bellman-Ford на случайных графах.

**Benchmark suite `graph`:** BFS/DFS/Dijkstra/topo на разных плотностях.

**Книга:** почему представление важнее алгоритма; стоимость heap; отрицательные
рёбра; disconnected/self-loops/parallel edges.

### 8.4. Phase 5 — глава 04: B-Tree и поиск

**Код:** `BTree` порядка t (search/insert/delete, borrow/merge/split),
`BPlusTree` (range scan, linked leaves, bulk load), `PageSimulator` (4/8 KiB,
счётчик чтений узлов); baselines: sorted array + binary search, PHP-массив.

**Эксперименты:**

- `tree:height` — высота и node reads для N = 1e3…1e6, проверка fanout.
- `tree:insert-patterns` — random vs sorted, bulk load.
- `tree:range` — range 10/100/1000, B+Tree vs sorted array.
- `tree:page-reads` — page simulation: B-Tree vs binary search.
- `tree:memory` — bytes/key.

**Benchmark suite `tree`:** search/insert/delete/range.

**Книга:** почему B-Tree — про блоки, а не про CPU; fanout и высота; почему
binary search по массиву проигрывает на диске; deletion rebalance.

### 8.5. Phase 6 — глава 05: Consistent Hashing

**Код:** `ModuloHash` baseline, `HashRing` (sorted positions, virtual nodes,
binary search, add/remove/getNodes), `RendezvousHash`; `JumpConsistentHash` —
stretch.

**Эксперименты:**

- `ring:remap` — доля переехавших ключей vs 1/(N+1).
- `ring:balance` — max/mean, CV, chi-square при vnodes 1/10/100/500.
- `ring:replicas` — R последовательных узлов, отказ узла.
- `ring:lookup` — throughput при росте узлов/vnodes.

**Benchmark suite `ring`:** lookup, add/remove.

**Книга:** почему modulo ломает кэш; цена vnodes; ring vs HRW; что считать
«равномерностью».

### 8.6. Phase 7 — глава 06: Top-K и потоковая обработка

**Код:** `TopKHeap` O(n log k), `QuickSelect` O(n) avg, `CountMinSketch`,
`SpaceSaving`/Misra-Gries; sliding window — stretch; генераторы uniform/Zipf/
all-unique/drift.

**Эксперименты:**

- `stream:top-k` — recall/precision, rank error, K = 10/100/1000.
- `stream:sketch` — Count-Min vs Space-Saving при равной памяти.
- `stream:unique` — all-unique поток: exact выходит за память, sketch — нет.
- `stream:drift` — смена популярности в середине потока.

**Benchmark suite `stream`:** exact vs sketch, throughput, memory.

**Книга:** bounded memory; ε/δ; почему точный top-K на потоке — это не heap
размера K, а вопрос памяти; concept drift.

### 8.7. Phase 8 — глава 07: External Sorting

**Код:** `ExternalSorter` (chunk → sort → spill → k-way merge через heap),
`ChunkReader` на generators, настраиваемые chunk size/fan-in, компаратор,
стабильность, cleanup temp files.

**Эксперименты:**

- `sort:memory-limit` — 100/500 MB при `memory_limit=128M`, chunk 8/32/128 MB.
- `sort:fan-in` — 8/32/128, время vs открытые файлы.
- `sort:patterns` — random/sorted/reverse/duplicates, число проходов.
- `sort:io` — bytes read/written, MB/s, peak memory.
- `sort:correctness` — checksum vs in-memory sort на уменьшенном наборе.

**Benchmark suite `external-sort`:** те же входы, измерение I/O и времени.

**Книга:** external memory model; почему k-way merge, а не двухпутевой; цена
лишнего прохода; почему `sort()` в памяти — не решение.

---

## 9. Phase 9 — интеграционные сценарии

Нужны, чтобы темы не остались разрозненными задачами, и чтобы связать
лабораторию с соседними проектами.

1. **Cache tier.** Consistent hashing + LRU/LFU: кластер N узлов, добавление и
   отказ узла, hit ratio, переезд ключей. Связь: `php-mini-cache`,
   `php-systems-platform`.
2. **Log pipeline.** Поток access log → Bloom для уникальных → Top-K URL →
   external sort для дневного отчёта. Связь: observability-главы
   `php-systems-lab`.
3. **Mini index.** B+Tree + Bloom: точечные запросы, экономия на ложноположительных,
   сравнение с полным сканом. Связь: `php-mini-database`, `php-mini-search`.

Для MVP достаточно одного сценария; в v1.0 — два-три.

**DoD:** сценарий запускается одной командой, имеет свой benchmark/отчёт,
воспроизводит данные и показывает эффект от каждой структуры.

---

## 10. Тестирование и качество

- **Unit:** границы, пустые структуры, один элемент, дубликаты, удаление.
- **Differential/property:** случайные операции против эталонной модели
  (PHP-массив, sorted array, naive LRU, Bellman-Ford).
- **Invariants:** порядок и баланс B-Tree, ring после add/remove, heap,
  размеры, tombstones.
- **Memory-limit tests:** отдельный процесс с `memory_limit`, проверка external
  sort и bounded-memory sketch.
- **Benchmark smoke:** крошечные размеры в CI, только «запускается и считает»,
  без порогов времени.
- **DocumentationTest:** docs связаны из README, ссылки резолвятся, пути в
  backticks существуют, команды `make experiment/benchmark` из docs существуют.
- **CI:** `make build install test analyse format-check`; performance — вручную.
- **Mutation testing** (Infection) — stretch после стабилизации.
- **Один phase = один логический коммит**, статус ✅ только после зелёного
  контейнера. Ревью-фиксы — отдельными коммитами, как в соседях.

---

## 11. Воспроизводимость и статистика

- Seed + commit + окружение в каждом результате.
- Benchmark: warmup + repetitions, median, min/max, p95/MAD, ops/sec от median.
- Память: отдельный процесс, baseline, `memory_get_usage(false)`, peak.
- Xdebug off; OPcache/JIT фиксируются; `memory_limit` фиксируется.
- Для детерминированных счётчиков достаточно одного прогона, но показываем
  разброс.
- Оценка сложности через log-log fit; вывод «похоже на O(n log n)» подкрепляется
  числом и `R²`.
- **Docker на macOS** добавляет шум виртуализации. Числа для книги снимаются в
  Linux-контейнере/CI; в отчёте всегда есть environment block и оговорка, что
  это числа этой машины, а не универсальные.
- `results/` → `var/results/<date>-<commit>/meta.json`, `raw.csv`, `summary.md`.
- Данные: seeded генераторы; большие файлы в `var/datasets` (gitignored),
  checksums; маленькие фикстуры (< 5 MB) в `datasets/`; лицензии фиксируются.

---

## 12. Документация, книга и релиз

### 12.1. Разделение

| Где | Что | Язык |
|---|---|---|
| `algorithms-lab/README.md` | Что это, как запустить, карта глав, docs, структура, roadmap | EN |
| `algorithms-lab/docs/PHASES.md` | Build journal: Goal / Tasks / DoD / Tests по фазам | EN |
| `algorithms-lab/docs/DECISIONS.md` | Датированные решения и trade-off | EN |
| `algorithms-lab/docs/BENCHMARKS.md` | Методика, окружение, таблицы результатов | EN |
| `algorithms-lab/docs/ARCHITECTURE.md` | Карта модулей и harness | EN |
| `algorithms-lab/docs/COMPLEXITY.md` | Теория vs измеренные показатели | EN |
| `book/algorithms-lab/NN-*.md` | Повествование, код, эксперимент, отказы, упражнения | RU |
| `book/algorithms-lab/README.md` | Оглавление, маршруты чтения, статус | RU |

### 12.2. Шаблон главы книги

1. **Проблема** — зачем механизм и что ломается без него.
2. **Устройство** — как работает, со схемой.
3. **Код** — выдержки из настоящих файлов, каждая с GitHub-ссылкой на
   `v1.0.0`/SHA и диапазоном строк.
4. **Эксперимент** — `make experiment ARGS="..."`, реальный вывод.
5. **Отказы** — что ломается и как проект это ловит.
6. **Как это проверено** — конкретные тесты и почему им можно верить.
7. **Цифры** — таблица из `make benchmark`, с environment block.
8. **Упражнения** — 3–5 задач на понимание и расчёт.
9. **Что дальше** — связь со следующей главой и соседними лабораториями.

### 12.3. Контракт code blocks

- Код в главе копируется **дословно** из файла на закреплённом SHA/теге.
- Сразу после блока — ссылка вида
  `https://github.com/Researcher86/algorithms-lab/blob/v1.0.0/src/...#L10-L20`.
- `check_book.py` сравнивает блок с источником; расхождение — ошибка CI.
- Во время написания главы используется SHA запушенного коммита; после релиза
  заменяется на `v1.0.0` (или остаётся SHA — оба варианта допустимы, ссылка на
  ветку `master` — нет).

### 12.4. Что нужно поправить в книжном репозитории

`book/php-systems-lab/tools/check_book.py` сейчас:

- ищет главы `[0-9][0-9]-*.md` в текущей папке;
- ждёт ровно 26 глав (1..26);
- проверяет code blocks и ссылки.

Для algorithms-lab нужно:

- [ ] Добавить `--expected-chapters` (default 26 для обратной совместимости).
- [ ] В `book/.github/workflows/book.yml` добавить шаг:
      ```yaml
      - name: Verify Algorithms Lab chapters
        working-directory: algorithms-lab
        run: python3 ../php-systems-lab/tools/check_book.py --expected-chapters=7
      ```
- [ ] Локальный запуск:
      ```bash
      cd book/algorithms-lab
      python3 ../php-systems-lab/tools/check_book.py \
        --source-root ../.. --expected-chapters=7
      ```
      (`read_local(root, repo, path)` ищет `root/repo/path`, поэтому
      `--source-root ../..` указывает на `engineering-lab`, а repo —
      `algorithms-lab`.)
- [ ] Обновить `book/algorithms-lab/README.md`: статус, оглавление, маршруты
      чтения, карта зависимостей, «как проверено».
- [ ] Обновить `book/README.md`: статус лаборатории.
- [ ] (Опционально) перенести checker в `book/tools/check_book.py`, чтобы он не
      жил внутри php-systems-lab; это отдельный рефакторинг, не блокирует.

### 12.5. Релизный чек-лист

- [ ] Все 7 глав реализованы, experiments/benchmarks воспроизводятся.
- [ ] `make test analyse format-check` зелёные в контейнере.
- [ ] `docs/PHASES.md` — все фазы ✅; `docs/BENCHMARKS.md` — все таблицы.
- [ ] README: карта глав, ссылки на book, docs, structure.
- [ ] Публичный репозиторий, remote, CI зелёный.
- [ ] Тег `v1.0.0` запушен.
- [ ] Все ссылки в книге переведены на `v1.0.0`.
- [ ] `check_book.py --expected-chapters=7` зелёный.
- [ ] `book/algorithms-lab/README.md` больше не «черновик».
- [ ] Анонс/итоговая статья (опционально).

---

## 13. Дорожная карта

Оценки — при частичной занятости (~10–12 ч/нед), без написания книги.

| Phase | Содержание | Оценка | Артефакт |
|---|---|---|---|
| 0 | Каркас в экосистеме, Docker, CI, docs, DocumentationTest | 1 нед. | `make check` зелёный, первый коммит |
| 1 | Foundation + experiment/benchmark harness | 2 нед. | demo experiment + demo benchmark |
| 2 | Глава 01: Hash + Bloom | 2 нед. | suite `hash-table`, `bloom` |
| 3 | Глава 02: LRU/LFU | 2 нед. | suite `cache` |
| 4 | Глава 03: Graphs | 3 нед. | suite `graph` |
| 5 | Глава 04: B-Tree | 2.5 нед. | suite `tree` |
| 6 | Глава 05: Consistent Hashing | 1.5 нед. | suite `ring` |
| 7 | Глава 06: Top-K / streams | 2 нед. | suite `stream` |
| 8 | Глава 07: External Sorting | 2 нед. | suite `external-sort` |
| 9 | Интеграционные сценарии + benchmark pass | 2 нед. | 2–3 сценария, отчёт |
| 10 | Релиз v1.0.0 + handoff книги (repo-часть) | 1 нед. | тег, README, checker |
| 11 | Написание 7 глав в `book/algorithms-lab` | 4–6 нед. | главы + зелёный checker |

**Итого по репозиторию:** ~19–20 недель.
**С книгой:** ~24–26 недель.

**MVP (v0.1):** Phase 0–8, по одной качественной реализации на главу, 1–2
эксперимента, один benchmark suite, docs и `DocumentationTest`; без интеграции,
реальных датасетов и stretch. ~15–17 недель.

**v1.0:** MVP + Phase 9–10 + книга + реальные данные + варианты реализаций.

Порядок глав фиксирован книгой. Если хочется быстрее получить результат,
можно сначала сделать тонкие срезы всех семи тем (MVP), а потом углублять —
но главы всё равно пишутся по одной и в порядке книги.

---

## 14. Риски и меры

| Риск | Последствие | Мера |
|---|---|---|
| Код в книге разъезжается с репозиторием | Книга врёт | Pinned SHA/тег + `check_book.py` в CI |
| Docker на macOS искажает benchmark | Неверные выводы | Environment block, относительные сравнения, числа для книги — Linux/CI |
| PHP-overhead съедает разницу алгоритмов | Ложные выводы | Счётчики операций как основная метрика; минимум объектов на элемент |
| Шумные time-бенчмарки | «Ускорения» из воздуха | Warmup, repetitions, median/p95, CV, фиксация окружения |
| Грубое измерение памяти в PHP | Ошибки bytes/key | Отдельный процесс, baseline, `memory_get_usage(false)`, оговорки |
| Разрастание scope (7 глав + книга) | Проект не заканчивается | MVP, глава за главой, stretch-список |
| Docs гниют | README врёт | `DocumentationTest` в CI |
| Ссылки в книге на несуществующий тег | CI книги красный | Тег до перевода ссылок; SHA на время написания |
| Один репозиторий vs 7 | Ссылки в книге | Решение зафиксировано: один; менять только до начала книги |
| Огромные датасеты | Тяжёлый репозиторий | Генераторы, `var/datasets`, gitignore, checksums |
| Xdebug в контейнере | Искажает замеры | `mode=off` по умолчанию, debug-targets явно |
| Flaky performance в CI | Красные сборки | В CI только smoke, без порогов |
| Потеря мотивации на graphs/B-tree | Заброшенный проект | Чередовать тяжёлые и лёгкие главы, интеграция как «награда» |

---

## 15. MVP и stretch

### MVP

- Phase 0–8.
- Одна реализация на тему + baseline.
- 1–2 эксперимента на тему, один benchmark suite.
- Differential/property тесты.
- README + docs (PHASES, DECISIONS, BENCHMARKS, ARCHITECTURE, COMPLEXITY).
- `DocumentationTest`.
- Один интеграционный сценарий (Phase 9 — минимум).

### Stretch

- Robin Hood hashing, counting/scalable Bloom.
- TinyLFU / segmented LRU.
- Jump consistent hash.
- B+Tree bulk load, skip list.
- A*, Tarjan SCC, PageRank, CSR-оптимизации.
- Sliding-window top-K, t-digest.
- Radix sort для external sort, сжатие чанков.
- Графики и статический HTML-отчёт.
- Сравнение с реализацией на другом языке.
- Mutation testing, fuzzing.
- Полный набор реальных датасетов.

---

## 16. Первые шаги

1. Подтвердить: один репозиторий, PHP 8.5, публичный
   `Researcher86/algorithms-lab`, книга — отдельно.
2. Создать remote и первый коммит с Phase 0.
3. Поднять Docker, Composer, Makefile, CI, PHPStan, CS-Fixer, PHPUnit.
4. Написать `docs/00-methodology` → `docs/BENCHMARKS.md` (методика до кода).
5. Реализовать `DocumentationTest`.
6. Реализовать Foundation + harness (Phase 1).
7. Сделать пилот `hash:load-factor` + suite `hash-table`.
8. Снять первые числа, заполнить `docs/BENCHMARKS.md`, проверить, что формат
   удобен для книги.
9. Сверить README и docs с соседними проектами (тон, структура, команды).
10. Дальше — по главам, закрывая каждую по общему DoD.

---

## 17. Открытые вопросы

1. **Один репозиторий или семь?** Рекомендация — один: книга имеет один
   `algorithms-lab/README.md`, темы делят harness. Если семь, нужно менять
   структуру ссылок в книге.
2. **Публичный репозиторий:** создавать `Researcher86/algorithms-lab` сразу?
   Без него `check_book.py` в CI книги не сможет проверить главы.
3. **Книга в scope:** писать 7 глав сразу после кода или сначала выпустить
   v1.0.0 кода, а книгу — отдельным треком?
4. **Реальные данные:** тянем Gutenberg/SNAP/access logs или обходимся
   генераторами? Влияет на Docker-образ (mbstring, размеры) и на главы.
5. **Машина для эталонных чисел:** Linux CI, отдельный Linux-хост или macOS
   Docker? Числа в книге должны быть сняты в одном окружении.
6. **Язык docs репозитория:** подтверждаем английский (как у соседей), а книгу
   оставляем на русском?
7. **Сроки:** ориентируемся на MVP ~15–17 недель или на полный v1.0 ~20 недель
   без книги?
