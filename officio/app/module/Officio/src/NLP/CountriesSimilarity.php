<?php

namespace Officio\NLP;

use Laminas\Db\Sql\Select;
use Officio\Common\DbAdapterWrapper;

/**
 * @author    Uniques Software Corp.
 * @copyright Uniques
 */
class CountriesSimilarity
{

    /** @var DbAdapterWrapper */
    protected $_db2;

    public function __construct(DbAdapterWrapper $db)
    {
        $this->_db2 = $db;
    }

    public function suggest($input)
    {
        $result = array();
        $input  = strtolower($input ?? '');

        $input    = $this->cleanItem($input);
        $synonyms = $this->getSynonyms();

        $fullMatchesCount = 0;
        foreach ($synonyms as $country) {
            if (is_array($country['synonyms'])) {
                foreach ($country['synonyms'] as $synonym) {
                    $score = JaroWinkler::jaroWinkler($input, $synonym);
                    if ($score == 1) {
                        $result[$country['countries_name']] = $score;
                        $fullMatchesCount                   += 1;
                    } elseif ($score >= 0.75) {
                        if (!isset($result[$country['countries_name']]) || ($result[$country['countries_name']] < $score)) {
                            $result[$country['countries_name']] = $score;
                        }
                    }
                }
            }
        }

        arsort($result);

        # if we have a lot of suggestions
        if (count($result) > 1) {
            # and many of them has full match with query, so select only those matched with similarity_score == 1
            if ($fullMatchesCount > 1) {
                $result = array_filter($result, function ($x) {
                    return $x == 1;
                });
            } else {
                # select only single top matched item
                $k = key($result);
                # if top one has similarity greater our threshold -> return single value
                if ($result[$k] >= 0.93) {
                    return array($k);
                }
            }
        }

        return array_keys($result);

    }

    public function saveSynonym($synonym, $selectedCountry)
    {
        if (!empty($synonym)) {
            $synonym = strtolower($synonym ?? '');
            $item    = $this->getSynonym($selectedCountry);

            if (!empty($item)) {
                if ($synonym != strtolower($selectedCountry ?? '') && $synonym != strtolower($item['immi_code_3'] ?? '')) {
                    if (!empty($item['synonyms'])) {
                        $item['synonyms'] = @unserialize($item['synonyms']);

                        if (is_array($item['synonyms']) && !in_array($synonym, $item['synonyms'])) {
                            $item['synonyms'][] = $synonym;
                        } else { # maybe sometimes
                            $item['synonyms'] = array($synonym);
                        }
                    } else {
                        $item['synonyms'] = array($synonym);
                    }

                    $this->_db2->update(
                        'country_master',
                        ['synonyms' => serialize($item['synonyms'])],
                        ['countries_id' => (int)$item['countries_id']]
                    );
                }
            }
        }
    }

    protected function cleanItem($item)
    {
        $stopWords = array(
            'and',
            'republic',
            'democratic',
            'of',
            'the',
            'peoples',
            'former',
            'islamic',
            'territory',
            'terr',
            'federated',
            'federation',
            'arab',
        );

        $item  = preg_replace("/[^a-zA-Z ]+/", '', $item) ?? '';
        $words = array_filter(explode(' ', $item), function ($x) use ($stopWords) {
            return !empty($x) and !in_array($x, $stopWords);
        });
        asort($words);

        return implode(' ', $words);
    }

    protected function getSynonyms()
    {
        $select = (new Select())
            ->from(['c' => 'country_master'])
            ->columns(['countries_id', 'countries_name', 'synonyms', 'immi_code_3'])
            ->where(['c.type' => 'vevo']);

        $results = $this->_db2->fetchAll($select);

        foreach ($results as &$item) {
            if (!empty($item['synonyms'])) {
                $item['synonyms'] = @unserialize($item['synonyms']);
            } else {
                $item['synonyms'] = array(strtolower($item['countries_name'] ?? ''));
            }

            foreach ($item['synonyms'] as &$syn) {
                $syn = $this->cleanItem($syn);
            }

            $item['synonyms'][] = strtolower($item['immi_code_3'] ?? '');
        }

        return $results;
    }

    protected function getSynonym($countryName)
    {
        $select = (new Select())
            ->from(['c' => 'country_master'])
            ->columns(['countries_id', 'synonyms', 'immi_code_3'])
            ->where(['c.type' => 'vevo', 'c.countries_name' => $countryName]);

        return $this->_db2->fetchRow($select);
    }
}
