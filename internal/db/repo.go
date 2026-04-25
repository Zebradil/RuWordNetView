package db

import (
	"context"
	"encoding/json"
	"fmt"
	"sort"
	"strings"

	"github.com/jackc/pgx/v5"
	"github.com/jackc/pgx/v5/pgxpool"
	"golang.org/x/sync/errgroup"
)

var synsetRelationOrder = []string{
	"hypernym", "hyponym",
	"part meronym", "part holonym",
	"member meronym", "member holonym",
	"substance meronym", "substance holonym",
	"meronym", "holonym",
	"instance hypernym", "instance hyponym",
	"antonym", "POS-synonymy",
	"cause", "entailment",
	"related",
}

var senseRelationOrder = []string{"composed_of", "derived_from"}

type Repo struct {
	pool *pgxpool.Pool
}

func NewRepo(pool *pgxpool.Pool) *Repo {
	return &Repo{pool: pool}
}

func (r *Repo) GetByName(ctx context.Context, name string) ([]Sense, error) {
	const q = `
		SELECT id, synset_id, name, lemma, synt_type, meaning
		FROM senses
		WHERE name = $1
		ORDER BY meaning
		LIMIT 10`
	rows, err := r.pool.Query(ctx, q, strings.ToUpper(strings.TrimSpace(name)))
	if err != nil {
		return nil, fmt.Errorf("GetByName: %w", err)
	}
	defer rows.Close()
	return scanSenses(rows)
}

func (r *Repo) GetByNameAndMeaning(ctx context.Context, name string, meaning int) (*Sense, error) {
	const q = `
		SELECT id, synset_id, name, lemma, synt_type, meaning
		FROM senses
		WHERE name = $1 AND meaning = $2
		LIMIT 1`
	rows, err := r.pool.Query(ctx, q, strings.ToUpper(strings.TrimSpace(name)), meaning)
	if err != nil {
		return nil, fmt.Errorf("GetByNameAndMeaning: %w", err)
	}
	defer rows.Close()
	senses, err := scanSenses(rows)
	if err != nil {
		return nil, err
	}
	if len(senses) == 0 {
		return nil, nil
	}
	return &senses[0], nil
}

// BuildLexemeView eager-loads all related data for a set of senses in 4 parallel queries.
func (r *Repo) BuildLexemeView(ctx context.Context, senses []Sense) (*LexemeView, error) {
	if len(senses) == 0 {
		return &LexemeView{}, nil
	}

	senseIDs := make([]string, len(senses))
	synsetIDs := make([]string, len(senses))
	for i, s := range senses {
		senseIDs[i] = s.ID
		synsetIDs[i] = s.SynsetID
	}

	var (
		synsets       map[string]Synset
		synsetSenses  map[string][]Sense     // synset_id → senses
		senseRels     map[string][]senseRel  // sense_id → rels
		synsetRels    map[string][]synsetRel // synset_id → rels
		targetSynsets map[string]Synset      // child synset_id → synset
		targetSenses  map[string][]Sense     // child synset_id → senses
	)

	g, gctx := errgroup.WithContext(ctx)

	g.Go(func() error {
		var err error
		synsets, err = r.querySynsets(gctx, synsetIDs)
		return err
	})

	g.Go(func() error {
		var err error
		synsetSenses, err = r.querySynsetSenses(gctx, synsetIDs)
		return err
	})

	g.Go(func() error {
		var err error
		senseRels, err = r.querySenseRelations(gctx, senseIDs)
		return err
	})

	g.Go(func() error {
		var err error
		synsetRels, targetSynsets, targetSenses, err = r.querySynsetRelations(gctx, synsetIDs)
		return err
	})

	if err := g.Wait(); err != nil {
		return nil, err
	}

	details := make([]SenseDetail, 0, len(senses))
	for _, s := range senses {
		syn := synsets[s.SynsetID]

		iliRels, err := r.queryILIRelations(ctx, syn)
		if err != nil {
			return nil, err
		}

		detail := SenseDetail{
			Sense:           s,
			Synset:          syn,
			SynsetSenses:    synsetSenses[s.SynsetID],
			SenseRelations:  groupSenseRelations(s.ID, senseRels, synsets),
			SynsetRelations: groupSynsetRelations(s.SynsetID, synsetRels, targetSynsets, targetSenses),
			ILIRelations:    iliRels,
		}
		details = append(details, detail)
	}

	return &LexemeView{Senses: details}, nil
}

type senseRel struct {
	parentID string
	relName  string
	child    Sense
}

type synsetRel struct {
	parentID string
	relName  string
	childID  string
}

func (r *Repo) querySynsets(ctx context.Context, synsetIDs []string) (map[string]Synset, error) {
	const q = `
		SELECT id, name, definition, part_of_speech
		FROM synsets
		WHERE id = ANY($1)`
	rows, err := r.pool.Query(ctx, q, synsetIDs)
	if err != nil {
		return nil, fmt.Errorf("querySynsets: %w", err)
	}
	defer rows.Close()
	result := make(map[string]Synset)
	for rows.Next() {
		var s Synset
		var def *string
		if err := rows.Scan(&s.ID, &s.Name, &def, &s.PartOfSpeech); err != nil {
			return nil, err
		}
		if def != nil {
			s.Definition = *def
		}
		result[s.ID] = s
	}
	return result, rows.Err()
}

func (r *Repo) querySynsetSenses(ctx context.Context, synsetIDs []string) (map[string][]Sense, error) {
	const q = `
		SELECT id, synset_id, name, lemma, synt_type, meaning
		FROM senses
		WHERE synset_id = ANY($1)
		ORDER BY synset_id, name`
	rows, err := r.pool.Query(ctx, q, synsetIDs)
	if err != nil {
		return nil, fmt.Errorf("querySynsetSenses: %w", err)
	}
	defer rows.Close()
	result := make(map[string][]Sense)
	for rows.Next() {
		var s Sense
		if err := rows.Scan(&s.ID, &s.SynsetID, &s.Name, &s.Lemma, &s.SyntType, &s.Meaning); err != nil {
			return nil, err
		}
		result[s.SynsetID] = append(result[s.SynsetID], s)
	}
	return result, rows.Err()
}

func (r *Repo) querySenseRelations(ctx context.Context, senseIDs []string) (map[string][]senseRel, error) {
	const q = `
		SELECT sr.parent_id, sr.name AS rel_name,
		       c.id, c.synset_id, c.name, c.lemma, c.synt_type, c.meaning
		FROM sense_relations sr
		JOIN senses c ON c.id = sr.child_id
		WHERE sr.parent_id = ANY($1)
		  AND sr.info <> 'deleted'`
	rows, err := r.pool.Query(ctx, q, senseIDs)
	if err != nil {
		return nil, fmt.Errorf("querySenseRelations: %w", err)
	}
	defer rows.Close()
	result := make(map[string][]senseRel)
	for rows.Next() {
		var rel senseRel
		if err := rows.Scan(
			&rel.parentID, &rel.relName,
			&rel.child.ID, &rel.child.SynsetID, &rel.child.Name,
			&rel.child.Lemma, &rel.child.SyntType, &rel.child.Meaning,
		); err != nil {
			return nil, err
		}
		result[rel.parentID] = append(result[rel.parentID], rel)
	}
	return result, rows.Err()
}

func (r *Repo) querySynsetRelations(ctx context.Context, synsetIDs []string) (
	map[string][]synsetRel, map[string]Synset, map[string][]Sense, error,
) {
	const q = `
		SELECT sr.parent_id, sr.name AS rel_name,
		       y.id, y.name, y.definition, y.part_of_speech,
		       cs.id, cs.synset_id, cs.name, cs.lemma, cs.synt_type, cs.meaning
		FROM synset_relations sr
		JOIN synsets y ON y.id = sr.child_id
		JOIN senses cs ON cs.synset_id = y.id
		WHERE sr.parent_id = ANY($1)
		ORDER BY sr.parent_id, sr.name, y.id, cs.name`
	rows, err := r.pool.Query(ctx, q, synsetIDs)
	if err != nil {
		return nil, nil, nil, fmt.Errorf("querySynsetRelations: %w", err)
	}
	defer rows.Close()

	rels := make(map[string][]synsetRel)
	synsets := make(map[string]Synset)
	senses := make(map[string][]Sense)

	seen := make(map[string]bool) // deduplicate synset_relations rows per (parentID, relName, childID)
	for rows.Next() {
		var (
			parentID, relName string
			y                 Synset
			cs                Sense
			def               *string
		)
		if err := rows.Scan(
			&parentID, &relName,
			&y.ID, &y.Name, &def, &y.PartOfSpeech,
			&cs.ID, &cs.SynsetID, &cs.Name, &cs.Lemma, &cs.SyntType, &cs.Meaning,
		); err != nil {
			return nil, nil, nil, err
		}
		if def != nil {
			y.Definition = *def
		}
		synsets[y.ID] = y
		senses[y.ID] = append(senses[y.ID], cs)

		key := parentID + "|" + relName + "|" + y.ID
		if !seen[key] {
			seen[key] = true
			rels[parentID] = append(rels[parentID], synsetRel{parentID: parentID, relName: relName, childID: y.ID})
		}
	}
	return rels, synsets, senses, rows.Err()
}

func (r *Repo) queryILIRelations(ctx context.Context, syn Synset) ([]ILIRelation, error) {
	const q = `
		SELECT
		    m.ili,
		    d.id,
		    d.name,
		    d.definition,
		    d.lemma_names
		FROM concepts c
		    JOIN (
		        SELECT concept_id, wn_id
		        FROM ili
		        WHERE source != 'manual'
		          AND approved
		        UNION
		        SELECT concept_id, wm.wn30
		        FROM ili
		            JOIN wn_mapping wm ON wm.wn31 = ili.wn_id
		        WHERE source = 'manual'
		          AND approved
		    ) ili ON ili.concept_id = c.id
		    JOIN ili_map_wn m
		        ON m.wn = ili.wn_id
		        AND m.version = 30
		    JOIN wn_data d
		        ON d.id = m.wn
		        AND d.version = m.version
		WHERE c.name = $1
		  AND $2 LIKE '%' || substring(ili.wn_id, '.$') || '%'`

	pos := iliPOS(syn.PartOfSpeech)
	rows, err := r.pool.Query(ctx, q, strings.ToUpper(syn.Name), pos)
	if err != nil {
		return nil, fmt.Errorf("queryILIRelations: %w", err)
	}
	defer rows.Close()

	var result []ILIRelation
	for rows.Next() {
		var rel ILIRelation
		var lemmaJSON string
		if err := rows.Scan(&rel.ILI, &rel.ID, &rel.Name, &rel.Definition, &lemmaJSON); err != nil {
			return nil, err
		}
		_ = json.Unmarshal([]byte(lemmaJSON), &rel.LemmaNames)
		result = append(result, rel)
	}
	return result, rows.Err()
}

func iliPOS(partOfSpeech string) string {
	if partOfSpeech == "Adj" {
		return "as"
	}
	return strings.ToLower(partOfSpeech)
}

func scanSenses(rows pgx.Rows) ([]Sense, error) {
	var result []Sense
	for rows.Next() {
		var s Sense
		if err := rows.Scan(&s.ID, &s.SynsetID, &s.Name, &s.Lemma, &s.SyntType, &s.Meaning); err != nil {
			return nil, err
		}
		result = append(result, s)
	}
	return result, rows.Err()
}

func groupSenseRelations(senseID string, rels map[string][]senseRel, synsets map[string]Synset) []SenseRelGroup {
	rawRels := rels[senseID]
	grouped := make(map[string][]Sense)
	for _, rel := range rawRels {
		grouped[rel.relName] = append(grouped[rel.relName], rel.child)
	}

	order := make(map[string]int, len(senseRelationOrder))
	for i, name := range senseRelationOrder {
		order[name] = i
	}

	names := make([]string, 0, len(grouped))
	for name := range grouped {
		names = append(names, name)
	}
	sort.SliceStable(names, func(i, j int) bool {
		ai, aOK := order[names[i]]
		bi, bOK := order[names[j]]
		if aOK && bOK {
			return ai < bi
		}
		if aOK {
			return true
		}
		if bOK {
			return false
		}
		return names[i] < names[j]
	})

	result := make([]SenseRelGroup, 0, len(names))
	for _, name := range names {
		result = append(result, SenseRelGroup{Name: name, Senses: grouped[name]})
	}
	return result
}

func groupSynsetRelations(
	synsetID string,
	rels map[string][]synsetRel,
	targetSynsets map[string]Synset,
	targetSenses map[string][]Sense,
) []RelationGroup {
	rawRels := rels[synsetID]

	type groupKey struct{ name, childID string }
	grouped := make(map[string][]SynsetTarget)
	seen := make(map[groupKey]bool)
	for _, rel := range rawRels {
		k := groupKey{rel.relName, rel.childID}
		if seen[k] {
			continue
		}
		seen[k] = true
		target := SynsetTarget{
			Synset: targetSynsets[rel.childID],
			Senses: targetSenses[rel.childID],
		}
		grouped[rel.relName] = append(grouped[rel.relName], target)
	}

	order := make(map[string]int, len(synsetRelationOrder))
	for i, name := range synsetRelationOrder {
		order[name] = i
	}

	names := make([]string, 0, len(grouped))
	for name := range grouped {
		names = append(names, name)
	}
	sort.SliceStable(names, func(i, j int) bool {
		ai, aOK := order[names[i]]
		bi, bOK := order[names[j]]
		if aOK && bOK {
			return ai < bi
		}
		if aOK {
			return true
		}
		if bOK {
			return false
		}
		return names[i] < names[j]
	})

	result := make([]RelationGroup, 0, len(names))
	for _, name := range names {
		result = append(result, RelationGroup{Name: name, Targets: grouped[name]})
	}
	return result
}
