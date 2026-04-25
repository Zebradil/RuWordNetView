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

	// Single batched ILI query (replaces the per-synset loop that caused N round trips).
	iliByKey, err := r.buildILIBatch(ctx, synsets)
	if err != nil {
		return nil, err
	}

	details := make([]SenseDetail, 0, len(senses))
	for _, s := range senses {
		syn := synsets[s.SynsetID]
		detail := SenseDetail{
			Sense:           s,
			Synset:          syn,
			SynsetSenses:    synsetSenses[s.SynsetID],
			SenseRelations:  groupSenseRelations(s.ID, senseRels),
			SynsetRelations: groupSynsetRelations(s.SynsetID, synsetRels, targetSynsets, targetSenses),
			ILIRelations:    iliByKey[s.SynsetID],
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

// querySynsetRelations fetches synset→synset relations and the child synsets.
// Uses two focused queries instead of a three-way join to avoid row fan-out when
// child synsets have many senses.
func (r *Repo) querySynsetRelations(ctx context.Context, synsetIDs []string) (
	map[string][]synsetRel, map[string]Synset, map[string][]Sense, error,
) {
	// Query 1: relations + child synset metadata (no senses, no fan-out).
	const qRels = `
		SELECT sr.parent_id, sr.name AS rel_name,
		       y.id, y.name, y.definition, y.part_of_speech
		FROM synset_relations sr
		JOIN synsets y ON y.id = sr.child_id
		WHERE sr.parent_id = ANY($1)
		ORDER BY sr.parent_id, sr.name, y.id`
	rows, err := r.pool.Query(ctx, qRels, synsetIDs)
	if err != nil {
		return nil, nil, nil, fmt.Errorf("querySynsetRelations/rels: %w", err)
	}

	rels := make(map[string][]synsetRel)
	synsets := make(map[string]Synset)
	childIDs := make([]string, 0)
	seen := make(map[string]bool)

	for rows.Next() {
		var (
			parentID, relName string
			y                 Synset
			def               *string
		)
		if err := rows.Scan(&parentID, &relName, &y.ID, &y.Name, &def, &y.PartOfSpeech); err != nil {
			rows.Close()
			return nil, nil, nil, err
		}
		if def != nil {
			y.Definition = *def
		}
		synsets[y.ID] = y

		key := parentID + "|" + relName + "|" + y.ID
		if !seen[key] {
			seen[key] = true
			rels[parentID] = append(rels[parentID], synsetRel{parentID: parentID, relName: relName, childID: y.ID})
			childIDs = append(childIDs, y.ID)
		}
	}
	rows.Close()
	if err := rows.Err(); err != nil {
		return nil, nil, nil, err
	}

	if len(childIDs) == 0 {
		return rels, synsets, nil, nil
	}

	// Query 2: senses for child synsets only.
	senses, err := r.querySynsetSenses(ctx, childIDs)
	if err != nil {
		return nil, nil, nil, fmt.Errorf("querySynsetRelations/senses: %w", err)
	}
	return rels, synsets, senses, nil
}

// buildILIBatch fetches ILI data for all synsets in one query.
// Returns a map keyed by synset_id.
func (r *Repo) buildILIBatch(ctx context.Context, synsets map[string]Synset) (map[string][]ILIRelation, error) {
	if len(synsets) == 0 {
		return nil, nil
	}

	names := make([]string, 0, len(synsets))
	posSet := make(map[string]struct{})
	for _, syn := range synsets {
		names = append(names, strings.ToUpper(syn.Name))
		for _, ch := range iliPOS(syn.PartOfSpeech) {
			posSet[string(ch)] = struct{}{}
		}
	}
	posChars := make([]string, 0, len(posSet))
	for ch := range posSet {
		posChars = append(posChars, ch)
	}

	// concept_id filter is pushed into both UNION branches so the planner can use
	// the PK (concept_id, wn_id, source) instead of scanning all approved rows.
	const q = `
		SELECT
		    c.name,
		    right(ili.wn_id, 1) AS pos_char,
		    m.ili,
		    d.id,
		    d.name,
		    d.definition,
		    d.lemma_names
		FROM concepts c
		    JOIN (
		        SELECT concept_id, wn_id
		        FROM ili
		        WHERE concept_id = ANY(SELECT id FROM concepts WHERE name = ANY($1))
		          AND source != 'manual'
		          AND approved
		        UNION
		        SELECT ili.concept_id, wm.wn30
		        FROM ili
		            JOIN wn_mapping wm ON wm.wn31 = ili.wn_id
		        WHERE ili.concept_id = ANY(SELECT id FROM concepts WHERE name = ANY($1))
		          AND source = 'manual'
		          AND approved
		    ) ili ON ili.concept_id = c.id
		    JOIN ili_map_wn m
		        ON m.wn = ili.wn_id
		        AND m.version = 30
		    JOIN wn_data d
		        ON d.id = m.wn
		        AND d.version = m.version
		WHERE c.name = ANY($1)
		  AND right(ili.wn_id, 1) = ANY($2)`

	rows, err := r.pool.Query(ctx, q, names, posChars)
	if err != nil {
		return nil, fmt.Errorf("buildILIBatch: %w", err)
	}
	defer rows.Close()

	// Intermediate: group by (conceptName, posChar) so we can map back to synset IDs.
	type namePos struct{ name, posChar string }
	byNamePos := make(map[namePos][]ILIRelation)
	for rows.Next() {
		var (
			conceptName string
			posChar     string
			rel         ILIRelation
			lemmaJSON   string
		)
		if err := rows.Scan(&conceptName, &posChar, &rel.ILI, &rel.ID, &rel.Name, &rel.Definition, &lemmaJSON); err != nil {
			return nil, err
		}
		_ = json.Unmarshal([]byte(lemmaJSON), &rel.LemmaNames)
		byNamePos[namePos{conceptName, posChar}] = append(byNamePos[namePos{conceptName, posChar}], rel)
	}
	if err := rows.Err(); err != nil {
		return nil, err
	}

	// Map results back to synset_id for O(1) lookup in the caller.
	result := make(map[string][]ILIRelation, len(synsets))
	for synID, syn := range synsets {
		pos := iliPOS(syn.PartOfSpeech)
		name := strings.ToUpper(syn.Name)
		var merged []ILIRelation
		for _, ch := range pos {
			merged = append(merged, byNamePos[namePos{name, string(ch)}]...)
		}
		if len(merged) > 0 {
			result[synID] = merged
		}
	}
	return result, nil
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

func groupSenseRelations(senseID string, rels map[string][]senseRel) []SenseRelGroup {
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
