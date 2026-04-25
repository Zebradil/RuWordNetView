package views

import (
	"bytes"
	"net/http/httptest"
	"testing"

	"github.com/zebradil/ruwordnetview/internal/i18n"
)

func TestTemplatesParse(t *testing.T) {
	tr, err := i18n.Load("../../app/locales")
	if err != nil {
		t.Fatalf("load i18n: %v", err)
	}
	r, err := New("../../views", tr)
	if err != nil {
		t.Fatalf("load templates: %v", err)
	}

	pages := []struct {
		name string
		data PageData
	}{
		{
			name: "homepage",
			data: PageData{
				Locale:      "ru",
				RouteName:   "homepage",
				RouteParams: map[string]interface{}{"_locale": "ru"},
				LocaleText:  "<p>test</p>",
			},
		},
		{
			name: "404",
			data: PageData{
				Locale:      "ru",
				RouteName:   "homepage",
				RouteParams: map[string]interface{}{"_locale": "ru"},
			},
		},
		{
			name: "error",
			data: PageData{
				Locale:      "ru",
				RouteName:   "",
				RouteParams: map[string]interface{}{},
			},
		},
		{
			name: "lexeme_summary",
			data: PageData{
				Locale:       "ru",
				RouteName:    "search",
				RouteParams:  map[string]interface{}{"_locale": "ru"},
				SearchString: "стол",
				LexemeView:   nil,
			},
		},
	}

	for _, tc := range pages {
		t.Run(tc.name, func(t *testing.T) {
			w := httptest.NewRecorder()
			if err := r.Render(w, 200, tc.name, tc.data); err != nil {
				t.Errorf("render %s: %v", tc.name, err)
			}
			var buf bytes.Buffer
			buf.ReadFrom(w.Body)
			if buf.Len() == 0 {
				t.Errorf("render %s: empty output", tc.name)
			}
		})
	}
}
