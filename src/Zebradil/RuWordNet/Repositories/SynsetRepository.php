<?php

namespace Zebradil\RuWordNet\Repositories;

use Zebradil\RuWordNet\Models\Synset;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractRepository;

/**
 * Class SenseRepository.
 */
class SynsetRepository extends AbstractRepository
{
    const TABLE_NAME = 'synsets';
    const MODEL_CLASS = Synset::class;
    const PRIMARY_KEY = ['id'];

    /**
     * @param Synset $synset Synset object
     *
     * @return string[][] ILI relations data
     */
    public function getIliRelationsForSynset(Synset $synset): array
    {
        $sql = "
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
              WHERE source = 'auto verified'
              UNION
              SELECT concept_id, m.wn30
              FROM ili
                JOIN wn_mapping m ON m.wn31 = ili.wn_id
              WHERE source = 'manual'
          ) ili ON ili.concept_id = c.id
            JOIN ili_map_wn m
              ON m.wn = ili.wn_id
              AND m.version = 30
            JOIN wn_data d
              ON d.id = m.wn
              AND d.version = m.version
          WHERE c.name = :conceptName
            AND :partOfSpeech LIKE '%' || substring(ili.wn_id, '.$') || '%'";

        /* wn  rwn  desc
         * n   N    NOUN
         * v   V    VERB
         * a   Adj  ADJECTIVE
         * s   Adj  ADJECTIVE SATELLITE
         * r   -    ADVERB
         */
        if ('Adj' === $synset->part_of_speech) {
            $partOfSpeech = 'as';
        } else {
            $partOfSpeech = mb_strtolower($synset->part_of_speech);
        }

        $params = [
            'conceptName' => mb_strtoupper($synset->name),
            'partOfSpeech' => $partOfSpeech,
        ];

        return array_map(function (array $row) {
            $row['lemma_names'] = json_decode($row['lemma_names'], true);

            return $row;
        }, $this->db->fetchAll($sql, $params));
    }
}
