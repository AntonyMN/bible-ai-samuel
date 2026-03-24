<?php

namespace App\Console\Commands;

use App\Models\BiblePerson;
use App\Models\BibleRelationship;
use App\Models\BiblePlace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportBibleData extends Command
{
    protected $signature = 'bible:import';
    protected $description = 'Import factual Bible data from open-source repositories';

    public function handle()
    {
        $this->importPeople();
        $this->importRelationships();
        $this->importPlaces();
        $this->info("Import completed successfully!");
    }

    private function importPeople()
    {
        $this->info("Importing People...");
        $csv = Http::get("https://raw.githubusercontent.com/BradyStephenson/bible-data/main/BibleData-Person.csv")->body();
        $csv = $this->removeBOM($csv);
        $lines = explode("\n", $csv);
        $headerLine = array_shift($lines);
        if (!$headerLine) return;
        $header = str_getcsv($headerLine);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $data = str_getcsv($line);
            if (count($data) < count($header)) {
                $data = array_pad($data, count($header), '');
            }
            $row = array_combine($header, array_slice($data, 0, count($header)));
            
            BiblePerson::updateOrCreate(
                ['person_id' => $row['person_id']],
                [
                    'name' => $row['person_name'] ?? '',
                    'sex' => $row['sex'] ?? '',
                    'tribe' => $row['tribe'] ?? '',
                    'notes' => $row['person_notes'] ?? '',
                ]
            );
        }
    }

    private function importRelationships()
    {
        $this->info("Importing Relationships...");
        $csv = Http::get("https://raw.githubusercontent.com/BradyStephenson/bible-data/main/BibleData-PersonRelationship.csv")->body();
        $csv = $this->removeBOM($csv);
        $lines = explode("\n", $csv);
        $headerLine = array_shift($lines);
        if (!$headerLine) return;
        $header = str_getcsv($headerLine);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $data = str_getcsv($line);
            if (count($data) < count($header)) {
                $data = array_pad($data, count($header), '');
            }
            $row = array_combine($header, array_slice($data, 0, count($header)));
            
            BibleRelationship::updateOrCreate(
                [
                    'person_id_1' => $row['person_id_1'],
                    'person_id_2' => $row['person_id_2'],
                    'relationship_type' => $row['relationship_type'],
                ],
                [
                    'relationship_category' => $row['relationship_category'] ?? '',
                    'reference_id' => $row['reference_id'] ?? '',
                    'notes' => $row['relationship_notes'] ?? '',
                ]
            );
        }
    }

    private function importPlaces()
    {
        $this->info("Importing Places...");
        $csv = Http::get("https://raw.githubusercontent.com/BradyStephenson/bible-data/main/BibleData-Place.csv")->body();
        $csv = $this->removeBOM($csv);
        $lines = explode("\n", $csv);
        $headerLine = array_shift($lines);
        if (!$headerLine) return;
        $header = str_getcsv($headerLine);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $data = str_getcsv($line);
            if (count($data) < count($header)) {
                $data = array_pad($data, count($header), '');
            }
            $row = array_combine($header, array_slice($data, 0, count($header)));
            
            BiblePlace::updateOrCreate(
                ['place_id' => $row['place_id']],
                [
                    'name' => $row['place_name'] ?? '',
                    'place_type' => $row['place_type'] ?? '',
                    'modern_equivalent' => $row['modern_equivalent'] ?? '',
                    'notes' => $row['place_notes'] ?? '',
                    'openbible_id' => $row['openbible_id'] ?? '',
                ]
            );
        }
    }

    private function removeBOM($text)
    {
        $bom = pack('H*', 'EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }
}
