package views

import (
	"bytes"
	"fmt"
	"html/template"
	"net/http"
	"path/filepath"

	"github.com/zebradil/ruwordnetview/internal/i18n"
)

// PageData is the root data structure passed to every template.
type PageData struct {
	Locale       string
	RouteName    string
	RouteParams  map[string]interface{} // keys: _locale, name, meaning, searchString
	SearchString string
	LexemeView   interface{} // *db.LexemeView; fields accessed via reflection in templates
	LocaleText   template.HTML
	ErrorMsg     string
	StatusCode   int
}

type Renderer struct {
	pages map[string]*template.Template
}

var pageFiles = []string{
	"homepage",
	"lexeme_summary",
	"404",
	"error",
}

func New(viewsDir string, tr *i18n.Translator) (*Renderer, error) {
	fm := buildFuncMap(tr)

	layoutFile := filepath.Join(viewsDir, "layout.gohtml")
	macroFile := filepath.Join(viewsDir, "macros.gohtml")

	// Parse layout + macros as the base. Both files consist only of {{define}} blocks,
	// so named templates "layout" and "senseList" are registered in the set.
	base, err := template.New("base").Funcs(fm).ParseFiles(layoutFile, macroFile)
	if err != nil {
		return nil, fmt.Errorf("parse base templates: %w", err)
	}

	r := &Renderer{pages: make(map[string]*template.Template)}
	for _, page := range pageFiles {
		clone, err := base.Clone()
		if err != nil {
			return nil, fmt.Errorf("clone base for %s: %w", page, err)
		}
		pageFile := filepath.Join(viewsDir, page+".gohtml")
		// ParseFiles registers the {{define "content"}} block from the page file,
		// overriding the default empty block from layout.gohtml.
		_, err = clone.ParseFiles(pageFile)
		if err != nil {
			return nil, fmt.Errorf("parse template %s: %w", page, err)
		}
		r.pages[page] = clone
	}

	return r, nil
}

func (r *Renderer) Render(w http.ResponseWriter, status int, page string, data interface{}) error {
	t, ok := r.pages[page]
	if !ok {
		return fmt.Errorf("template %q not found", page)
	}
	var buf bytes.Buffer
	if err := t.ExecuteTemplate(&buf, "layout", data); err != nil {
		return fmt.Errorf("execute template %s: %w", page, err)
	}
	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.WriteHeader(status)
	_, err := buf.WriteTo(w)
	return err
}

func (r *Renderer) RenderError(w http.ResponseWriter, status int, locale, msg string) {
	data := PageData{
		Locale:      locale,
		RouteName:   "",
		RouteParams: map[string]interface{}{},
		ErrorMsg:    msg,
		StatusCode:  status,
	}
	if err := r.Render(w, status, "error", data); err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
	}
}
