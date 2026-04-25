# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Web interface for the RuWordNet Russian thesaurus database. Go 1.23 app using **chi** router, `html/template` for rendering, **pgx/v5** for PostgreSQL access, and stdlib `log/slog`. Localized (ru/en) under `/{locale}/...` — locale is URL-only, no cookies or sessions.

## Commands

All common workflows are driven through the Makefile. Run `make help` for the auto-generated target list.

- `make build-prod` — build the production Docker image (`ruwordnetview:prod`). ~15 MB distroless image.
- `make build-nginx` — build the nginx SSL-termination image (`ruwordnetview:nginx`).
- `make dev` — run the app locally (`go run ./cmd/ruwordnetview`). Export `POSTGRES_*` env vars first.
- `make test` — run Go tests.
- `make lint` — run `go vet` and `gofmt` check.
- `make css` — compile `web/static/css/layout.scss` → `layout.min.css` via `sassc` (host tool).
- `make deploy` — rsync the tree to the `ruwordnet` remote.
- Production stack: `docker compose up` uses `compose.yaml` (nginx proxy + app + postgres:15.17). Requires `.env` populated from `.env.dist`. The `app` service reads DB from `POSTGRES_*` env vars.
- CI: GitHub Actions (`.github/workflows/ci.yml`) runs `go vet`, `gofmt` check, and Docker build on every push/PR.

Go commands need `GOFLAGS=-mod=mod` (or `-mod=mod` flag) because the PHP `vendor/` directory exists during the transition. This is set in `Makefile`. Once PHP files are cleaned up, run `go mod vendor` or remove the flag.

First-time local setup:
1. Export `POSTGRES_HOST`, `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD` (`POSTGRES_PORT` defaults to `5432`).
2. Run `make dev` — the app serves on `:8000`.

Optional env vars: `LISTEN_ADDR` (default `:8000`), `APP_DEBUG` (any non-empty enables panic details in error pages), `VIEWS_DIR` (default `views`), `LOCALES_DIR` (default `app/locales`), `STATIC_DIR` (default `web/static`).

## Architecture

Entrypoint is `cmd/ruwordnetview/main.go`, which reads env, connects to Postgres via pgx pool, loads i18n and templates, then starts `net/http` with chi.

1. **Database** (`internal/db/`) — `db.Config` from `POSTGRES_*` env, `db.NewPool` creates a `pgxpool.Pool`. `db.Repo` has three methods: `GetByName`, `GetByNameAndMeaning`, `BuildLexemeView`. `BuildLexemeView` runs 4 queries in parallel via `errgroup` (synsets, synset senses, sense relations, synset relations) then fires ILI per synset — eliminating the N+1 that plagued the PHP app.
2. **Templates** (`internal/views/`) — `views.New(viewsDir, tr)` loads `layout.gohtml` + `macros.gohtml` as a base, then for each page clones the base and adds the page-specific `{{define "content"}}` file. Executed via `t.ExecuteTemplate(w, "layout", data)`. All data is passed as `views.PageData`.
3. **Handlers** (`internal/handlers/site.go`) — `HomepageHandler`, `SearchHandler`, `SenseHandler`. Each reads locale from the URL path param, builds data, and calls `renderer.Render`.
4. **i18n** (`internal/i18n/`) — loads `app/locales/{ru,en}.yml` (flat key→value YAML). `Translator.T(locale, key, params)` handles `%placeholder%` substitution. Fallback is `en`.
5. **Router** (`internal/app/app.go`) — chi router. Static files at `/static/*` and web root files served directly. Root `/` → 302 to `/ru/`. Locale routes under `/{locale:ru|en}/...`. Non-locale catch-all → 302 to `/ru/{path}`.

### Route table

```
GET /                               → 302 /ru/
GET /{locale}/                      → homepage
GET /{locale}/search                → search (empty)
GET /{locale}/search/{searchString} → search
GET /{locale}/sense/{senseSpec}     → sense (senseSpec = "NAME" or "NAME+MEANING")
GET /{locale}/{other}               → 404
GET /{non-locale-path}              → 302 /ru/{non-locale-path}
```

### Template structure

```
views/
  layout.gohtml          {{define "layout"}} — header, nav, footer, {{block "content" .}}
  macros.gohtml          {{define "senseList"}} — shared sense list macro
  homepage.gohtml        {{define "content"}} — extends layout via block
  lexeme_summary.gohtml  {{define "content"}} — sense/search results page
  404.gohtml             {{define "content"}}
  error.gohtml           {{define "content"}}
app/locales/
  ru.yml                 Russian translations (flat key: value)
  en.yml                 English translations
views/Site/parts/translations/
  homepage_text.ru.html  Locale-specific homepage body (raw HTML, trusted)
  homepage_text.en.html
web/static/              CSS, images — served directly by Go's net/http
```

### Template FuncMap

`trans(locale, key)`, `transp(locale, key, params)`, `path(locale, routeName, params)`, `capitalize`, `upper`, `lower`, `join`, `itoa`, `add`, `last`, `dict`, `synsetTail(locale, synsetName)`, `safeHTML`, `printf`.

### Data model

```
LexemeView
  └─ []SenseDetail
       ├─ Sense (ID, SynsetID, Name, Lemma, SyntType, Meaning)
       ├─ Synset (ID, Name, Definition, PartOfSpeech)
       ├─ SynsetSenses []Sense       — all senses in same synset
       ├─ SenseRelations []SenseRelGroup{Name, []Sense}
       ├─ SynsetRelations []RelationGroup{Name, []SynsetTarget{Synset, []Sense}}
       └─ ILIRelations []ILIRelation{ILI, ID, Name, Definition, LemmaNames}
```

## Conventions and gotchas

- Go 1.23. No CGO. `GOFLAGS=-mod=mod` is required while `vendor/` (PHP composer) exists.
- Locale is URL-only. No cookies, no sessions. Root `/` always redirects to `/ru/`.
- `path(locale, routeName, params)` — for locale switching, the current page passes `RouteParams` (a `map[string]interface{}`) to the path function directly. The path function accepts both `map[string]interface{}` and `map[string]string`.
- Sense names in the DB are stored UPPERCASE. `GetByName` and `GetByNameAndMeaning` call `strings.ToUpper` before querying.
- The `+` in sense URLs (`/ru/sense/СТОЛ+1`) is a literal plus sign (path segment, not query string). `parseSenseSpec` splits on the last `+` followed by digits.
- ILI relations query uses `$2 LIKE '%' || substring(ili.wn_id, '.$') || '%'` which checks if the last char of the WN id appears in the partOfSpeech string (`"as"` for Adj, `"n"`/`"v"` for N/V).
- Nginx (`docker/nginx/`) is kept as SSL terminator + proxy cache. `app.conf` uses `proxy_pass http://app:8000`. Cache validity: 200/302 for 60 days.
- Dependencies are auto-updated by Renovate (`renovate.json`).
