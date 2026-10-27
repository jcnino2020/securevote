<?php
declare(strict_types=1);

/** Fictional elections and baseline totals used only by the separate /demo database. */
function demo_seed_elections(PDO $pdo): void
{
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS demo_elections (id INTEGER PRIMARY KEY AUTOINCREMENT, slug TEXT NOT NULL UNIQUE, title TEXT NOT NULL, description TEXT NOT NULL, category TEXT NOT NULL, baseline_ballots INTEGER NOT NULL DEFAULT 0, display_order INTEGER NOT NULL)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS demo_positions (id INTEGER PRIMARY KEY AUTOINCREMENT, election_id INTEGER NOT NULL REFERENCES demo_elections(id), title TEXT NOT NULL, description TEXT NOT NULL, display_order INTEGER NOT NULL, UNIQUE(election_id, title))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS demo_candidates (id INTEGER PRIMARY KEY AUTOINCREMENT, position_id INTEGER NOT NULL REFERENCES demo_positions(id), name TEXT NOT NULL, slate TEXT NOT NULL, year_level TEXT NOT NULL, statement TEXT NOT NULL, baseline_votes INTEGER NOT NULL DEFAULT 0, display_order INTEGER NOT NULL, UNIQUE(position_id, name))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS demo_ballots (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL REFERENCES demo_users(id), election_id INTEGER NOT NULL REFERENCES demo_elections(id), receipt TEXT NOT NULL UNIQUE, submitted_at TEXT NOT NULL, UNIQUE(user_id, election_id))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS demo_ballot_choices (ballot_id INTEGER NOT NULL REFERENCES demo_ballots(id), position_id INTEGER NOT NULL REFERENCES demo_positions(id), candidate_id INTEGER NOT NULL REFERENCES demo_candidates(id), PRIMARY KEY(ballot_id, position_id))');
    $pdo->exec('CREATE INDEX IF NOT EXISTS demo_ballots_election ON demo_ballots(election_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS demo_choices_candidate ON demo_ballot_choices(candidate_id)');
    if ((int) $pdo->query('SELECT COUNT(*) FROM demo_candidates')->fetchColumn() >= 24) return;

    $samples = [
        ['student-council-2026', '2026 University Student Council', 'Choose student leaders to represent campus concerns, events, and student services.', 'Student government', 120, [
            ['President', 'Leads the council and represents the student body.', [
                ['Mika Santos', 'Forward Together', '4th year', 'Open monthly forums and publish a clear action tracker for every student concern.', 50],
                ['Adrian Reyes', 'Campus First', '3rd year', 'Make student services easier to access and respond faster to campus issues.', 42],
                ['Leah Navarro', 'Independent', '4th year', 'Bring more voices into decisions through regular class consultations.', 28],
            ]],
            ['Vice President', 'Coordinates council committees and campus programs.', [
                ['Rafael Cruz', 'Forward Together', '3rd year', 'Connect organizations so student projects can reach more people.', 47],
                ['Sofia Lim', 'Campus First', '2nd year', 'Build a calendar students can trust for activities and deadlines.', 39],
                ['Noel Garcia', 'Independent', '3rd year', 'Make council meetings easier for students to follow and join.', 34],
            ]],
            ['Secretary', 'Keeps public records and clear council communications.', [
                ['Dana Chua', 'Forward Together', '3rd year', 'Publish concise meeting notes and accessible announcements.', 54],
                ['Iris Mendoza', 'Campus First', '2nd year', 'Improve how students find forms, minutes, and event details.', 36],
                ['Paolo Dizon', 'Independent', '4th year', 'Create a reliable archive of decisions and promises.', 30],
            ]],
            ['Treasurer', 'Oversees budget reporting for student initiatives.', [
                ['Elena Flores', 'Forward Together', '4th year', 'Share simple budget reports after every major activity.', 44],
                ['Marco Tan', 'Campus First', '3rd year', 'Prioritize transparent spending and practical support for clubs.', 41],
                ['Nina Ramos', 'Independent', '2nd year', 'Let students suggest and track small campus improvement grants.', 35],
            ]],
        ]],
        ['sustainability-council', 'Campus Sustainability Council', 'Select student representatives for greener facilities and community projects.', 'Campus initiatives', 80, [
            ['Council Lead', 'Sets priorities and coordinates sustainability projects.', [
                ['Aira Cruz', 'Green Campus', '3rd year', 'Start student-led waste audits and publish measurable goals.', 33],
                ['Jonah Cruz', 'Eco Action', '4th year', 'Expand refill stations and reduce single-use materials.', 29],
                ['Bea Rivera', 'Independent', '2nd year', 'Make sustainability projects easy for every course to join.', 18],
            ]],
            ['Community Representative', 'Connects volunteers and local partners.', [
                ['Tessa Yu', 'Green Campus', '2nd year', 'Create regular neighborhood cleanups with lasting partnerships.', 31],
                ['Ivan Torres', 'Eco Action', '3rd year', 'Coordinate practical volunteer work with nearby communities.', 27],
                ['Sam Ortega', 'Independent', '4th year', 'Give small student groups more support to run local projects.', 22],
            ]],
        ]],
        ['library-advisory-board', 'Library Advisory Board', 'Help shape study spaces, learning resources, and library services.', 'Student services', 45, [
            ['Board Chair', 'Chairs student feedback and service planning.', [
                ['Isabel Reyes', 'Open Library', '4th year', 'Extend access to quiet study areas during peak weeks.', 19],
                ['Kai Bautista', 'Study First', '3rd year', 'Improve booking and access to shared study rooms.', 15],
                ['Rina Velasco', 'Independent', '2nd year', 'Make course resources easier to discover online.', 11],
            ]],
            ['Student Representative', 'Brings everyday student needs to the board.', [
                ['Luis Mercado', 'Open Library', '2nd year', 'Bring faster feedback channels to the library desk.', 17],
                ['Maya Aquino', 'Study First', '3rd year', 'Advocate for clearer library hours and resource guides.', 16],
                ['Theo Villanueva', 'Independent', '4th year', 'Improve accessible seating and equipment availability.', 12],
            ]],
        ]],
    ];

    $insertElection = $pdo->prepare('INSERT OR IGNORE INTO demo_elections (slug,title,description,category,baseline_ballots,display_order) VALUES (?,?,?,?,?,?)');
    $findElection = $pdo->prepare('SELECT id FROM demo_elections WHERE slug = ?');
    $insertPosition = $pdo->prepare('INSERT OR IGNORE INTO demo_positions (election_id,title,description,display_order) VALUES (?,?,?,?)');
    $findPosition = $pdo->prepare('SELECT id FROM demo_positions WHERE election_id = ? AND title = ?');
    $insertCandidate = $pdo->prepare('INSERT OR IGNORE INTO demo_candidates (position_id,name,slate,year_level,statement,baseline_votes,display_order) VALUES (?,?,?,?,?,?,?)');
    foreach ($samples as $electionOrder => [$slug, $title, $description, $category, $baselineBallots, $positions]) {
        $insertElection->execute([$slug, $title, $description, $category, $baselineBallots, $electionOrder + 1]);
        $findElection->execute([$slug]);
        $electionId = (int) $findElection->fetchColumn();
        foreach ($positions as $positionOrder => [$positionTitle, $positionDescription, $candidates]) {
            $insertPosition->execute([$electionId, $positionTitle, $positionDescription, $positionOrder + 1]);
            $findPosition->execute([$electionId, $positionTitle]);
            $positionId = (int) $findPosition->fetchColumn();
            foreach ($candidates as $candidateOrder => [$name, $slate, $year, $statement, $baselineVotes]) {
                $insertCandidate->execute([$positionId, $name, $slate, $year, $statement, $baselineVotes, $candidateOrder + 1]);
            }
        }
    }
}

function demo_elections_for_user(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare('SELECT e.*, COUNT(DISTINCT p.id) AS position_count, COUNT(DISTINCT c.id) AS candidate_count, b.id AS ballot_id, b.receipt, b.submitted_at FROM demo_elections e LEFT JOIN demo_positions p ON p.election_id = e.id LEFT JOIN demo_candidates c ON c.position_id = p.id LEFT JOIN demo_ballots b ON b.election_id = e.id AND b.user_id = ? GROUP BY e.id ORDER BY e.display_order');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function demo_election(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM demo_elections WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function demo_positions(PDO $pdo, int $electionId): array
{
    $stmt = $pdo->prepare('SELECT * FROM demo_positions WHERE election_id = ? ORDER BY display_order');
    $stmt->execute([$electionId]);
    return $stmt->fetchAll();
}

function demo_candidates(PDO $pdo, int $positionId): array
{
    $stmt = $pdo->prepare('SELECT * FROM demo_candidates WHERE position_id = ? ORDER BY display_order');
    $stmt->execute([$positionId]);
    return $stmt->fetchAll();
}

function demo_owned_ballot(PDO $pdo, int $userId, int $electionId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM demo_ballots WHERE user_id = ? AND election_id = ?');
    $stmt->execute([$userId, $electionId]);
    return $stmt->fetch() ?: null;
}
