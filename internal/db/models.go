package db

import "strconv"

type Sense struct {
	ID       string
	SynsetID string
	Name     string
	Lemma    string
	SyntType string
	Meaning  int
}

func (s Sense) FullName() string {
	if s.Meaning == 0 {
		return s.Name
	}
	return s.Name + " " + strconv.Itoa(s.Meaning)
}

type Synset struct {
	ID           string
	Name         string
	Definition   string
	PartOfSpeech string
}

type ILIRelation struct {
	ILI        string
	ID         string
	Name       string
	Definition string
	LemmaNames []string
}

type SenseRelGroup struct {
	Name   string
	Senses []Sense
}

type RelationGroup struct {
	Name    string
	Targets []SynsetTarget
}

type SynsetTarget struct {
	Synset Synset
	Senses []Sense
}

type SenseDetail struct {
	Sense           Sense
	Synset          Synset
	SynsetSenses    []Sense
	SenseRelations  []SenseRelGroup
	SynsetRelations []RelationGroup
	ILIRelations    []ILIRelation
}

type LexemeView struct {
	Senses []SenseDetail
}
