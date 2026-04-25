package handlers

import (
	"html/template"
	"log/slog"
	"net/http"
	"strconv"
	"strings"

	"github.com/go-chi/chi/v5"
	"github.com/zebradil/ruwordnetview/internal/db"
	"github.com/zebradil/ruwordnetview/internal/views"
)

type Site struct {
	repo        *db.Repo
	renderer    *views.Renderer
	logger      *slog.Logger
	localeTexts map[string]string // locale → HTML text for homepage
}

func NewSite(repo *db.Repo, renderer *views.Renderer, logger *slog.Logger, localeTexts map[string]string) *Site {
	return &Site{repo: repo, renderer: renderer, logger: logger, localeTexts: localeTexts}
}

func (s *Site) Homepage(w http.ResponseWriter, r *http.Request) {
	locale := chi.URLParam(r, "locale")
	localeText := template.HTML(s.localeTexts[locale])
	if localeText == "" {
		localeText = template.HTML(s.localeTexts["en"])
	}
	data := views.PageData{
		Locale:      locale,
		RouteName:   "homepage",
		RouteParams: map[string]interface{}{"_locale": locale},
		LocaleText:  localeText,
	}
	if err := s.renderer.Render(w, http.StatusOK, "homepage", data); err != nil {
		s.logger.Error("render homepage", "error", err)
	}
}

func (s *Site) Search(w http.ResponseWriter, r *http.Request) {
	locale := chi.URLParam(r, "locale")

	searchString := chi.URLParam(r, "searchString")
	if searchString == "" {
		searchString = r.URL.Query().Get("searchString")
	}
	searchString = strings.TrimSpace(searchString)

	base := views.PageData{
		Locale:       locale,
		RouteName:    "search",
		RouteParams:  map[string]interface{}{"_locale": locale, "searchString": searchString},
		SearchString: searchString,
	}

	if searchString == "" {
		if err := s.renderer.Render(w, http.StatusOK, "lexeme_summary", base); err != nil {
			s.logger.Error("render search", "error", err)
		}
		return
	}

	senses, err := s.repo.GetByName(r.Context(), searchString)
	if err != nil {
		s.logger.Error("GetByName", "error", err, "query", searchString)
		s.renderer.RenderError(w, http.StatusInternalServerError, locale, "")
		return
	}

	lv, err := s.repo.BuildLexemeView(r.Context(), senses)
	if err != nil {
		s.logger.Error("BuildLexemeView", "error", err)
		s.renderer.RenderError(w, http.StatusInternalServerError, locale, "")
		return
	}

	base.LexemeView = lv
	if err := s.renderer.Render(w, http.StatusOK, "lexeme_summary", base); err != nil {
		s.logger.Error("render search results", "error", err)
	}
}

func (s *Site) Sense(w http.ResponseWriter, r *http.Request) {
	locale := chi.URLParam(r, "locale")
	senseSpec := chi.URLParam(r, "senseSpec")

	name, meaning := parseSenseSpec(senseSpec)

	base := views.PageData{
		Locale:       locale,
		RouteName:    "sense",
		RouteParams:  map[string]interface{}{"_locale": locale, "name": name, "meaning": strconv.Itoa(meaning)},
		SearchString: name,
	}

	sense, err := s.repo.GetByNameAndMeaning(r.Context(), name, meaning)
	if err != nil {
		s.logger.Error("GetByNameAndMeaning", "error", err)
		s.renderer.RenderError(w, http.StatusInternalServerError, locale, "")
		return
	}

	if sense == nil {
		if err := s.renderer.Render(w, http.StatusOK, "lexeme_summary", base); err != nil {
			s.logger.Error("render sense not found", "error", err)
		}
		return
	}

	lv, err := s.repo.BuildLexemeView(r.Context(), []db.Sense{*sense})
	if err != nil {
		s.logger.Error("BuildLexemeView", "error", err)
		s.renderer.RenderError(w, http.StatusInternalServerError, locale, "")
		return
	}

	base.LexemeView = lv
	if err := s.renderer.Render(w, http.StatusOK, "lexeme_summary", base); err != nil {
		s.logger.Error("render sense", "error", err)
	}
}

func parseSenseSpec(s string) (name string, meaning int) {
	if i := strings.LastIndex(s, "+"); i >= 0 {
		if m, err := strconv.Atoi(s[i+1:]); err == nil {
			return s[:i], m
		}
	}
	return s, 0
}
