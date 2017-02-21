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
}
