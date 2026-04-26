package app

import (
	"fmt"
	"log/slog"
	"net/http"
	"os"
	"runtime/debug"

	"github.com/go-chi/chi/v5"
	"github.com/go-chi/chi/v5/middleware"
	"github.com/jackc/pgx/v5/pgxpool"
	"github.com/zebradil/ruwordnetview/internal/db"
	"github.com/zebradil/ruwordnetview/internal/handlers"
	"github.com/zebradil/ruwordnetview/internal/views"
)

type App struct {
	pool        *pgxpool.Pool
	renderer    *views.Renderer
	logger      *slog.Logger
	debug       bool
	localeTexts map[string]string
}

func New(pool *pgxpool.Pool, renderer *views.Renderer, logger *slog.Logger, debug bool, localeTexts map[string]string) *App {
	return &App{pool: pool, renderer: renderer, logger: logger, debug: debug, localeTexts: localeTexts}
}

func (a *App) Router() http.Handler {
	repo := db.NewRepo(a.pool)
	site := handlers.NewSite(repo, a.renderer, a.logger, a.localeTexts)

	r := chi.NewRouter()
	r.Use(middleware.RealIP)
	r.Use(middleware.RequestID)
	r.Use(a.recoveryMiddleware)

	// Static assets — served first, no locale redirect.
	staticDir := os.Getenv("STATIC_DIR")
	if staticDir == "" {
		staticDir = "web/static"
	}
	r.Handle("/static/*", http.StripPrefix("/static/", http.FileServer(http.Dir(staticDir))))

	// Web root assets (favicons, manifest, etc.)
	webDir := "web"
	for _, f := range []string{
		"favicon.ico", "favicon-16x16.png", "favicon-32x32.png",
		"apple-touch-icon.png", "manifest.json", "safari-pinned-tab.svg",
		"android-chrome-192x192.png", "android-chrome-512x512.png",
		"mstile-150x150.png", "browserconfig.xml",
	} {
		ff := f // capture
		r.Get("/"+ff, func(w http.ResponseWriter, req *http.Request) {
			http.ServeFile(w, req, webDir+"/"+ff)
		})
	}

	// Root redirect.
	r.Get("/", func(w http.ResponseWriter, req *http.Request) {
		http.Redirect(w, req, "/ru/", http.StatusFound)
	})

	// Locale-prefixed routes.
	r.Route("/{locale:ru|en}", func(r chi.Router) {
		r.Get("/", site.Homepage)
		r.Get("/search", site.Search)
		r.Get("/search/", site.Search)
		r.Get("/search/{searchString:.*}", site.Search)
		r.Get("/sense/{senseSpec:.*}", site.Sense)
		r.Get("/{whatever:.*}", func(w http.ResponseWriter, req *http.Request) {
			locale := chi.URLParam(req, "locale")
			data := views.PageData{
				Locale:      locale,
				RouteName:   "homepage",
				RouteParams: map[string]interface{}{"_locale": locale},
			}
			a.renderer.Render(w, http.StatusNotFound, "404", data) //nolint:errcheck
		})
	})

	// Catch-all: redirect non-locale paths to /ru/{path}.
	r.Get("/{whatever:.*}", func(w http.ResponseWriter, req *http.Request) {
		whatever := chi.URLParam(req, "whatever")
		http.Redirect(w, req, "/ru/"+whatever, http.StatusFound)
	})

	return r
}

func (a *App) recoveryMiddleware(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if rec := recover(); rec != nil {
				a.logger.Error("panic recovered",
					"panic", rec,
					"stack", string(debug.Stack()),
				)
				locale := chi.URLParam(r, "locale")
				if locale == "" {
					locale = "ru"
				}
				msg := ""
				if a.debug {
					msg = fmt.Sprint(rec)
				}
				a.renderer.RenderError(w, http.StatusInternalServerError, locale, msg)
			}
		}()
		next.ServeHTTP(w, r)
	})
}
