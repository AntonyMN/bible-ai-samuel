<?php

namespace App\Services;

use App\Models\BiblePerson;
use App\Models\BibleRelationship;
use App\Models\BiblePlace;
use Illuminate\Support\Str;

class BibleFactService
{
    /**
     * Determine if a query is factual and retrieve relevant facts.
     */
    public function getFactsForQuery(string $query): array
    {
        $facts = [];
        $found = false;

        // 1. Detect Factual intent
        if (!$this->detectFactualIntent($query)) {
            return [
                'is_factual' => false,
                'facts' => [],
                'found' => false,
            ];
        }

        // 2. Basic entity extraction
        $words = str_word_count($query, 1);
        $capitalizedWords = array_filter($words, function($word) {
            return ctype_upper($word[0]);
        });
        
        // Remove common non-name capitalized words
        $stopWords = ['The', 'Who', 'Where', 'What', 'How', 'Did', 'Is', 'Was', 'Were', 'Biblical', 'Scripture', 'Father', 'Mother', 'Son', 'Daughter'];
        $entities = array_diff($capitalizedWords, $stopWords);

        foreach ($entities as $entity) {
            $variations = [
                $entity,
                $this->normalizeName($entity),
                preg_replace('/([a-z])(?=[a-z])/i', '$1%', $entity) . '%', // P%e%n%i%n%a%h%
            ];
            
            // Search People
            $query = BiblePerson::query();
            foreach ($variations as $v) {
                $query->orWhere('name', 'like', "%{$v}%");
            }
            $people = $query->get();
            foreach ($people as $person) {
                $found = true;
                $personFacts = "Fact: {$person->name}";
                if ($person->sex) $personFacts .= " ({$person->sex})";
                if ($person->tribe) $personFacts .= ", Tribe: {$person->tribe}";
                $personFacts .= ". ";
                if ($person->notes) $personFacts .= "Bio: {$person->notes}. ";
                
                // Relationships where this person is the subject (person_id_1)
                $relationships1 = BibleRelationship::where('person_id_1', $person->person_id)->get();
                foreach ($relationships1 as $rel) {
                    $otherPerson = BiblePerson::where('person_id', $rel->person_id_2)->first();
                    $otherName = $otherPerson ? $otherPerson->name : $rel->person_id_2;
                    $personFacts .= "Relationship: {$person->name} is the {$rel->relationship_type} of {$otherName} (Ref: {$rel->reference_id}). ";
                    
                    // Co-wife detection (if this person is a wife)
                    if ($rel->relationship_type === 'wife') {
                        $husbandId = $rel->person_id_2;
                        $otherWives = BibleRelationship::where('person_id_2', $husbandId)
                            ->where('relationship_type', 'wife')
                            ->where('person_id_1', '!=', $person->person_id)
                            ->get();
                        foreach ($otherWives as $ow) {
                            $cw = BiblePerson::where('person_id', $ow->person_id_1)->first();
                            if ($cw) {
                                $personFacts .= "Fact: {$person->name} and {$cw->name} are BOTH wives of {$otherName} (co-wives). They are NOT sisters. ";
                            }
                        }
                    }
                }

                // Relationships where this person is the object (person_id_2)
                $relationships2 = BibleRelationship::where('person_id_2', $person->person_id)->get();
                foreach ($relationships2 as $rel) {
                    $otherPerson = BiblePerson::where('person_id', $rel->person_id_1)->first();
                    $otherName = $otherPerson ? $otherPerson->name : $rel->person_id_1;
                    $personFacts .= "Relationship: {$otherName} is the {$rel->relationship_type} of {$person->name} (Ref: {$rel->reference_id}). ";
                    
                    // Co-wife detection (if this person has a husband)
                    if ($rel->relationship_type === 'husband') {
                        $husbandId = $rel->person_id_1;
                        $otherWives = BibleRelationship::where('person_id_2', $husbandId)
                            ->where('relationship_type', 'wife')
                            ->where('person_id_1', '!=', $person->person_id)
                            ->get();
                        foreach ($otherWives as $ow) {
                            $cw = BiblePerson::where('person_id', $ow->person_id_1)->first();
                            if ($cw) {
                                $personFacts .= "Fact: {$person->name} and {$cw->name} are BOTH wives of {$otherName} (co-wives). They are NOT sisters. ";
                            }
                        }
                    }
                }
                $facts[] = $personFacts;
            }

            // Search Places
            $places = BiblePlace::where('name', 'like', "%{$entity}%")->get();
            foreach ($places as $place) {
                $found = true;
                $placeFacts = "Fact: Place {$place->name}";
                if ($place->place_type) $placeFacts .= " ({$place->place_type})";
                $placeFacts .= ". ";
                if ($place->notes) $placeFacts .= "Note: {$place->notes}. ";
                if ($place->modern_equivalent) $placeFacts .= "Modern equivalent: {$place->modern_equivalent}. ";
                $facts[] = $placeFacts;
            }
        }

        return [
            'is_factual' => true,
            'facts' => array_unique($facts),
            'found' => $found,
        ];
    }

    /**
     * Normalize names by removing double letters for fuzzy matching.
     */
    private function normalizeName(string $name): string
    {
        return preg_replace('/(.)\1/', '$1', $name);
    }

    /**
     * Use simple keyword matching to detect factual intent.
     */
    private function detectFactualIntent(string $query): bool
    {
        $factualKeywords = ['who', 'where', 'father', 'mother', 'son', 'daughter', 'begat', 'born', 'died', 'location', 'place', 'wife', 'husband', 'brother', 'sister', 'relationship'];
        $queryLower = strtolower($query);
        
        foreach ($factualKeywords as $keyword) {
            if (str_contains($queryLower, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
}
