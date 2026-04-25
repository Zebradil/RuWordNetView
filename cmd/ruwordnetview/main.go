package main

import (
	"context"
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"path/filepath"
	"strings"

	"github.com/zebradil/ruwordnetview/internal/app"
	"github.com/zebradil/ruwordnetview/internal/db"
	"github.com/zebradil/ruwordnetview/internal/i18n"
	"github.com/zebradil/ruwordnetview/internal/views"
)

func main() {
	ctx := context.Background()
	logger := slog.New(slog.NewJSONHandler(os.Stdout, nil))

	cfg := db.Config{
		Host:     mustEnv("POSTGRES_HOST"),
		Port:     envOrDefault("POSTGRES_PORT", "5432"),
		Database: mustEnv("POSTGRES_DB"),
		User:     mustEnv("POSTGRES_USER"),
		Password: mustEnv("POSTGRES_PASSWORD"),
	}

	debug := os.Getenv("APP_DEBUG") != ""

	var dbLogger *slog.Logger
	if debug {
		dbLogger = slog.New(slog.NewJSONHandler(os.Stdout, &slog.HandlerOptions{Level: slog.LevelDebug}))
	}

	pool, err := db.NewPool(ctx, cfg, dbLogger)
	if err != nil {
		logger.Error("connect to database", "error", err)
		os.Exit(1)
	}
	defer pool.Close()

	localesDir := envOrDefault("LOCALES_DIR", "app/locales")
	tr, err := i18n.Load(localesDir)
	if err != nil {
		logger.Error("load translations", "error", err)
		os.Exit(1)
	}

	viewsDir := envOrDefault("VIEWS_DIR", "views")
	renderer, err := views.New(viewsDir, tr)
	if err != nil {
		logger.Error("load templates", "error", err)
		os.Exit(1)
	}

	localeTexts, err := loadLocaleTexts(viewsDir)
	if err != nil {
		logger.Warn("load locale texts", "error", err)
		localeTexts = map[string]string{}
	}

	addr := envOrDefault("LISTEN_ADDR", ":8000")

	a := app.New(pool, renderer, logger, debug, localeTexts)

	logger.Info("starting server", "addr", addr)
	if err := http.ListenAndServe(addr, a.Router()); err != nil {
		logger.Error("server stopped", "error", err)
		os.Exit(1)
	}
}

// loadLocaleTexts reads the homepage locale-specific HTML files from
// views/parts/translations/homepage_text.{locale}.html
func loadLocaleTexts(viewsDir string) (map[string]string, error) {
	pattern := filepath.Join(viewsDir, "Site", "parts", "translations", "homepage_text.*.html")
	files, err := filepath.Glob(pattern)
	if err != nil {
		return nil, err
	}
	texts := make(map[string]string)
	for _, f := range files {
		base := filepath.Base(f)
		// base = "homepage_text.ru.html"
		parts := strings.Split(base, ".")
		if len(parts) < 3 {
			continue
		}
		locale := parts[1]
		data, err := os.ReadFile(f)
		if err != nil {
			return nil, err
		}
		texts[locale] = string(data)
	}
	return texts, nil
}

func mustEnv(key string) string {
	v := os.Getenv(key)
	if v == "" {
		fmt.Fprintf(os.Stderr, "required env var %s is not set\n", key)
		os.Exit(1)
	}
	return v
}

func envOrDefault(key, def string) string {
	if v := os.Getenv(key); v != "" {
		return v
	}
	return def
}
