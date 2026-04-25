package views

import (
	"fmt"
	"html/template"
	"net/url"
	"reflect"
	"strconv"
	"strings"
	"unicode"

	"github.com/zebradil/ruwordnetview/internal/i18n"
)

func buildFuncMap(tr *i18n.Translator) template.FuncMap {
	return template.FuncMap{
		"trans": func(locale, key string) string {
			return tr.T(locale, key, nil)
		},
		"transp": func(locale, key string, params map[string]interface{}) string {
			p := make(map[string]string, len(params))
			for k, v := range params {
				p[k] = fmt.Sprint(v)
			}
			return tr.T(locale, key, p)
		},
		"path": pathFunc,
		"otherLocale": func(locale string) string {
			if locale == "ru" {
				return "en"
			}
			return "ru"
		},
		"capitalize": capitalize,
		"upper":      strings.ToUpper,
		"lower":      strings.ToLower,
		"join":       strings.Join,
		"itoa":       strconv.Itoa,
		"add":        func(a, b int) int { return a + b },
		"last": func(i int, a interface{}) bool {
			v := reflect.ValueOf(a)
			return i == v.Len()-1
		},
		"dict": func(vals ...interface{}) (map[string]interface{}, error) {
			if len(vals)%2 != 0 {
				return nil, fmt.Errorf("dict: odd number of args")
			}
			d := make(map[string]interface{}, len(vals)/2)
			for i := 0; i < len(vals); i += 2 {
				key, ok := vals[i].(string)
				if !ok {
					return nil, fmt.Errorf("dict: key %v is not a string", vals[i])
				}
				d[key] = vals[i+1]
			}
			return d, nil
		},
		"synsetTail": func(locale, synsetName string) string {
			ruthes := tr.T(locale, "ruthes_concept", nil)
			return "[" + ruthes + ": " + strings.ToLower(synsetName) + "]"
		},
		"safeHTML": func(s string) template.HTML {
			return template.HTML(s)
		},
		"printf": fmt.Sprintf,
	}
}

func capitalize(s string) string {
	if s == "" {
		return ""
	}
	runes := []rune(strings.ToLower(s))
	runes[0] = unicode.ToUpper(runes[0])
	return string(runes)
}

func pathFunc(locale, routeName string, params interface{}) string {
	get := func(key string) string {
		switch m := params.(type) {
		case map[string]interface{}:
			if v, ok := m[key]; ok {
				return fmt.Sprint(v)
			}
		case map[string]string:
			if v, ok := m[key]; ok {
				return v
			}
		}
		return ""
	}
	switch routeName {
	case "homepage":
		return "/" + locale + "/"
	case "search":
		s := get("searchString")
		if s == "" {
			return "/" + locale + "/search"
		}
		return "/" + locale + "/search/" + url.PathEscape(s)
	case "sense":
		name := get("name")
		meaning := get("meaning")
		if meaning == "" || meaning == "0" {
			return "/" + locale + "/sense/" + url.PathEscape(name)
		}
		return "/" + locale + "/sense/" + url.PathEscape(name) + "+" + meaning
	}
	return "/"
}
