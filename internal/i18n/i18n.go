package i18n

import (
	"fmt"
	"io/fs"
	"os"
	"strings"

	"gopkg.in/yaml.v3"
)

type Translator struct {
	bundles  map[string]map[string]string
	fallback string
}

func Load(dir string) (*Translator, error) {
	entries, err := os.ReadDir(dir)
	if err != nil {
		return nil, fmt.Errorf("read locales dir: %w", err)
	}

	tr := &Translator{
		bundles:  make(map[string]map[string]string),
		fallback: "en",
	}

	for _, entry := range entries {
		if entry.IsDir() {
			continue
		}
		name := entry.Name()
		if !strings.HasSuffix(name, ".yml") {
			continue
		}
		locale := strings.TrimSuffix(name, ".yml")

		data, err := os.ReadFile(dir + "/" + name)
		if err != nil {
			return nil, fmt.Errorf("read %s: %w", name, err)
		}

		var bundle map[string]string
		if err := yaml.Unmarshal(data, &bundle); err != nil {
			return nil, fmt.Errorf("parse %s: %w", name, err)
		}
		tr.bundles[locale] = bundle
	}

	return tr, nil
}

func LoadFS(fsys fs.FS, dir string) (*Translator, error) {
	entries, err := fs.ReadDir(fsys, dir)
	if err != nil {
		return nil, fmt.Errorf("read locales dir: %w", err)
	}

	tr := &Translator{
		bundles:  make(map[string]map[string]string),
		fallback: "en",
	}

	for _, entry := range entries {
		if entry.IsDir() {
			continue
		}
		name := entry.Name()
		if !strings.HasSuffix(name, ".yml") {
			continue
		}
		locale := strings.TrimSuffix(name, ".yml")

		data, err := fs.ReadFile(fsys, dir+"/"+name)
		if err != nil {
			return nil, fmt.Errorf("read %s: %w", name, err)
		}

		var bundle map[string]string
		if err := yaml.Unmarshal(data, &bundle); err != nil {
			return nil, fmt.Errorf("parse %s: %w", name, err)
		}
		tr.bundles[locale] = bundle
	}

	return tr, nil
}

func (t *Translator) T(locale, key string, params map[string]string) string {
	msg, ok := t.bundles[locale][key]
	if !ok {
		msg, ok = t.bundles[t.fallback][key]
		if !ok {
			return key
		}
	}
	for k, v := range params {
		msg = strings.ReplaceAll(msg, k, v)
	}
	return msg
}

func (t *Translator) Locales() []string {
	locales := make([]string, 0, len(t.bundles))
	for l := range t.bundles {
		locales = append(locales, l)
	}
	return locales
}
