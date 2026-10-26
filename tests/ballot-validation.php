<?php
declare(strict_types=1);

// Database-free checks for the form boundary used before a ballot is reviewed.
require_once __DIR__ . '/../includes/voting.php';

$ballot = [[
    'position' => ['id' => 7, 'name' => 'President', 'max_selections' => 1],
    'candidates' => [['id' => 42], ['id' => 43]],
]];

$cases = [
    'valid selection' => [[7 => ['42']], true],
    'abstention' => [[7 => ['']], true],
    'another candidate' => [[7 => ['99']], false],
    'too many selections' => [[7 => ['42', '43']], false],
    'malformed candidate id' => [[7 => ['42garbage']], false],
    'zero candidate id' => [[7 => ['0']], false],
    'unknown position' => [[7 => ['42'], 8 => ['43']], false],
];

foreach ($cases as $name => [$submitted, $expected]) {
    $actual = sv_validate_submission($ballot, $submitted)['ok'];
    if ($actual !== $expected) {
        fwrite(STDERR, "FAIL $name\n");
        exit(1);
    }
    echo "PASS $name\n";
}
